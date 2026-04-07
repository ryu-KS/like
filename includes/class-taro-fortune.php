<?php

if (!defined('ABSPATH')) {
    exit;
}

class YesOrNo_Plugin {
    /** @var YesOrNo_Data_Store */
    private $data_store;

    /** @var YesOrNo_Admin */
    private $admin;

    /** @var YesOrNo_Frontend */
    private $frontend;

    public function __construct() {
        $this->data_store = new YesOrNo_Data_Store();
        $this->admin = new YesOrNo_Admin($this->data_store);
        $this->frontend = new YesOrNo_Frontend($this->data_store);
    }

    public function run() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('init', array($this->data_store, 'bootstrap'));

        $this->admin->register();
        $this->frontend->register();
    }

    public function load_textdomain() {
        load_plugin_textdomain('yesorno', false, dirname(plugin_basename(YESORNO_FILE)) . '/languages');
    }
}
