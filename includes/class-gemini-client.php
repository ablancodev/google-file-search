<?php
/**
 * Gemini API Client
 * Handles all interactions with Google Gemini File Search API
 */

if (!defined('ABSPATH')) {
    exit;
}

class GFS_Gemini_Client {

    private $api_key;
    private $base_url = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('gfs_gemini_api_key', '');
    }

    /**
     * Create a new File Search store (reemplaza corpora en la nueva API)
     */
    public function create_corpus($display_name = 'WooCommerce Products') {
        $endpoint = $this->base_url . '/fileSearchStores';

        $body = array(
            'displayName' => $display_name
        );

        $response = $this->make_request($endpoint, 'POST', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response['name'] ?? null;
    }

    /**
     * Create a document in the File Search store usando upload directo
     */
    public function create_document($store_id, $display_name, $content) {
        // Log para debugging
        error_log('GFS create_document called with store_id: ' . $store_id);

        // Paso 1: Iniciar upload resumible
        $upload_endpoint = 'https://generativelanguage.googleapis.com/upload/v1beta/' . $store_id . ':uploadToFileSearchStore';
        $url = $upload_endpoint . '?key=' . $this->api_key;

        error_log('GFS Upload endpoint: ' . $upload_endpoint);

        $content_bytes = strlen($content);

        $init_args = array(
            'method' => 'POST',
            'headers' => array(
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => $content_bytes,
                'X-Goog-Upload-Header-Content-Type' => 'text/plain',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'display_name' => $display_name
            )),
            'timeout' => 30
        );

        $init_response = wp_remote_request($url, $init_args);

        if (is_wp_error($init_response)) {
            error_log('Gemini Init Upload WP Error: ' . $init_response->get_error_message());
            return $init_response;
        }

        // Verificar status del init
        $init_status = wp_remote_retrieve_response_code($init_response);
        $init_body = wp_remote_retrieve_body($init_response);

        if ($init_status >= 400) {
            error_log('Gemini Init Upload Failed. Status: ' . $init_status);
            error_log('Gemini Init Upload Response: ' . $init_body);

            $init_data = json_decode($init_body, true);
            $error_message = $init_data['error']['message'] ?? 'Error al iniciar upload';

            return new WP_Error('upload_init_error', $error_message, array(
                'status' => $init_status,
                'response' => $init_data,
                'url' => $upload_endpoint
            ));
        }

        // Obtener URL de upload
        $upload_url = wp_remote_retrieve_header($init_response, 'x-goog-upload-url');

        // También probar con mayúscula
        if (empty($upload_url)) {
            $upload_url = wp_remote_retrieve_header($init_response, 'X-Goog-Upload-Url');
        }

        if (empty($upload_url)) {
            error_log('Gemini Upload URL not received. Init Status: ' . $init_status);
            error_log('Gemini Upload Headers: ' . print_r(wp_remote_retrieve_headers($init_response), true));
            return new WP_Error('upload_error', 'No se recibió URL de upload. Status: ' . $init_status);
        }

        // Paso 2: Subir contenido
        $upload_args = array(
            'method' => 'POST',
            'headers' => array(
                'X-Goog-Upload-Command' => 'upload, finalize',
                'X-Goog-Upload-Offset' => '0',
                'Content-Type' => 'text/plain'
            ),
            'body' => $content,
            'timeout' => 60
        );

        $upload_response = wp_remote_request($upload_url, $upload_args);

        if (is_wp_error($upload_response)) {
            return $upload_response;
        }

        $status_code = wp_remote_retrieve_response_code($upload_response);
        $response_body = wp_remote_retrieve_body($upload_response);
        $data = json_decode($response_body, true);

        if ($status_code >= 400) {
            $error_message = $data['error']['message'] ?? 'Error al subir documento';
            error_log('Gemini Upload Error: ' . $error_message);
            error_log('Response: ' . $response_body);
            return new WP_Error('upload_error', $error_message, array('status' => $status_code, 'response' => $data));
        }

        $operation_id = $data['name'] ?? null;

        if (!$operation_id) {
            return new WP_Error('upload_error', 'No se recibió ID de operación');
        }

        // Convertir operation ID a document ID
        // De: fileSearchStores/{store}/upload/operations/{id}
        // A:  fileSearchStores/{store}/documents/{id}
        $document_id = str_replace('/upload/operations/', '/documents/', $operation_id);

        error_log('GFS: Operation ID: ' . $operation_id);
        error_log('GFS: Document ID: ' . $document_id);

        return $document_id;
    }

    /**
     * Update a document
     *
     * Elimina el documento anterior y crea uno nuevo con el contenido actualizado.
     */
    public function update_document($document_id, $display_name, $content) {
        error_log('GFS update_document called with document_id: ' . $document_id);

        // Extraer store_id del document_id existente
        $store_id = null;

        if (strpos($document_id, 'corpora/') === 0) {
            // Formato antiguo, usar el store configurado
            error_log('GFS: Old corpus format detected');
            $store_id = get_option('gfs_corpus_id', '');
        } elseif (strpos($document_id, 'fileSearchStores/') === 0) {
            // Extraer store_id del document_id
            preg_match('/^(fileSearchStores\/[^\/]+)/', $document_id, $matches);
            $store_id = $matches[1] ?? null;
        }

        if (empty($store_id)) {
            return new WP_Error('no_store', 'No se pudo determinar el File Search Store');
        }

        // Intentar eliminar el documento anterior
        $delete_result = $this->delete_document($document_id);

        if (is_wp_error($delete_result)) {
            error_log('GFS: Delete failed: ' . $delete_result->get_error_message());
            // Continuar de todos modos, el documento puede no existir
        } else {
            error_log('GFS: Old document deleted successfully');
        }

        // Crear el nuevo documento
        return $this->create_document($store_id, $display_name, $content);
    }

    /**
     * Delete a document
     */
    public function delete_document($document_id) {
        error_log('GFS delete_document called with: ' . $document_id);

        // Convertir IDs antiguos (upload/operations) a formato correcto (documents)
        if (strpos($document_id, '/upload/operations/') !== false) {
            $document_id = str_replace('/upload/operations/', '/documents/', $document_id);
            error_log('GFS: Converted to document ID: ' . $document_id);
        }

        // Agregar parámetro force=true para eliminar chunks asociados
        $url = $this->base_url . '/' . $document_id . '?force=true&key=' . $this->api_key;

        error_log('GFS delete_document URL: ' . $url);

        $args = array(
            'method' => 'DELETE',
            'timeout' => 30
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('GFS delete_document WP error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($status_code >= 400) {
            $data = json_decode($response_body, true);
            $error_message = $data['error']['message'] ?? 'Error al eliminar documento';
            error_log('GFS delete_document error: ' . $error_message);
            error_log('GFS delete_document response: ' . $response_body);
            return new WP_Error('delete_error', $error_message, array('status' => $status_code, 'response' => $data));
        }

        error_log('GFS delete_document success');
        return array('success' => true);
    }

    /**
     * Query the File Search store usando generateContent con fileSearch tool
     */
    public function query_corpus($store_id, $query, $results_count = 10) {
        // Con la nueva API, usamos generateContent con fileSearch tool en gemini-2.5-flash
        $endpoint = $this->base_url . '/models/gemini-2.5-flash:generateContent';

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => "Lista los productos más relevantes para: {$query}")
                    )
                )
            ),
            'tools' => array(
                array(
                    'file_search' => array(
                        'file_search_store_names' => array($store_id)
                    )
                )
            )
        );

        $response = $this->make_request($endpoint, 'POST', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        // Extraer chunks del grounding metadata
        $chunks = array();
        if (isset($response['candidates'][0]['groundingMetadata']['groundingChunks'])) {
            $chunks = $response['candidates'][0]['groundingMetadata']['groundingChunks'];
        }

        // Log para debugging
        error_log('GFS query_corpus - Query: ' . $query);
        error_log('GFS query_corpus - Chunks found: ' . count($chunks));
        if (empty($chunks)) {
            error_log('GFS query_corpus - Full response: ' . json_encode($response));
        }

        return $chunks;
    }

    /**
     * Generate content with File Search tool (nueva API)
     */
    public function generate_with_search($store_id, $query) {
        $endpoint = $this->base_url . '/models/gemini-2.5-flash:generateContent';

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $query)
                    )
                )
            ),
            'tools' => array(
                array(
                    'file_search' => array(
                        'file_search_store_names' => array($store_id)
                    )
                )
            )
        );

        $response = $this->make_request($endpoint, 'POST', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Make HTTP request to Gemini API
     */
    private function make_request($endpoint, $method = 'GET', $body = null) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'No se ha configurado la API key de Gemini');
        }

        $url = $endpoint . '?key=' . $this->api_key;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );

        if ($body !== null && in_array($method, array('POST', 'PATCH', 'PUT'))) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($status_code >= 400) {
            // Construir mensaje de error detallado
            $error_message = 'Error desconocido';
            $error_details = array('status' => $status_code);

            if (is_array($data) && isset($data['error'])) {
                if (isset($data['error']['message'])) {
                    $error_message = $data['error']['message'];
                }
                if (isset($data['error']['status'])) {
                    $error_details['error_status'] = $data['error']['status'];
                }
                if (isset($data['error']['code'])) {
                    $error_details['error_code'] = $data['error']['code'];
                }
                // Agregar toda la respuesta para debug
                $error_details['full_response'] = $data;
            } else {
                // Si no hay estructura de error, incluir la respuesta completa
                $error_message = 'Error HTTP ' . $status_code . ': ' . substr($response_body, 0, 200);
                $error_details['raw_response'] = $response_body;
            }

            // Log del error para debugging
            error_log('Gemini API Error: ' . $error_message);
            error_log('Gemini API Error Details: ' . print_r($error_details, true));

            return new WP_Error('api_error', $error_message, $error_details);
        }

        // For DELETE requests, return success even if body is empty
        if ($method === 'DELETE' && $status_code === 200) {
            return array('success' => true);
        }

        return $data;
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        $endpoint = $this->base_url . '/corpora';

        $response = $this->make_request($endpoint, 'GET');

        return !is_wp_error($response);
    }

    /**
     * List all File Search Stores
     */
    public function list_stores() {
        $endpoint = $this->base_url . '/fileSearchStores';

        $response = $this->make_request($endpoint, 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        return $response['fileSearchStores'] ?? array();
    }

    /**
     * Delete a File Search Store
     */
    public function delete_store($store_id) {
        $endpoint = $this->base_url . '/' . $store_id;

        $response = $this->make_request($endpoint, 'DELETE');

        return $response;
    }

    /**
     * List documents in a File Search Store
     */
    public function list_documents($store_id, $page_token = null) {
        $endpoint = $this->base_url . '/' . $store_id . '/documents';

        if ($page_token) {
            $endpoint .= '?pageToken=' . urlencode($page_token) . '&key=' . $this->api_key;
        } else {
            $endpoint .= '?key=' . $this->api_key;
        }

        $response = wp_remote_get($endpoint, array('timeout' => 30));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($status_code >= 400) {
            $error_message = $data['error']['message'] ?? 'Error al listar documentos';
            return new WP_Error('list_error', $error_message, array('status' => $status_code));
        }

        return $data;
    }
}
