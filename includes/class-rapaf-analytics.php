<?php

/**
 * Common backend functions
 *
 * 
 * 
 */
class RAPAF_Analytics
{
    public static $client;

    public static function get_env()
    {
        $site_url = get_site_url();
        $site_domain = parse_url($site_url, PHP_URL_HOST);

        if (strpos($site_domain, '.qa.') !== false || strpos($site_domain, 'localhost') !== false) {
            return 'dev';
        } else {
            return 'prod';
        }
    }

    public static function get_kinesis_api()
    {
        $str = self::get_env() == 'dev' ? '.development' : '';
        return 'https://affiliate-api' . $str . '.raptive.com/v1/create/event';
    }

    public static function get_kinesis_stream()
    {
        $str = self::get_env() == 'dev' ? 'development' : 'production';
        return 'apeng-event-stream-' . $str;
    }

    public static function get_meta_info()
    {
        $plugin_data = get_plugin_data(plugin_dir_path(__FILE__) . '../raptive-affiliate.php');
        //print_r($plugin_data);
        $plugin_version = $plugin_data['Version'];
        $site_url = get_site_url();
        $site_domain = parse_url($site_url, PHP_URL_HOST);
        $current_user = wp_get_current_user();
        $user_email = esc_html($current_user->user_email);
        return array(
            'plugin_version' => $plugin_version,
            'site_domain' => $site_domain,
            'user' => $user_email
        );
    }

    public static function send_event($event_type, $event_data)
    {
        $meta_data = self::get_meta_info();

        $event_data_combined = array_unique(array_merge($meta_data, $event_data));
        $data = array(
            'type' => $event_type,
            'id' => uniqid(),
            'timestamp' => time(),
            'features' => array('affiliate-plugin'),
            'data' => $event_data_combined
        );
        $json_data = json_encode($data);
        // Encode the JSON data in Base64
        $base64_data = base64_encode($json_data);

        // Prepare the final record with the Base64 encoded data
        $record = array(
            'DeliveryStreamName' => self::get_kinesis_stream(),
            'Records' => array(array('Data' => $base64_data))
        );
        // Convert the record to JSON
        $json_record = json_encode($record);

        // Create a new cURL resource
        $curl = curl_init();

        // Set the cURL options
        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => self::get_kinesis_api(),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json_record,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json_record)
                )
            )
        );
        // Execute the cURL request
        $response = curl_exec($curl);

        // Check for errors
        if (curl_errno($curl)) {
            $error = curl_error($curl);
            // Handle the error accordingly
            echo "Error sending plugin analytics. Please contact Raptive Support";
        } else {
            // Process the response
            // echo "Response: " . $response;
        }

        // Close the cURL resource
        curl_close($curl);
    }

    public static function send_plugin_activation_analytics()
    {
        self::send_event('plugin_activation', array());
    }

    public static function send_plugin_uninstall_analytics()
    {
        self::send_event('plugin_uninstall', array());
    }

    public static function send_settings_change_event_analytics($settings_name, $settings_value)
    {

        $meta_data = self::get_meta_info();
        //print('settings change' . $settings_name . '' . $settings_value . '');
        $settings_value_str = $settings_value ? $settings_value : "off";
        self::send_event(
            'settings_change',
            array(
                'settings_name' => $settings_name,
                'settings_value' => $settings_value_str
            )
        );
    }

    public static function send_affiliate_link_insertion_analytics($data)
    {

        $meta_data = self::get_meta_info();
        self::send_event('link_insertion', $data);
    }

    public static function send_affiliate_ingredient_match_analytics($data)
    {

        $meta_data = self::get_meta_info();
        self::send_event('ingredient_match', $data);
    }


}
