<?php

/**
 * The core plugin class.
 *
 * This is used to define admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0
 * @package    Raptive_Affiliate
 * @subpackage Raptive_Affiliate/includes
 * @author     Raptive
 */
class Raptive_Affiliate
{

    /**
     * Define any constants to be used in the plugin.
     *
     * @since    1.0.0
     */
    private function define_constants()
    {
        define('RAPTIVE_AFFILIATE_DIR', plugin_dir_path(dirname(__FILE__)));
        define('RAPTIVE_AFFILIATE_URL', plugin_dir_url(dirname(__FILE__)));
        define('RAPTIVE_KEYWORD_TAXONOMY_NAME', "rapaf_keyword");


        global $wpdb;
        define('RAPTIVE_TERMMETA_AUDITTRAIL_TABLE', $wpdb->prefix . 'termmeta_audit_trail');

    }

    /**
     * Make sure all is set up for the plugin to load.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $this->define_constants();
        $this->load_dependencies();


        $plugin_data = get_plugin_data(plugin_dir_path(dirname(__FILE__)) . 'raptive-affiliate.php');
        $plugin_version = $plugin_data['Version'];

        define('RAPTIVE_AFFILIATE_VERSION', $plugin_version);

        add_action('plugins_loaded', array($this, 'rapaf_init'), 1);
        add_action('admin_notices', array($this, 'admin_notice_required_version'));

        if (is_admin()) {
            //add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        add_action('wp_enqueue_scripts', array($this, 'enqueue_client_scripts'));
    }

    /**
     * Init placeholder
     *
     * @since    
     */
    public function rapaf_init()
    {
        //do_action( 'rapaf_init' );
    }

    /**
     * Load all plugin dependencies.
     *
     * @since    1.0.0
     */
    private function load_dependencies()
    {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        require_once(RAPTIVE_AFFILIATE_DIR . 'includes/class-rapaf-commons.php');

        // Menu
        require_once(RAPTIVE_AFFILIATE_DIR . 'includes/admin/class-rapaf-admin-menu.php');

        require_once(RAPTIVE_AFFILIATE_DIR . 'includes/class-rapaf-analytics.php');

        // if you do not want the root menu to repeat, include the submenu before everything else. 
        require_once(RAPTIVE_AFFILIATE_DIR . 'includes/admin/class-rapaf-settings.php');

        // Admin.
        if (is_admin()) {
            $configs = RAPAF_Commons::get_remote_site_configs(); // NOT USED CURRENTLY but soon
            require_once(RAPTIVE_AFFILIATE_DIR . 'includes/admin/class-rapaf-admin-commons.php');

            require_once(RAPTIVE_AFFILIATE_DIR . 'includes/admin/class-rapaf-recommend-links.php');
            require_once(RAPTIVE_AFFILIATE_DIR . 'includes/admin/class-rapaf-import-links.php');
            require_once(RAPTIVE_AFFILIATE_DIR . 'includes/admin/class-rapaf-rollback.php');

        }
    }


    /**
     * Admin notice to show when the required version is not met.
     * Output the minimum version error message
     */
    function admin_notice_required_version()
    {
        global $wp_version;

        if (version_compare(phpversion(), '5.6', '<') || version_compare($wp_version, '4.6', '<')) {
            //add_action( 'admin_notices', 'rapaf_php_notice' );
            echo '<div class="error"><p>' . __('This affiliate plugin requires PHP 5.6+ and WordPress 4.6+', 'rap-af') . '</p></div>';
            // add_action( 'admin_init', 'adaf_deactivate_self' );
            deactivate_plugins(plugin_basename(RAPTIVE_AFFILIATE_FILE));
        }
    }

    function add_type_attribute($tag, $handle, $src)
    {
        $theme_handles = array(
            // List of script handles that should be loaded with type="module"
            //'global_js',
        );
        // change the script tag by adding type="module" and return it.
        foreach ($theme_handles as $theme_handle) {
            if ($theme_handle === $handle) {
                $tag = '<script src="' . esc_url($src) . '" type="module"></script>';
                return $tag;
            }
        }
        return $tag;
    }

    public function enqueue_admin_scripts()
    {
        //be sure to use the datatables builder to get the right components included in the min.js
        wp_enqueue_script('datatables_js', RAPTIVE_AFFILIATE_URL . 'js/datatables.min.js', array(), RAPTIVE_AFFILIATE_VERSION);

        wp_enqueue_style('datatables', RAPTIVE_AFFILIATE_URL . 'js/datatables.min.css', array(), wp_get_theme()->get('Version'), 'all');
        wp_enqueue_style('adaf', RAPTIVE_AFFILIATE_URL . 'dist/css/rapaf.css', array(), wp_get_theme()->get('Version'), 'all');

        wp_enqueue_script('global_js', RAPTIVE_AFFILIATE_URL . 'js/global.js', array(), RAPTIVE_AFFILIATE_VERSION);
        add_filter('script_loader_tag', array($this, 'add_type_attribute'), 10, 3);
    }

    public function enqueue_client_scripts()
    {
        require plugin_dir_path(__FILE__) . '../partials/insert-affiliate-js.php';

    }
}
