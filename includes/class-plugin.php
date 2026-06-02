<?php
/**
 * Main plugin bootstrap class.
 *
 * Loads all dependencies and registers WordPress hooks.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FWR_Plugin
 *
 * Entry point for the Frame Watermark plugin.
 * Call FWR_Plugin::init() on the plugins_loaded hook.
 */
class FWR_Plugin {

	/** @var string The post type this plugin operates on. */
	const POST_TYPE = 'post';

	/**
	 * Initialises the plugin by loading files and registering hooks.
	 */
	public static function init(): void {
		self::load_dependencies();
		self::register_hooks();
	}

	/**
	 * Requires all class files needed by the plugin.
	 */
	private static function load_dependencies(): void {
		require_once FWR_PLUGIN_PATH . 'includes/class-acf-fields.php';
		require_once FWR_PLUGIN_PATH . 'includes/class-image-processor.php';
		require_once FWR_PLUGIN_PATH . 'includes/class-upload-handler.php';
	}

	/**
	 * Registers all WordPress action and filter hooks.
	 */
	private static function register_hooks(): void {
		add_action( 'acf/init', array( 'FWR_ACF_Fields', 'register' ) );

		add_action(
			'init',
			static function () {
				new FWR_Upload_Handler();
			}
		);

		add_filter(
			'wp_generate_attachment_metadata',
			array( 'FWR_Image_Processor', 'process_ft_vertical' ),
			10,
			2
		);
	}
}
