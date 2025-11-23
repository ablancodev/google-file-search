<?php
/**
 * Search API
 * Handles REST API endpoints for semantic search
 */

if (!defined('ABSPATH')) {
    exit;
}

class GFS_Search_API {

    private static $instance = null;
    private $gemini_client;
    private $namespace = 'gfs/v1';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->gemini_client = new GFS_Gemini_Client();
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Search endpoint
        register_rest_route($this->namespace, '/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'search'),
            'permission_callback' => '__return_true',
            'args' => array(
                'query' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Búsqueda semántica',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10,
                    'description' => 'Número de resultados',
                    'sanitize_callback' => 'absint'
                )
            )
        ));

        // Advanced search with AI generation
        register_rest_route($this->namespace, '/search-ai', array(
            'methods' => 'POST',
            'callback' => array($this, 'search_with_ai'),
            'permission_callback' => '__return_true',
            'args' => array(
                'query' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Consulta para el AI',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }

    /**
     * Semantic search endpoint
     */
    public function search($request) {
        $query = $request->get_param('query');
        $limit = $request->get_param('limit');

        $corpus_id = get_option('gfs_corpus_id', '');
        if (empty($corpus_id)) {
            return new WP_Error('no_corpus', 'No se ha configurado el corpus de Gemini', array('status' => 500));
        }

        // Query Gemini
        $chunks = $this->gemini_client->query_corpus($corpus_id, $query, $limit);

        if (is_wp_error($chunks)) {
            return $chunks;
        }

        // Process results and extract product IDs
        $products = $this->process_search_results($chunks);

        return rest_ensure_response(array(
            'success' => true,
            'query' => $query,
            'total_results' => count($products),
            'products' => $products
        ));
    }

    /**
     * AI-powered search with natural language response
     */
    public function search_with_ai($request) {
        $query = $request->get_param('query');

        $corpus_id = get_option('gfs_corpus_id', '');
        if (empty($corpus_id)) {
            return new WP_Error('no_corpus', 'No se ha configurado el corpus de Gemini', array('status' => 500));
        }

        // Build prompt for AI
        $ai_prompt = "Busca productos que coincidan con esta consulta: '$query'. " .
                    "Proporciona una respuesta útil que incluya los productos más relevantes " .
                    "con sus características principales, precios y por qué son buenos para esta búsqueda.";

        $result = $this->gemini_client->generate_with_search($corpus_id, $ai_prompt);

        if (is_wp_error($result)) {
            return $result;
        }

        // Extract text from response
        $ai_response = '';
        $grounding_metadata = null;

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $ai_response = $result['candidates'][0]['content']['parts'][0]['text'];
        }

        if (isset($result['candidates'][0]['groundingMetadata'])) {
            $grounding_metadata = $result['candidates'][0]['groundingMetadata'];
        }

        // Extract product IDs from grounding chunks
        $products = array();
        $seen_ids = array();
        if ($grounding_metadata && isset($grounding_metadata['groundingChunks'])) {
            foreach ($grounding_metadata['groundingChunks'] as $chunk) {
                if (isset($chunk['retrievedContext']['text'])) {
                    $product_id = $this->extract_product_id_from_text($chunk['retrievedContext']['text']);
                    if ($product_id && !in_array($product_id, $seen_ids)) {
                        $product_data = $this->get_product_data($product_id);
                        if ($product_data) {
                            $products[] = $product_data;
                            $seen_ids[] = $product_id;
                        }
                    }
                }
            }
        }

        return rest_ensure_response(array(
            'success' => true,
            'query' => $query,
            'ai_response' => $ai_response,
            'products' => $products,
            'grounding_metadata' => $grounding_metadata
        ));
    }

    /**
     * Process search results from Gemini chunks (nueva API fileSearch)
     */
    private function process_search_results($chunks) {
        $products = array();
        $seen_ids = array();

        foreach ($chunks as $chunk) {
            // Nueva estructura de fileSearch
            $content = '';

            // Intentar obtener el contenido del chunk
            if (isset($chunk['retrievedContext']['text'])) {
                $content = $chunk['retrievedContext']['text'];
            } elseif (isset($chunk['chunk']['data']['stringValue'])) {
                // Fallback para estructura antigua
                $content = $chunk['chunk']['data']['stringValue'];
            }

            if (empty($content)) {
                continue;
            }

            // Extraer product ID del contenido
            $product_id = $this->extract_product_id_from_text($content);

            if ($product_id && !in_array($product_id, $seen_ids)) {
                $product_data = $this->get_product_data($product_id);

                if ($product_data) {
                    // En la nueva API no hay relevance score directo, usar 1.0 por defecto
                    $product_data['relevance_score'] = 1.0;
                    $product_data['matched_text'] = substr($content, 0, 200);
                    $products[] = $product_data;
                    $seen_ids[] = $product_id;
                }
            }
        }

        return $products;
    }

    /**
     * Extract product ID from text content
     */
    private function extract_product_id_from_text($text) {
        if (preg_match('/ID del producto:\s*(\d+)/i', $text, $matches)) {
            return intval($matches[1]);
        }
        return null;
    }

    /**
     * Get product data for API response
     */
    private function get_product_data($product_id) {
        $product = wc_get_product($product_id);

        if (!$product || $product->get_status() !== 'publish') {
            return null;
        }

        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'price_html' => $product->get_price_html(),
            'description' => wp_trim_words(wp_strip_all_tags($product->get_description()), 50),
            'short_description' => wp_strip_all_tags($product->get_short_description()),
            'permalink' => $product->get_permalink(),
            'image' => $image_url,
            'stock_status' => $product->get_stock_status(),
            'in_stock' => $product->is_in_stock(),
            'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names')),
            'tags' => wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'))
        );
    }
}
