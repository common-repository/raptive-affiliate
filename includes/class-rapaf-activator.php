<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 * @package    Raptive_Affiliate
 * @subpackage Raptive_Affiliate/includes
 */

class RAPAF_Activator
{

    /**
     * Execute this on activation of the plugin.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        self::rapaf_create_audit_trail_table();
        RAPAF_Analytics::send_plugin_activation_analytics();
    }

    /**
     * Create audit trail table
     */
    public static function rapaf_create_audit_trail_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = RAPTIVE_TERMMETA_AUDITTRAIL_TABLE;
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        audit_trail_id bigint(20) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        term_id bigint(20) NOT NULL,
        batch_id varchar(255) NOT NULL,
        meta_key varchar(255) DEFAULT '' NULL,
        meta_value_old text NULL,
        meta_value_new text NULL,
        rolled_back tinyint(1) DEFAULT NULL,

        PRIMARY KEY  (audit_trail_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        $is_error = empty($wpdb->last_error);
    }
}
