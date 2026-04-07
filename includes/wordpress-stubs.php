<?php
/**
 * WordPress Core Function Stubs for IDE Support
 * 
 * This file provides type hints for WordPress core functions
 * to help IDE editors recognize WordPress APIs.
 * 
 * @codeCoverageIgnore
 */

if (!function_exists('plugin_dir_path')) {
    /**
     * Get the filesystem directory path for a plugin.
     *
     * @param string|false $file The filename of the plugin (__FILE__ most of the time).
     * @return string The filesystem path of the plugin's directory.
     */
    function plugin_dir_path($file) {
        return '';
    }
}

if (!function_exists('plugin_dir_url')) {
    /**
     * Get the URL directory path for a plugin.
     *
     * @param string|false $file The filename of the plugin (__FILE__ most of the time).
     * @return string The URL path of the plugin's directory.
     */
    function plugin_dir_url($file) {
        return '';
    }
}

if (!function_exists('wp_die')) {
    /**
     * Die, with optional message and status code.
     *
     * @param string|WP_Error $message Optional. Error message. Default empty.
     * @param string|int      $title   Optional. Error title. Default empty.
     * @param string|array    $args    Optional. Arguments. Default empty array.
     * @return void
     */
    function wp_die($message = '', $title = '', $args = array()) {
    }
}

if (!function_exists('wp_enqueue_script')) {
    /**
     * Enqueue a script.
     *
     * @param string           $handle    Name used as a handle for the script.
     * @param string           $src       The URL of the script.
     * @param string[]         $deps      Optional. An array of registered script handles this script depends on.
     * @param string|bool|null $ver       Optional. String specifying script version number.
     * @param bool             $in_footer Optional. Whether to enqueue the script before closing body tag.
     * @return void
     */
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
    }
}

if (!function_exists('wp_enqueue_style')) {
    /**
     * Enqueue a CSS stylesheet.
     *
     * @param string       $handle   Name of the stylesheet.
     * @param string       $src      The URL of the stylesheet.
     * @param string[]     $deps     Optional. An array of registered stylesheet handles this stylesheet depends on.
     * @param string|bool|null $ver  Optional. String specifying stylesheet version number.
     * @param string       $media    Optional. The media for which this stylesheet has been defined.
     * @return void
     */
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
    }
}

if (!function_exists('wp_localize_script')) {
    /**
     * Localize a script.
     *
     * @param string $handle      Script handle the data will be attached to.
     * @param string $object_name Name for the JavaScript object.
     * @param array  $l10n        The data itself.
     * @return true
     */
    function wp_localize_script($handle, $object_name, $l10n = array()) {
        return true;
    }
}

if (!function_exists('wp_json_encode')) {
    /**
     * Encode a variable into JSON.
     *
     * @param mixed $data    Variable to encode.
     * @param int   $options Optional. Bitmask of JSON_* constants.
     * @param int   $depth   Optional. Maximum depth.
     * @return string|false JSON-encoded string, or false if it cannot be encoded.
     */
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('add_action')) {
    /**
     * Hooks a function on to a specific action.
     *
     * @param string   $hook             The name of the action to which the $function_to_add is hooked.
     * @param callable $function_to_add  The name of the function you wish to be called.
     * @param int      $priority         Optional. Used to specify the order in which the functions are executed.
     * @param int      $accepted_args    Optional. The number of arguments the function accepts.
     * @return true
     */
    function add_action($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    /**
     * Hooks a function or method to a specific filter action.
     *
     * @param string   $hook             The name of the filter to hook the $function_to_add to.
     * @param callable $function_to_add  The name of the function to be called when the filter is applied.
     * @param int      $priority         Optional. Used to specify the order in which the functions are executed.
     * @param int      $accepted_args    Optional. The number of arguments the function accepts.
     * @return true
     */
    function add_filter($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}
