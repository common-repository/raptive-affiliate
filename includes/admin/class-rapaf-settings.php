<?php

/**
 * Handle the Settings page.
 *
 * @since      
 * 
 */
class RAPAF_Settings
{
    private static $settings_options = array(
        'link_monetizer_option' => false,
        // 'dynamic_links_option' => false,
        'keyword_limit_option' => 3,
        'affiliate_js_client_path' => 'https://affiliate-cdn.raptive.com/affiliate.mvp.min.js',
        'affiliate_api_path' => 'https://affiliate-api.raptive.com',
        'amazon_affiliate_id' => '',
    );

    private static $settings_options_keys = [];


    public static function init()
    {
        self::$settings_options_keys = array_keys(self::$settings_options);

        // register settings options , don't do it with add_action('admin_init',  array(__CLASS__, 'add_settings_options'));. 
        // It'll not register the options for non admin pages
        self::add_settings_options();

        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));

        add_action('update_option_link_monetizer_option', array(__CLASS__, 'fire_settings_changed_event_analytics'), 10, 3);
        add_action('add_option_link_monetizer_option', array(__CLASS__, 'fire_settings_added_event_analytics'), 10, 3);

        add_action('update_option_amazon_affiliate_id', array(__CLASS__, 'fire_settings_changed_event_analytics'), 10, 3);
        add_action('add_option_amazon_affiliate_id', array(__CLASS__, 'fire_settings_added_event_analytics'), 10, 3);

    }


    /**
     * Add the submenu to the menu and initialize settings store.
     *
     * @since	5.0.0
     */
    public static function add_settings_page()
    {
        // add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

        // if you don't want the root menu to repeat, include the submenu before everything else.
        add_submenu_page(
            RAPAF_Admin_Menu::$default_page,
            'Link Monetizer',
            'Link Monetizer',
            'manage_options',
            RAPAF_Admin_Menu::$default_page, //this needs to be same as main menu slug 
            array(__CLASS__, 'settings_init')
        );


    }


    // Fired the first time a setting is "changed"
    public static function fire_settings_added_event_analytics($option_name, $value)
    {
        set_transient($option_name . '_updated', $value, 60);
    }

    // Fired on any subsequent changes.  For analytics purposes, these "updates" should be treated equivalently.
    public static function fire_settings_changed_event_analytics($old_value, $new_value, $option_name)
    {
        set_transient($option_name . '_updated', $new_value, 60);
    }



    public static function get_settings_options()
    {
        return self::$settings_options_keys;
    }


    public static function add_settings_options()
    {
        foreach (self::$settings_options_keys as $option) {
            register_setting(
                'rapaf_settings_group',
                $option,
                array('default' => self::$settings_options[$option])
            );
        }
    }


    public static function settings_init()
    {
        RAPAF_ADMIN_Commons::print_plugin_title();
        self::settings_form();
    }

    public static function settings_form()
    {
        $settingsObj = RAPAF_Commons::get_affiliate_settings_object();

        $link_monetizer_option = $settingsObj['enableLinkMonetizer'];
        // $dynamic_links_option = get_option('dynamic_links_option') === 'on' ? true : false;
        $keyword_limit_option = $settingsObj['keywordLinkerKeywordLimit'];
        $affiliate_js_client_path = $settingsObj['affiliateJsClientPath'];
        $affiliate_api_path = $settingsObj['affiliateApiPath'];
        $amazon_affiliate_id = $settingsObj['amazonAffiliateId'];

        $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $query_params);
            if (isset($query_params['raptive_dev'])) {
                $params_raptive_dev = true;
            }
            if (isset($query_params['advanced_settings'])) {
                $params_advanced_settings = true;
            }
        }


        foreach (self::$settings_options_keys as $option) {
            $new_value = get_transient($option . '_updated');

            // if no changes, type is boolean, otherwise even checkbox will return string type 
            if (gettype($new_value) === 'string') {
                RAPAF_Analytics::send_settings_change_event_analytics($option, $new_value);
                delete_transient($option . '_updated');
            }
        }
        ?>
        <br>
        <h2>Link Monetizer</h2>
        <form method="post" action="options.php">
            <?php settings_fields('rapaf_settings_group'); ?>
            <?php do_settings_sections('rapaf_settings_group'); ?>
            When enabled, Raptive will convert any existing product link for retailers in our network with an affiliate link.
            <br>

            You must sign Raptive's Affiliate Terms of Service Addendum before running this feature. Reach out to
            <a href="https://raptive.com/contact">Raptive Support</a> for more details.

            <table class="form-table">
                <tr valign="top">

                    <td>
                        <label>
                            <input type="checkbox" id="link-monetizer-option" name="link_monetizer_option" <?php checked($link_monetizer_option, true); ?> />
                        </label>
                        <?php echo __('Enable Link Monetizer.', 'textdomain'); ?>

                    </td>
                </tr>
                <!-- Commenting out keyword linker for Link Monetizer release -->
                <!-- 
                <tr valign="top">
                   
                    <td>
                        <label>
                            <input type="text" size=4 id="keyword-linker-keyword-limit-option" name="keyword_limit_option" value="<?php echo $keyword_limit_option; ?>" />
                        </label>
                            <?php echo __('How many occurances of each keyword should be affiliatized on a single page', 'textdomain'); ?>
                      
                    </td>
                </tr>
                -->

            </table>
            <?php
            // NOTE: In non-dev mode, the settings not included in the form will be wiped out. So we hide them.
            if (isset($params_advanced_settings)) {
                $display = 'block';
            } else {
                $display = 'none';
            }
            ?>
            <div id="dev-settings" style="display:<?php echo $display; ?>">
                <br><br>
                <h2>Advanced Settings</h2>
                <p>Please do not modify the settings here unless instructed as this can impact your affiliate earnings.</p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php echo __('Secondary Affiliate ID', 'textdomain'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="text" size=50 id="amazon_affiliate_id" name="amazon_affiliate_id"
                                    value="<?php echo $amazon_affiliate_id; ?>" />
                            </label>

                        </td>
                    </tr>
                </table>
            </div>
            <?php
            if (isset($params_raptive_dev)) {
                $display = 'block';
            } else {
                $display = 'none';
            }
            ?>
            <div id="dev-settings" style="display:<?php echo $display; ?>">
                <br><br>
                <h2>Development Settings</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php echo __('Affiliate client js path', 'textdomain'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="text" size=50 id="affiliate-js-client-path" name="affiliate_js_client_path"
                                    value="<?php echo $affiliate_js_client_path; ?>" />
                            </label>

                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php echo __('Affiliate API path with no slash', 'textdomain'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="text" size=50 id="affiliate-api-path" name="affiliate_api_path"
                                    value="<?php echo $affiliate_api_path; ?>" />
                            </label>

                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(); ?>
        </form>
        <?php

    }

}


RAPAF_Settings::init();
