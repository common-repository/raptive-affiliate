<?php


/**
 * Fired during plugin uninstall.
 *
 * This class defines all code necessary to run during the plugin's uninstall process.
 *
 */
class RAPAF_Uninstaller {

	/**
	 * Execute this on uninstall of the plugin.
	 *
	 * @since    1.0.0
	 */
	public static function uninstall() {
		$wordpress_options = RAPAF_Settings::get_settings_options();

		foreach ($wordpress_options as $option) {
			delete_option($option);
		}
		RAPAF_Analytics::send_plugin_uninstall_analytics();
	}
}
