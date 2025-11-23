<?php
/**
 * Admin Interface
 * Handles admin pages and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class GFS_Admin {

    private static $instance = null;
    private $gemini_client;
    private $product_sync;

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
        $this->product_sync = GFS_Product_Sync::get_instance();

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_gfs_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_gfs_create_corpus', array($this, 'ajax_create_corpus'));
        add_action('wp_ajax_gfs_bulk_sync', array($this, 'ajax_bulk_sync'));
        add_action('wp_ajax_gfs_list_stores', array($this, 'ajax_list_stores'));
        add_action('wp_ajax_gfs_delete_store', array($this, 'ajax_delete_store'));
        add_action('wp_ajax_gfs_list_documents', array($this, 'ajax_list_documents'));
        add_action('wp_ajax_gfs_delete_document', array($this, 'ajax_delete_document'));
        add_action('wp_ajax_gfs_clean_orphans', array($this, 'ajax_clean_orphans'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Gemini File Search',
            'Gemini Search',
            'manage_options',
            'gfs-settings',
            array($this, 'render_settings_page'),
            'dashicons-search',
            56
        );

        add_submenu_page(
            'gfs-settings',
            'Configuración',
            'Configuración',
            'manage_options',
            'gfs-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'gfs-settings',
            'Sincronización',
            'Sincronización',
            'manage_options',
            'gfs-sync',
            array($this, 'render_sync_page')
        );

        add_submenu_page(
            'gfs-settings',
            'Prueba de Búsqueda',
            'Prueba de Búsqueda',
            'manage_options',
            'gfs-test',
            array($this, 'render_test_page')
        );

        add_submenu_page(
            'gfs-settings',
            'Gestión de Stores',
            'Gestión de Stores',
            'manage_options',
            'gfs-manage',
            array($this, 'render_manage_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('gfs_settings', 'gfs_gemini_api_key');
        register_setting('gfs_settings', 'gfs_corpus_id');
        register_setting('gfs_settings', 'gfs_auto_sync');
        register_setting('gfs_settings', 'gfs_sync_on_save');
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'gfs-') === false) {
            return;
        }

        wp_enqueue_style('gfs-admin', GFS_PLUGIN_URL . 'assets/css/admin.css', array(), GFS_VERSION);
        wp_enqueue_script('gfs-admin', GFS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), GFS_VERSION, true);

        wp_localize_script('gfs-admin', 'gfsAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('gfs/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'admin_nonce' => wp_create_nonce('gfs_admin_nonce')
        ));
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap gfs-admin-page">
            <h1>Configuración de Gemini File Search</h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php settings_fields('gfs_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gfs_gemini_api_key">API Key de Gemini</label>
                        </th>
                        <td>
                            <input type="text" id="gfs_gemini_api_key" name="gfs_gemini_api_key"
                                   value="<?php echo esc_attr(get_option('gfs_gemini_api_key')); ?>"
                                   class="regular-text" />
                            <p class="description">
                                Obtén tu API key desde <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>
                            </p>
                            <button type="button" class="button" id="gfs-test-connection">Probar Conexión</button>
                            <span id="gfs-connection-status"></span>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="gfs_corpus_id">Corpus ID</label>
                        </th>
                        <td>
                            <input type="text" id="gfs_corpus_id" name="gfs_corpus_id"
                                   value="<?php echo esc_attr(get_option('gfs_corpus_id')); ?>"
                                   class="regular-text" readonly />
                            <p class="description">
                                ID del corpus donde se almacenan los productos
                            </p>
                            <button type="button" class="button" id="gfs-create-corpus">Crear Nuevo Corpus</button>
                            <span id="gfs-corpus-status"></span>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="gfs_sync_on_save">Sincronización Automática</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="gfs_sync_on_save" name="gfs_sync_on_save" value="yes"
                                       <?php checked(get_option('gfs_sync_on_save', 'yes'), 'yes'); ?> />
                                Sincronizar productos automáticamente al guardar/actualizar
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2>Información del Sistema</h2>
            <table class="widefat">
                <tr>
                    <th>Versión del Plugin</th>
                    <td><?php echo GFS_VERSION; ?></td>
                </tr>
                <tr>
                    <th>WooCommerce Version</th>
                    <td><?php echo defined('WC_VERSION') ? WC_VERSION : 'N/A'; ?></td>
                </tr>
                <tr>
                    <th>Total de Productos</th>
                    <td><?php echo wp_count_posts('product')->publish; ?></td>
                </tr>
                <tr>
                    <th>Productos Sincronizados</th>
                    <td>
                        <?php
                        global $wpdb;
                        $count = $wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM {$wpdb->prefix}gfs_sync_log WHERE sync_status = 'success'");
                        echo $count;
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Última Sincronización Masiva</th>
                    <td><?php echo get_option('gfs_last_bulk_sync', 'Nunca'); ?></td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render sync page
     */
    public function render_sync_page() {
        ?>
        <div class="wrap gfs-admin-page">
            <h1>Sincronización de Productos</h1>

            <div class="gfs-sync-controls">
                <button type="button" class="button button-primary button-large" id="gfs-bulk-sync">
                    Sincronizar Todos los Productos
                </button>
                <p class="description">
                    Esto sincronizará todos los productos publicados con Gemini File Search.
                    El proceso puede tardar varios minutos dependiendo del número de productos.
                </p>
            </div>

            <div id="gfs-sync-progress" style="display:none; margin: 20px 0;">
                <div class="gfs-progress-bar">
                    <div class="gfs-progress-bar-fill"></div>
                </div>
                <p id="gfs-sync-status">Sincronizando...</p>
            </div>

            <div id="gfs-sync-results"></div>

            <hr>

            <h2>Historial de Sincronización</h2>
            <?php $this->render_sync_log(); ?>
        </div>
        <?php
    }

    /**
     * Render sync log table
     */
    private function render_sync_log() {
        $logs = $this->product_sync->get_recent_sync_logs(50);

        if (empty($logs)) {
            echo '<p>No hay registros de sincronización.</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Document ID</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php $product = wc_get_product($log->product_id); ?>
                    <tr>
                        <td>
                            <?php if ($product): ?>
                                <a href="<?php echo get_edit_post_link($log->product_id); ?>">
                                    <?php echo $product->get_name(); ?> (#<?php echo $log->product_id; ?>)
                                </a>
                            <?php else: ?>
                                Producto #<?php echo $log->product_id; ?> (eliminado)
                            <?php endif; ?>
                        </td>
                        <td>
                            <code title="<?php echo esc_attr($log->document_id); ?>">
                                <?php
                                // Mostrar la parte única del documento (últimos 30 caracteres)
                                $doc_id = $log->document_id;
                                if (strlen($doc_id) > 50) {
                                    echo '...' . esc_html(substr($doc_id, -47));
                                } else {
                                    echo esc_html($doc_id);
                                }
                                ?>
                            </code>
                        </td>
                        <td>
                            <span class="gfs-status-<?php echo esc_attr($log->sync_status); ?>">
                                <?php echo esc_html(ucfirst($log->sync_status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->sync_date); ?></td>
                        <td><?php echo esc_html($log->error_message); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render test page
     */
    public function render_test_page() {
        ?>
        <div class="wrap gfs-admin-page">
            <h1>Prueba de Búsqueda Semántica</h1>

            <div class="gfs-search-test">
                <h2>Búsqueda Simple</h2>
                <input type="text" id="gfs-test-query" class="regular-text"
                       placeholder="Ej: camiseta roja de algodón" />
                <button type="button" class="button button-primary" id="gfs-test-search">Buscar</button>

                <div id="gfs-test-results" style="margin-top: 20px;"></div>
            </div>

            <hr>

            <div class="gfs-ai-search-test">
                <h2>Búsqueda con IA (Respuesta Natural)</h2>
                <textarea id="gfs-ai-query" rows="3" class="large-text"
                          placeholder="Ej: Necesito un regalo para mi madre, le gusta la jardinería"></textarea>
                <button type="button" class="button button-primary" id="gfs-ai-search">Buscar con IA</button>

                <div id="gfs-ai-results" style="margin-top: 20px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('gfs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $result = $this->gemini_client->test_connection();

        if ($result) {
            wp_send_json_success('Conexión exitosa');
        } else {
            wp_send_json_error('Error de conexión. Verifica tu API key.');
        }
    }

    /**
     * AJAX: Create corpus
     */
    public function ajax_create_corpus() {
        check_ajax_referer('gfs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $corpus_id = $this->gemini_client->create_corpus('WooCommerce Products - ' . get_bloginfo('name'));

        if (is_wp_error($corpus_id)) {
            wp_send_json_error($corpus_id->get_error_message());
        }

        update_option('gfs_corpus_id', $corpus_id);
        wp_send_json_success(array('corpus_id' => $corpus_id));
    }

    /**
     * AJAX: Bulk sync
     */
    public function ajax_bulk_sync() {
        check_ajax_referer('gfs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        // Increase time limit
        set_time_limit(300);

        $results = $this->product_sync->bulk_sync_all_products();

        wp_send_json_success($results);
    }

    /**
     * Render manage stores page
     */
    public function render_manage_page() {
        ?>
        <div class="wrap gfs-admin-page">
            <h1>Gestión de File Search Stores</h1>

            <div class="gfs-manage-controls">
                <button type="button" class="button button-primary" id="gfs-refresh-stores">
                    Actualizar Lista
                </button>
                <button type="button" class="button" id="gfs-clean-orphans">
                    Limpiar Documentos Huérfanos
                </button>
                <p class="description">
                    Elimina documentos de productos que ya no existen en WooCommerce.
                </p>
            </div>

            <div id="gfs-stores-container" style="margin-top: 20px;">
                <p>Cargando stores...</p>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: List all stores
     */
    public function ajax_list_stores() {
        check_ajax_referer('gfs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $stores = $this->gemini_client->list_stores();

        if (is_wp_error($stores)) {
            wp_send_json_error($stores->get_error_message());
        }

        // Obtener el store configurado actualmente
        $current_store = get_option('gfs_corpus_id', '');

        // Obtener stats de la DB local
        global $wpdb;
        $local_stats = array();

        foreach ($stores as $store) {
            $store_id = $store['name'];
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT product_id) FROM {$wpdb->prefix}gfs_sync_log WHERE sync_status = 'success' AND document_id LIKE %s",
                $store_id . '%'
            ));
            $local_stats[$store_id] = $count;
        }

        wp_send_json_success(array(
            'stores' => $stores,
            'current_store' => $current_store,
            'local_stats' => $local_stats
        ));
    }

    /**
     * AJAX: Delete store
     */
    public function ajax_delete_store() {
        check_ajax_referer('gfs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $store_id = isset($_POST['store_id']) ? sanitize_text_field($_POST['store_id']) : '';

        if (empty($store_id)) {
            wp_send_json_error('Store ID requerido');
        }

        // No permitir borrar el store activo
        $current_store = get_option('gfs_corpus_id', '');
        if ($store_id === $current_store) {
            wp_send_json_error('No puedes eliminar el store activo. Cambia primero a otro store.');
        }

        $result = $this->gemini_client->delete_store($store_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success('Store eliminado exitosamente');
    }

    /**
     * AJAX: List documents in a store
     */
    public function ajax_list_documents() {
        check_ajax_referer('gfs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $store_id = isset($_POST['store_id']) ? sanitize_text_field($_POST['store_id']) : '';

        if (empty($store_id)) {
            wp_send_json_error('Store ID requerido');
        }

        // La API puede no soportar listar documentos, usar datos locales
        global $wpdb;
        $documents = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT product_id, document_id, sync_date
             FROM {$wpdb->prefix}gfs_sync_log
             WHERE sync_status = 'success' AND document_id LIKE %s
             ORDER BY sync_date DESC",
            $store_id . '%'
        ));

        $result = array();
        foreach ($documents as $doc) {
            $product = wc_get_product($doc->product_id);
            $result[] = array(
                'document_id' => $doc->document_id,
                'product_id' => $doc->product_id,
                'product_name' => $product ? $product->get_name() : 'Producto eliminado',
                'product_exists' => $product ? true : false,
                'sync_date' => $doc->sync_date
            );
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Delete single document
     */
    public function ajax_delete_document() {
        check_ajax_referer('gfs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        $document_id = isset($_POST['document_id']) ? sanitize_text_field($_POST['document_id']) : '';
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if (empty($document_id)) {
            wp_send_json_error('Document ID requerido');
        }

        $result = $this->gemini_client->delete_document($document_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Limpiar metadata del producto
        if ($product_id) {
            delete_post_meta($product_id, '_gfs_document_id');
        }

        wp_send_json_success('Documento eliminado exitosamente');
    }

    /**
     * AJAX: Clean orphan documents
     *
     * Limpia registros de documentos que ya no existen en Gemini
     */
    public function ajax_clean_orphans() {
        check_ajax_referer('gfs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }

        set_time_limit(120);

        $store_id = get_option('gfs_corpus_id', '');
        if (empty($store_id)) {
            wp_send_json_error('No hay store configurado');
        }

        // Obtener todos los documentos reales de Gemini
        $gemini_docs = array();
        $next_page_token = null;

        do {
            $list_result = $this->gemini_client->list_documents($store_id, $next_page_token);

            if (is_wp_error($list_result)) {
                wp_send_json_error('Error al obtener documentos de Gemini: ' . $list_result->get_error_message());
            }

            if (isset($list_result['documents'])) {
                foreach ($list_result['documents'] as $doc) {
                    $gemini_docs[$doc['name']] = true;
                }
            }

            $next_page_token = $list_result['nextPageToken'] ?? null;

        } while ($next_page_token);

        // Obtener todos los registros locales
        global $wpdb;
        $local_records = $wpdb->get_results(
            "SELECT id, product_id, document_id
             FROM {$wpdb->prefix}gfs_sync_log
             WHERE sync_status = 'success'"
        );

        // Encontrar huérfanos (registros que no existen en Gemini)
        $cleaned = 0;
        $products_to_clean_meta = array();

        foreach ($local_records as $record) {
            $doc_id = $record->document_id;

            // Convertir IDs antiguos para comparación
            if (strpos($doc_id, '/upload/operations/') !== false) {
                $doc_id = str_replace('/upload/operations/', '/documents/', $doc_id);
            }

            // Si el documento no existe en Gemini, eliminar el registro local
            if (!isset($gemini_docs[$doc_id])) {
                $wpdb->delete(
                    $wpdb->prefix . 'gfs_sync_log',
                    array('id' => $record->id),
                    array('%d')
                );

                $cleaned++;

                // Marcar producto para limpiar metadata
                $products_to_clean_meta[$record->product_id] = true;
            }
        }

        // Limpiar metadata de productos huérfanos
        foreach (array_keys($products_to_clean_meta) as $product_id) {
            // Solo limpiar si el producto ya no tiene ningún registro válido
            $has_valid_record = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gfs_sync_log WHERE product_id = %d AND sync_status = 'success'",
                $product_id
            ));

            if (!$has_valid_record) {
                delete_post_meta($product_id, '_gfs_document_id');
                delete_post_meta($product_id, '_gfs_last_sync');
            }
        }

        wp_send_json_success(array(
            'cleaned' => $cleaned,
            'total' => count($local_records),
            'gemini_docs' => count($gemini_docs)
        ));
    }
}
