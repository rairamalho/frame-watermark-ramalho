<?php

if (!defined('ABSPATH')) exit;

class MW_Plugin
{
  const IMG_WIDTH  = 1080;
  const IMG_HEIGHT = 1920;
  const POST_TYPE = 'post';

  public static function init()
  {
    self::includes();
  }

  private static function includes()
  {
    require_once MW_PLUGIN_PATH . 'includes/AcfFields.php';
    require_once MW_PLUGIN_PATH . 'includes/class-image-processor.php';
    require_once MW_PLUGIN_PATH . 'includes/class-upload-galeria.php';

    self::hooks();
  }

  private static function hooks()
  {
    add_action('acf/init', ['PMD\AcfFields', 'register']);
    add_action('init', function () {
      new Upload_Galeria();
    });

    add_filter(
      'wp_generate_attachment_metadata',
      ['Image_Processor', 'process_ft_vertical'],
      10,
      2
    );
  }
}
