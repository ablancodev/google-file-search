<?php
/**
 * Product Sync Manager
 * Handles synchronization of WooCommerce products to Gemini File Search
 */

if (!defined('ABSPATH')) {
    exit;
}

class GFS_Product_Sync {

    private static $instance = null;
    private $gemini_client;

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Sync on product save/update
        if (get_option('gfs_sync_on_save', 'yes') === 'yes') {
            add_action('woocommerce_update_product', array($this, 'sync_product'), 10, 1);
            add_action('woocommerce_new_product', array($this, 'sync_product'), 10, 1);
        }

        // Sync on product delete
        add_action('before_delete_post', array($this, 'delete_product_sync'), 10, 1);

        // Bulk sync action
        add_action('gfs_bulk_sync_products', array($this, 'bulk_sync_all_products'));
    }

    /**
     * Generate product content for Gemini
     */
    private function generate_product_content($product) {
        if (!is_a($product, 'WC_Product')) {
            $product = wc_get_product($product);
        }

        if (!$product) {
            return '';
        }

        $content = array();

        // Basic info
        $content[] = "Nombre del producto: " . $product->get_name();
        $content[] = "SKU: " . $product->get_sku();
        $content[] = "Precio: " . $product->get_price() . " " . get_woocommerce_currency();

        // Description
        if ($product->get_description()) {
            $content[] = "Descripción: " . wp_strip_all_tags($product->get_description());
        }

        if ($product->get_short_description()) {
            $content[] = "Descripción corta: " . wp_strip_all_tags($product->get_short_description());
        }

        // Categories
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        if (!empty($categories)) {
            $content[] = "Categorías: " . implode(', ', $categories);
        }

        // Tags
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
        if (!empty($tags)) {
            $content[] = "Etiquetas: " . implode(', ', $tags);
        }

        // Stock status
        $stock_status = $product->get_stock_status();
        $content[] = "Estado de stock: " . ($stock_status === 'instock' ? 'En stock' : 'Sin stock');

        // Attributes
        if ($product->is_type('variable')) {
            $attributes = $product->get_variation_attributes();
            if (!empty($attributes)) {
                $attr_text = array();
                foreach ($attributes as $attr_name => $values) {
                    $attr_text[] = $attr_name . ': ' . implode(', ', $values);
                }
                $content[] = "Atributos variables: " . implode('; ', $attr_text);
            }
        } else {
            $attributes = $product->get_attributes();
            if (!empty($attributes)) {
                $attr_text = array();
                foreach ($attributes as $attribute) {
                    if (is_a($attribute, 'WC_Product_Attribute')) {
                        $attr_text[] = wc_attribute_label($attribute->get_name()) . ': ' .
                                     implode(', ', $attribute->get_options());
                    }
                }
                if (!empty($attr_text)) {
                    $content[] = "Atributos: " . implode('; ', $attr_text);
                }
            }
        }

        // URL
        $content[] = "URL: " . $product->get_permalink();

        // Product ID (for reference)
        $content[] = "ID del producto: " . $product->get_id();

        return implode("\n", $content);
    }

    /**
     * Sync a single product to Gemini
     */
    public function sync_product($product_id) {
        // Prevenir sincronizaciones duplicadas en la misma request
        static $syncing = array();

        if (isset($syncing[$product_id])) {
            error_log('GFS: Skipping duplicate sync for product ' . $product_id);
            return $syncing[$product_id];
        }

        // Verificar si se sincronizó recientemente (últimos 10 segundos)
        $last_sync = get_post_meta($product_id, '_gfs_last_sync', true);
        if ($last_sync && (time() - strtotime($last_sync)) < 10) {
            error_log('GFS: Product ' . $product_id . ' synced recently, skipping');
            return get_post_meta($product_id, '_gfs_document_id', true);
        }

        $product = wc_get_product($product_id);

        if (!$product || $product->get_status() !== 'publish') {
            return new WP_Error('invalid_product', 'Producto no válido o no publicado');
        }

        $corpus_id = get_option('gfs_corpus_id', '');
        if (empty($corpus_id)) {
            return new WP_Error('no_corpus', 'No se ha configurado el corpus de Gemini');
        }

        // Generate product content
        $content = $this->generate_product_content($product);
        $display_name = $product->get_name() . ' (ID: ' . $product_id . ')';

        // Check if product already has a document
        $existing_doc_id = get_post_meta($product_id, '_gfs_document_id', true);

        if ($existing_doc_id) {
            // Update existing document
            $result = $this->gemini_client->update_document($existing_doc_id, $display_name, $content);

            // Si la actualización fue exitosa y el documento viejo fue eliminado,
            // limpiar el registro viejo de la base de datos
            if (!is_wp_error($result)) {
                global $wpdb;
                // Eliminar registros del documento viejo
                $wpdb->delete(
                    $wpdb->prefix . 'gfs_sync_log',
                    array('product_id' => $product_id, 'document_id' => $existing_doc_id),
                    array('%d', '%s')
                );
                // También convertir y eliminar versión con /documents/ por si acaso
                $existing_doc_id_converted = str_replace('/upload/operations/', '/documents/', $existing_doc_id);
                if ($existing_doc_id_converted !== $existing_doc_id) {
                    $wpdb->delete(
                        $wpdb->prefix . 'gfs_sync_log',
                        array('product_id' => $product_id, 'document_id' => $existing_doc_id_converted),
                        array('%d', '%s')
                    );
                }
            }
        } else {
            // Create new document
            $result = $this->gemini_client->create_document($corpus_id, $display_name, $content);
        }

        // Marcar como sincronizando
        $syncing[$product_id] = $result;

        // Log the sync
        $this->log_sync($product_id, $result);

        if (is_wp_error($result)) {
            return $result;
        }

        // Save document ID
        update_post_meta($product_id, '_gfs_document_id', $result);
        update_post_meta($product_id, '_gfs_last_sync', current_time('mysql'));

        return $result;
    }

    /**
     * Delete product from Gemini when deleted from WooCommerce
     */
    public function delete_product_sync($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $document_id = get_post_meta($post_id, '_gfs_document_id', true);

        if ($document_id) {
            $result = $this->gemini_client->delete_document($document_id);
            $this->log_sync($post_id, $result, 'deleted');
        }
    }

    /**
     * Bulk sync all published products
     */
    public function bulk_sync_all_products() {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $product_ids = get_posts($args);
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );

        foreach ($product_ids as $product_id) {
            $result = $this->sync_product($product_id);

            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = array(
                    'product_id' => $product_id,
                    'error' => $result->get_error_message()
                );
            } else {
                $results['success']++;
            }

            // Prevent timeouts
            usleep(500000); // 0.5 seconds between requests
        }

        update_option('gfs_last_bulk_sync', current_time('mysql'));
        update_option('gfs_last_bulk_sync_results', $results);

        return $results;
    }

    /**
     * Log sync operation to database
     */
    private function log_sync($product_id, $result, $action = 'sync') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gfs_sync_log';

        $data = array(
            'product_id' => $product_id,
            'sync_status' => is_wp_error($result) ? 'error' : 'success',
            'sync_date' => current_time('mysql')
        );

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $error_data = $result->get_error_data();

            // Si hay datos adicionales del error, incluirlos en el mensaje
            if (is_array($error_data)) {
                if (isset($error_data['error_status'])) {
                    $error_message .= ' [Status: ' . $error_data['error_status'] . ']';
                }
                if (isset($error_data['error_code'])) {
                    $error_message .= ' [Code: ' . $error_data['error_code'] . ']';
                }
                // Log adicional para debugging
                error_log('Product Sync Error Details for Product ID ' . $product_id . ': ' . print_r($error_data, true));
            }

            $data['error_message'] = substr($error_message, 0, 500); // Limitar a 500 caracteres
            $data['document_id'] = '';
        } else {
            $data['document_id'] = is_string($result) ? $result : '';
            $data['error_message'] = null;
        }

        $wpdb->insert($table_name, $data);
    }

    /**
     * Get sync status for a product
     */
    public function get_product_sync_status($product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gfs_sync_log';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d ORDER BY sync_date DESC LIMIT 1",
            $product_id
        ));

        return $result;
    }

    /**
     * Get recent sync logs
     */
    public function get_recent_sync_logs($limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gfs_sync_log';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY sync_date DESC LIMIT %d",
            $limit
        ));

        return $results;
    }
}
