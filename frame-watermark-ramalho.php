<?php
/**
 * Plugin Name:       Frame Watermark - Ramalho
 * Plugin URI:        https://github.com/ramalho/frame-watermark-ramalho
 * Description:       Applies watermark or frame effects to post images using ACF fields.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Ramalho
 * Author URI:        https://github.com/rairamalho
 * Text Domain:       frame-watermark
 */

defined( 'ABSPATH' ) || exit;

define( 'FWR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'FWR_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'FWR_IMG_WIDTH',   1080 );
define( 'FWR_IMG_HEIGHT',  1920 );

require_once FWR_PLUGIN_PATH . 'includes/class-plugin.php';

add_action(
	'after_setup_theme',
	static function () {
		add_image_size( 'ft-vertical', FWR_IMG_WIDTH, FWR_IMG_HEIGHT, true );
	}
);

add_action(
	'plugins_loaded',
	static function () {
		FWR_Plugin::init();
	}
);
