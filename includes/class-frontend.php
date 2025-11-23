<?php
/**
 * Frontend Interface
 * Handles shortcodes and frontend display
 */

if (!defined('ABSPATH')) {
    exit;
}

class GFS_Frontend {

    private static $instance = null;

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
        add_shortcode('gfs_search', array($this, 'render_search_shortcode'));
        add_shortcode('gfs_ai_search', array($this, 'render_ai_search_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('gfs-frontend', GFS_PLUGIN_URL . 'assets/css/frontend.css', array(), GFS_VERSION);
        wp_enqueue_script('gfs-frontend', GFS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), GFS_VERSION, true);

        wp_localize_script('gfs-frontend', 'gfsData', array(
            'rest_url' => rest_url('gfs/v1/'),
            'nonce' => wp_create_nonce('wp_rest')
        ));
    }

    /**
     * Render basic search shortcode
     * Usage: [gfs_search]
     */
    public function render_search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => 'Buscar productos...',
            'button_text' => 'Buscar',
            'results_per_page' => 10
        ), $atts);

        ob_start();
        ?>
        <div class="gfs-search-wrapper">
            <form class="gfs-search-form" data-results-limit="<?php echo esc_attr($atts['results_per_page']); ?>">
                <div class="gfs-search-input-wrapper">
                    <input type="text"
                           class="gfs-search-input"
                           name="gfs_query"
                           placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                           required />
                    <button type="submit" class="gfs-search-button">
                        <?php echo esc_html($atts['button_text']); ?>
                    </button>
                </div>
                <div class="gfs-search-loading" style="display:none;">
                    <span class="gfs-spinner"></span>
                    <span>Buscando...</span>
                </div>
            </form>

            <div class="gfs-search-results"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render AI-powered search shortcode
     * Usage: [gfs_ai_search]
     */
    public function render_ai_search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => 'Describe lo que buscas...',
            'button_text' => 'Buscar con IA'
        ), $atts);

        ob_start();
        ?>
        <div class="gfs-ai-search-wrapper">
            <form class="gfs-ai-search-form">
                <div class="gfs-ai-search-input-wrapper">
                    <textarea class="gfs-ai-search-input"
                              name="gfs_ai_query"
                              rows="3"
                              placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                              required></textarea>
                    <button type="submit" class="gfs-ai-search-button">
                        <?php echo esc_html($atts['button_text']); ?>
                    </button>
                </div>
                <div class="gfs-ai-search-loading" style="display:none;">
                    <span class="gfs-spinner"></span>
                    <span>El IA está procesando tu búsqueda...</span>
                </div>
            </form>

            <div class="gfs-ai-response"></div>
            <div class="gfs-ai-search-results"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
