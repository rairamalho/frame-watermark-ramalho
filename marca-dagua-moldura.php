<?php
/**
 * Plugin Name: Marca d’água e Moldura por Post
 * Description: Aplica marca d’água ou moldura em imagens anexadas a posts usando ACF.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

define('MW_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MW_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once MW_PLUGIN_PATH . 'includes/class-plugin.php';

add_action('after_setup_theme', function () {
    add_image_size('ft-vertical', 1080, 1920, true);
});

add_action('plugins_loaded', function () {
    MW_Plugin::init();
});
