<?php
/**
 * Examples of using Google Gemini File Search Plugin
 *
 * This file contains code examples for developers
 * DO NOT include this file in your theme - these are just examples
 */

// ============================================
// EXAMPLE 1: Sincronizar un producto específico
// ============================================

function my_sync_single_product($product_id) {
    $product_sync = GFS_Product_Sync::get_instance();
    $result = $product_sync->sync_product($product_id);

    if (is_wp_error($result)) {
        error_log('Error syncing product: ' . $result->get_error_message());
        return false;
    }

    return true;
}

// Uso:
// my_sync_single_product(123);


// ============================================
// EXAMPLE 2: Sincronización masiva programada
// ============================================

function my_schedule_bulk_sync() {
    // Programar sincronización diaria
    if (!wp_next_scheduled('my_daily_gfs_sync')) {
        wp_schedule_event(time(), 'daily', 'my_daily_gfs_sync');
    }
}
add_action('wp', 'my_schedule_bulk_sync');

function my_run_bulk_sync() {
    $product_sync = GFS_Product_Sync::get_instance();
    $results = $product_sync->bulk_sync_all_products();

    // Enviar email con resultados
    $message = "Sincronización completada:\n";
    $message .= "Exitosos: " . $results['success'] . "\n";
    $message .= "Fallidos: " . $results['failed'];

    wp_mail(get_option('admin_email'), 'Reporte de Sincronización', $message);
}
add_action('my_daily_gfs_sync', 'my_run_bulk_sync');


// ============================================
// EXAMPLE 3: Búsqueda personalizada en PHP
// ============================================

function my_custom_search($query) {
    $gemini_client = new GFS_Gemini_Client();
    $corpus_id = get_option('gfs_corpus_id');

    if (empty($corpus_id)) {
        return array();
    }

    $chunks = $gemini_client->query_corpus($corpus_id, $query, 20);

    if (is_wp_error($chunks)) {
        return array();
    }

    // Procesar resultados
    $product_ids = array();
    foreach ($chunks as $chunk) {
        // Extraer ID del producto del chunk
        if (preg_match('/ID del producto:\s*(\d+)/', $chunk['chunk']['data']['stringValue'], $matches)) {
            $product_ids[] = intval($matches[1]);
        }
    }

    return $product_ids;
}

// Uso:
// $products = my_custom_search('zapatos deportivos');


// ============================================
// EXAMPLE 4: Widget personalizado de búsqueda
// ============================================

class My_GFS_Search_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'my_gfs_search_widget',
            'Búsqueda Semántica',
            array('description' => 'Búsqueda de productos con Gemini AI')
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . $instance['title'] . $args['after_title'];
        }

        echo do_shortcode('[gfs_search placeholder="' . esc_attr($instance['placeholder']) . '"]');

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Buscar productos';
        $placeholder = !empty($instance['placeholder']) ? $instance['placeholder'] : 'Buscar...';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Título:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                   name="<?php echo $this->get_field_name('title'); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('placeholder'); ?>">Placeholder:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('placeholder'); ?>"
                   name="<?php echo $this->get_field_name('placeholder'); ?>" type="text"
                   value="<?php echo esc_attr($placeholder); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['placeholder'] = (!empty($new_instance['placeholder'])) ? strip_tags($new_instance['placeholder']) : '';
        return $instance;
    }
}

// Registrar el widget
function my_register_gfs_widget() {
    register_widget('My_GFS_Search_Widget');
}
add_action('widgets_init', 'my_register_gfs_widget');


// ============================================
// EXAMPLE 5: Modificar contenido antes de sincronizar
// ============================================

function my_modify_product_content($content, $product) {
    // Añadir información adicional
    $content .= "\n\nMarca: " . $product->get_attribute('marca');
    $content .= "\nMaterial: " . $product->get_attribute('material');

    // Añadir metadatos personalizados
    $custom_field = get_post_meta($product->get_id(), '_mi_campo_personalizado', true);
    if ($custom_field) {
        $content .= "\nInformación adicional: " . $custom_field;
    }

    return $content;
}
add_filter('gfs_product_content', 'my_modify_product_content', 10, 2);


// ============================================
// EXAMPLE 6: Mostrar búsqueda en header del tema
// ============================================

function my_add_search_to_header() {
    if (is_shop() || is_product_category() || is_product()) {
        ?>
        <div class="my-semantic-search">
            <?php echo do_shortcode('[gfs_search placeholder="Busca con IA..." button_text="🔍"]'); ?>
        </div>
        <?php
    }
}
add_action('wp_header', 'my_add_search_to_header');


// ============================================
// EXAMPLE 7: AJAX personalizado para búsqueda
// ============================================

function my_ajax_semantic_search() {
    check_ajax_referer('my_search_nonce', 'nonce');

    $query = sanitize_text_field($_POST['query']);

    $gemini_client = new GFS_Gemini_Client();
    $corpus_id = get_option('gfs_corpus_id');

    $chunks = $gemini_client->query_corpus($corpus_id, $query, 10);

    if (is_wp_error($chunks)) {
        wp_send_json_error($chunks->get_error_message());
    }

    // Procesar y formatear resultados
    $products = array();
    // ... procesar chunks ...

    wp_send_json_success(array('products' => $products));
}
add_action('wp_ajax_my_semantic_search', 'my_ajax_semantic_search');
add_action('wp_ajax_nopriv_my_semantic_search', 'my_ajax_semantic_search');


