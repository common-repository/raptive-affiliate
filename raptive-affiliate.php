<?php

/**
 * Plugin Name:       Raptive Affiliate
 * Description:       Participate in Raptive's Affiliate Platform
 * Requires at least: 4.6
 * Requires PHP:      5.6
 * Version:           1.1.6
 * Author:            Raptive
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       raptive-affiliate
 *
 * @package           Raptive_Affiliate
 */

defined('ABSPATH') || die;



/**
 * The code that runs during plugin activation.
 */
function activate_raptive_affiliate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-rapaf-activator.php';
	RAPAF_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_raptive_affiliate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-rapaf-deactivator.php';
	RAPAF_Deactivator::deactivate();
}

/**
 * The code that runs during plugin uninstall.
 */
function uninstall_raptive_affiliate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-rapaf-uninstaller.php';
	RAPAF_Uninstaller::uninstall();
}

register_activation_hook(__FILE__, 'activate_raptive_affiliate');
register_deactivation_hook(__FILE__, 'deactivate_raptive_affiliate');
register_uninstall_hook(__FILE__, 'uninstall_raptive_affiliate');


/**
 * The core plugin class that is used to define admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-raptive-affiliate.php';


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_raptive_affiliate()
{
	$plugin = new Raptive_Affiliate();
}
run_raptive_affiliate();
