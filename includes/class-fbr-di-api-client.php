<?php

if (!defined('ABSPATH')) {
    exit;
}

class FBR_DI_API_Client {

    private $base_url;
    private $api_key;
    private $environment;

    public function __construct() {
        $this->base_url = rtrim(get_option('fbr_di_base_url', 'https://ebcdisc.com/api'), '/');
        $this->api_key = get_option('fbr_di_api_key', '');
        $this->environment = get_option('fbr_di_environment', 'sandbox');
    }

    public function is_configured() {
        return !empty($this->api_key) && !empty($this->base_url);
    }

    public function is_sandbox() {
        return strtolower($this->environment) === 'sandbox';
    }

    private function get_headers() {
        return array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'User-Agent'    => 'WooCommerce-FbrDigitalInvoicing/' . FBR_DI_VERSION . '; ' . home_url(),
        );
    }

    public function ping() {
        $url = $this->base_url . '/ping';
        $response = wp_remote_get($url, array(
            'headers' => $this->get_headers(),
            'timeout' => 15,
        ));

        return $this->handle_response($response);
    }

    public function get_client_profile() {
        $url = $this->base_url . '/client-profile';
        $response = wp_remote_get($url, array(
            'headers' => $this->get_headers(),
            'timeout' => 15,
        ));

        return $this->handle_response($response);
    }

    public function submit_invoice($payload) {
        $endpoint = $this->is_sandbox() ? '/postinvoicedata_sb' : '/postinvoicedata';
        $url = $this->base_url . $endpoint;

        $response = wp_remote_post($url, array(
            'headers' => $this->get_headers(),
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ));

        return $this->handle_response($response);
    }

    public function verify_taxpayer($ntn_cnic) {
        $url = add_query_arg('ntn', $ntn_cnic, $this->base_url . '/statl');
        $response = wp_remote_get($url, array(
            'headers' => $this->get_headers(),
            'timeout' => 15,
        ));

        return $this->handle_response($response);
    }

    private function handle_response($response) {
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'code'    => 500,
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body)) {
            $body = array(
                'message' => wp_remote_retrieve_body($response),
            );
        }

        $is_success = ($code >= 200 && $code < 300) && (($body['status'] ?? '') !== 'ERROR');

        return array(
            'success' => $is_success,
            'code'    => $code,
            'data'    => $body,
            'message' => $body['message'] ?? ($body['error'] ?? ($is_success ? 'Success' : 'API Error')),
            'errors'  => $body['errors'] ?? ($body['validation_errors'] ?? array()),
        );
    }
}
