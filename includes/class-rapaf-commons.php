<?php


/**
 * Common backend functions
 *
 * @since      
 * 
 */


class RAPAF_Commons
{
    const API_AUTH_KEY_NAME = 'x-api-key';
    const API_AUTH_KEY_VALUE = 'ox8IWkcay_3D';

    public static function register_taxonomy()
    {
        if (!taxonomy_exists(RAPTIVE_KEYWORD_TAXONOMY_NAME)) {
            //add_action( 'init', 'create_new_taxonomy' );
            register_taxonomy(
                RAPTIVE_KEYWORD_TAXONOMY_NAME,
                'post',
                array(
                    'label' => __('Raptive Keyword'),
                    'rewrite' => array('slug' => 'raptive_keyword'),
                    'public' => false,
                    'query_var' => false,
                )
            );
        }
    }

    public static function get_affiliate_settings_object()
    {
        $settings = array(
            'enableLinkMonetizer' => get_option('link_monetizer_option') === 'on' ? true : false,
            'keywordLinkerKeywordLimit' => get_option('keyword_limit_option'),
            'affiliateJsClientPath' => get_option('affiliate_js_client_path'),
            'affiliateApiPath' => get_option('affiliate_api_path'),
            'amazonAffiliateId' => get_option('amazon_affiliate_id'),
            // additional default settings
            'excludeNetworks' => ['raptive'],
            'excludeDestinations' => ['cj'],
            'enableAnalytics' => true,
        );

        return $settings;
    }

    public static function get_keyword_links()
    {
        self::register_taxonomy();

        $terms = get_terms(
            array(
                'taxonomy' => RAPTIVE_KEYWORD_TAXONOMY_NAME,
                'hide_empty' => false,
            ));
        $rows = array();
        foreach ($terms as $term) {
            $link = get_term_meta($term->term_id, 'rapaf_keyword_link', true); //return single value
            $rows[] = array('name' => $term->name, 'link' => $link);
        }
        return $rows;
    }

    public static function get_remote_site_configs()
    {
        $settingsObj = self::get_affiliate_settings_object();
        $affiliateApiPath = $settingsObj['affiliateApiPath'];

        $url = $affiliateApiPath . '/v1/get/site_configs?domain_name=' . $_SERVER['SERVER_NAME'];

        $headers = array(self::API_AUTH_KEY_NAME => self::API_AUTH_KEY_VALUE);
        $request = wp_remote_get($url, array('headers' => $headers));

        if (is_wp_error($request)) {
            return array();
        }

        $body = wp_remote_retrieve_body($request);
        $json_response = json_decode($body, true);
        return $json_response;
    }

    public static function is_keywordlinker_enabled($configs)
    {
        $keywordLinkerEnabled = false;

        // 1/4/2024 disable this for LM release
        // if (!empty($configs)) {
        //     $feature_flags = $configs['featureFlags'];
        //     if ($feature_flags && $feature_flags['enableKeywordLinker']) {
        //         $keywordLinkerEnabled = true;
        //     }
        // }
        return $keywordLinkerEnabled;
    }

}
