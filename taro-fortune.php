<?php
/**
 * Plugin Name: YesOrNo
 * Description: Card-based Yes/No test engine with animated interactions and WordPress-managed test data.
 * Version: 1.3.14
 * Author: KoreaHi
 * Text Domain: yesorno
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YESORNO_VERSION', '1.3.14');
define('YESORNO_FILE', __FILE__);
define('YESORNO_DIR', plugin_dir_path(__FILE__));
define('YESORNO_URL', plugin_dir_url(__FILE__));
define('YESORNO_DATA_DIR', YESORNO_DIR . 'data/');

require_once YESORNO_DIR . 'includes/class-taro-fortune-data-store.php';
require_once YESORNO_DIR . 'includes/class-taro-fortune-admin.php';
require_once YESORNO_DIR . 'includes/class-taro-fortune-frontend.php';
require_once YESORNO_DIR . 'includes/class-taro-fortune.php';

if (!function_exists('yesorno_bootstrap')) {
    function yesorno_bootstrap() {
        $plugin = new YesOrNo_Plugin();
        $plugin->run();
    }
}

yesorno_bootstrap();
