<?php

/**
 * 
 * 
 */

class RAPAF_Admin_Menu {
    /**
     * Base64 encoded svg menu icon.
     *
     * @since    7.2.0
     * @access   private
     * @var      string    $icon    Base64 encoded svg menu icon.
     */
    //private static $icon = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIgd2lkdGg9IjI0cHgiIGhlaWdodD0iMjRweCIgdmlld0JveD0iMCAwIDI0IDI0Ij48ZyA+DQo8cGF0aCBmaWxsPSIjZmZmZmZmIiBkPSJNMTAsMEM5LjQsMCw5LDAuNCw5LDF2NEg3VjFjMC0wLjYtMC40LTEtMS0xUzUsMC40LDUsMXY0SDNWMWMwLTAuNi0wLjQtMS0xLTFTMSwwLjQsMSwxdjhjMCwxLjcsMS4zLDMsMywzDQp2MTBjMCwxLjEsMC45LDIsMiwyczItMC45LDItMlYxMmMxLjcsMCwzLTEuMywzLTNWMUMxMSwwLjQsMTAuNiwwLDEwLDB6Ii8+DQo8cGF0aCBkYXRhLWNvbG9yPSJjb2xvci0yIiBmaWxsPSIjZmZmZmZmIiBkPSJNMTksMGMtMy4zLDAtNiwyLjctNiw2djljMCwwLjYsMC40LDEsMSwxaDJ2NmMwLDEuMSwwLjksMiwyLDJzMi0wLjksMi0yVjENCkMyMCwwLjQsMTkuNiwwLDE5LDB6Ii8+DQo8L2c+PC9zdmc+';

    /**
     * Register actions and filters.
     *
     * @since    1.0.0
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
    }
    public static $default_page = 'link_monetizer';

    /**
     * Add WPRM to the wordpress menu.
     *
     * @since    1.0.0
     * Wordpress roles: https://wordpress.org/documentation/article/roles-and-capabilities/
     */
    public static function add_menu_page() {
        $default_class = 'RAPAF_Settings';
        $default_init = 'settings_init';

        add_menu_page( 
            'Raptive Affiliate', 
            'Raptive Affiliate', 
            'manage_options',
            self::$default_page,
            array($default_class, $default_init)
        );
        // remove_submenu_page('rapaf_main', 'rapaf_link_inserter');
    }
}
RAPAF_Admin_Menu::init();
