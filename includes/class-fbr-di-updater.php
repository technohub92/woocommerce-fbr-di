<?php

if (!defined('ABSPATH')) {
    exit;
}

class FBR_DI_Updater {

    private $plugin_slug;
    private $version;
    private $api_url;

    public function __construct() {
        $this->plugin_slug = plugin_basename(FBR_DI_PLUGIN_FILE);
        $this->version = FBR_DI_VERSION;
        $this->api_url = rtrim(get_option('fbr_di_base_url', ''), '/');

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_plugin_update'));
        add_filter('plugins_api', array($this, 'plugin_api_call'), 20, 3);
    }

    public function check_for_plugin_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_version_info = $this->get_remote_version();
        if ($remote_version_info && version_compare($this->version, $remote_version_info->new_version, '<')) {
            $transient->response[$this->plugin_slug] = $remote_version_info;
        }

        return $transient;
    }

    public function plugin_api_call($def, $action, $args) {
        if (!isset($args->slug) || $args->slug !== 'fbr-digital-invoicing-woocommerce') {
            return $def;
        }

        $remote_info = $this->get_remote_version();
        if ($remote_info) {
            return $remote_info;
        }

        return $def;
    }

    private function get_remote_version() {
        $url = $this->api_url . '/ping';
        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            return false;
        }

        $latest_version = wp_remote_retrieve_header($response, 'x-fbr-plugin-latest-version');
        if (empty($latest_version)) {
            $latest_version = '1.0.0';
        }

        $download_url = str_replace('/api', '', $this->api_url) . '/docs/download/woocommerce-plugin';

        $obj = new \stdClass();
        $obj->slug = 'fbr-digital-invoicing-woocommerce';
        $obj->new_version = $latest_version;
        $obj->url = str_replace('/api', '', $this->api_url) . '/docs/api';
        $obj->package = $download_url;
        $obj->tested = '9.0';
        $obj->requires_php = '7.4';

        return $obj;
    }
}

new FBR_DI_Updater();
