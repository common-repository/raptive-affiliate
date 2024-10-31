<?php

/**
 * This view is responsible for adding JS that will insert a <script> tag
 * that pulls the primary javascript down from our S3 bucket / CDN
 * 
 * Parameters:
 * raptive_dev (optional) - this forces the script to be loaded from the development CDN with a cachebuster
 * 
 * Note: We want to load the JS from the CDN asynchronously for performance reasons 
 * and also because we have found that `wp_enqueue_scripts` does not work reliably for
 * many of our customers. Note that the script here is built from the same file in the plugin repository.
 * 
 * 
 */


$settingsObj = RAPAF_Commons::get_affiliate_settings_object();

$plugin_data = get_plugin_data(plugin_dir_path(__FILE__) . '../raptive-affiliate.php');
$settingsObj['pluginVersion'] = $plugin_data['Version'];


// Disabling remote site config fetching till we have that behind CloudFront
//$remoteSiteConfigs = RAPAF_Commons::get_remote_site_configs();  

// Disable all keyword linker feature for now. 
// $keywordLinkerEnabled = RAPAF_Commons::is_keywordlinker_enabled($remoteSiteConfigs); 
// if ($keywordLinkerEnabled) {
// 	$settingsObj['enableKeywordLinker'] = true;
// 	$keywordLinkerKeywordLimit = $settingsObj['keywordLinkerKeywordLimit'];
// 	$rows = RAPAF_Commons::get_keyword_links();
// 	$settingsObj['keywordConfigs'] = [];
// 	foreach ($rows as $row) {
// 		$settingsObj['keywordConfigs'][] =
// 			array(
// 				'keyword' => $row['name'],
// 				'caseSensitive' => false,
// 				'href' => $row['link'],
// 				'exactOnly' => false,
// 				'occurrences' => $keywordLinkerKeywordLimit,
// 			);
// 	}
// }

$url = $_SERVER['REQUEST_URI'];
$query = parse_url($url, PHP_URL_QUERY);
$affiliate_script_url = $settingsObj['affiliateJsClientPath'];
if ($query) {
	parse_str($query, $query_params);
	if (isset($query_params['raptive_dev'])) {
		$cachebuster = '?cachebuster=' . time();
		$affiliate_script_url .= $cachebuster;
	}
}

$affiliate_script_config = json_encode($settingsObj, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

echo '<script data-affiliate-config type="application/json">' . $affiliate_script_config . '</script>';
?>

<script async referrerpolicy="no-referrer-when-downgrade" data-no-optimize="1" data-cfasync="false" src="<?php echo $affiliate_script_url; ?>">
</script>