// ============================================
// EXAMPLE 8: Sincronizar solo productos de una categoría
// ============================================

function my_sync_category_products($category_id) {
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $category_id
            )
        ),
        'fields' => 'ids'
    );

    $product_ids = get_posts($args);
    $product_sync = GFS_Product_Sync::get_instance();

    foreach ($product_ids as $product_id) {
        $product_sync->sync_product($product_id);
        usleep(500000); // 0.5 seconds delay
    }
}

// Uso:
// my_sync_category_products(15); // Sincronizar categoría con ID 15


// ============================================
// EXAMPLE 9: Obtener estadísticas de sincronización
// ============================================

function my_get_sync_stats() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gfs_sync_log';

    $stats = array(
        'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
        'success' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE sync_status = 'success'"),
        'error' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE sync_status = 'error'"),
        'last_sync' => $wpdb->get_var("SELECT MAX(sync_date) FROM $table_name WHERE sync_status = 'success'")
    );

    return $stats;
}

// Uso:
// $stats = my_get_sync_stats();
// echo "Última sincronización: " . $stats['last_sync'];


// ============================================
// EXAMPLE 10: Crear página de búsqueda personalizada
// ============================================

function my_create_search_page_template() {
    /*
    Template Name: Búsqueda Semántica
    */

    get_header();
    ?>

    <div class="my-search-page">
        <h1>Encuentra tu producto ideal</h1>
        <p>Usa nuestra búsqueda inteligente con IA</p>

        <?php echo do_shortcode('[gfs_ai_search]'); ?>

        <div class="search-suggestions">
            <h3>Búsquedas sugeridas:</h3>
            <ul>
                <li><a href="#" class="quick-search" data-query="Ropa deportiva cómoda">Ropa deportiva cómoda</a></li>
                <li><a href="#" class="quick-search" data-query="Regalo para cumpleaños">Regalo para cumpleaños</a></li>
                <li><a href="#" class="quick-search" data-query="Productos ecológicos">Productos ecológicos</a></li>
            </ul>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.quick-search').on('click', function(e) {
            e.preventDefault();
            var query = $(this).data('query');
            $('.gfs-ai-search-input').val(query);
            $('.gfs-ai-search-form').submit();
        });
    });
    </script>

    <?php
    get_footer();
}


// ============================================
// EXAMPLE 11: Integración con WooCommerce Product Search
// ============================================

function my_integrate_with_wc_search($query) {
    if (!is_admin() && $query->is_main_query() && is_search()) {
        $search_query = get_search_query();

        // Usar Gemini para mejorar resultados
        $product_ids = my_custom_search($search_query);

        if (!empty($product_ids)) {
            $query->set('post_type', 'product');
            $query->set('post__in', $product_ids);
            $query->set('orderby', 'post__in');
        }
    }
}
add_action('pre_get_posts', 'my_integrate_with_wc_search');


// ============================================
// EXAMPLE 12: Hooks personalizados
// ============================================

// Después de sincronizar un producto
function my_after_product_sync($product_id, $document_id) {
    // Registrar en log personalizado
    error_log("Producto $product_id sincronizado con documento $document_id");

    // Actualizar caché
    wp_cache_delete('gfs_product_' . $product_id);

    // Enviar notificación
    // ...
}
add_action('gfs_product_synced', 'my_after_product_sync', 10, 2);


// ============================================
// EXAMPLE 13: Shortcode personalizado con filtros
// ============================================

function my_custom_search_shortcode($atts) {
    $atts = shortcode_atts(array(
        'category' => '',
        'placeholder' => 'Buscar...',
        'min_price' => 0,
        'max_price' => 999999
    ), $atts);

    ob_start();
    ?>
    <div class="my-filtered-search">
        <?php echo do_shortcode('[gfs_search placeholder="' . esc_attr($atts['placeholder']) . '"]'); ?>

        <input type="hidden" class="filter-category" value="<?php echo esc_attr($atts['category']); ?>">
        <input type="hidden" class="filter-min-price" value="<?php echo esc_attr($atts['min_price']); ?>">
        <input type="hidden" class="filter-max-price" value="<?php echo esc_attr($atts['max_price']); ?>">
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('my_filtered_search', 'my_custom_search_shortcode');

// Uso:
// [my_filtered_search category="electronics" min_price="100" max_price="500"]


// ============================================
// NOTES FOR DEVELOPERS
// ============================================

/*
 * Available Filters:
 *
 * - gfs_product_content: Modify product content before syncing
 * - gfs_search_limit: Modify search results limit
 *
 * Available Actions:
 *
 * - gfs_product_synced: After product is synced
 * - gfs_before_delete_sync: Before product sync is deleted
 *
 * Available Classes:
 *
 * - GFS_Gemini_Client: Interact with Gemini API
 * - GFS_Product_Sync: Sync products
 * - GFS_Search_API: Search functionality
 *
 * REST Endpoints:
 *
 * - GET  /wp-json/gfs/v1/search?query=...&limit=10
 * - POST /wp-json/gfs/v1/search-ai (body: {"query": "..."})
 */
