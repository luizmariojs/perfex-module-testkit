<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sample_webhook extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('sample_module/sample_model');
    }

    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->respond(405, 'Method Not Allowed');
        }

        $token = get_option('sample_webhook_token');
        if ($token !== '' && !hash_equals($token, $_SERVER['HTTP_X_SAMPLE_TOKEN'] ?? '')) {
            return $this->respond(401, 'Unauthorized');
        }

        $body = json_decode(file_get_contents('php://input'));
        if (!$body || empty($body->event)) {
            return $this->respond(400, 'Bad Request');
        }

        if ($body->event === 'RECORD_DONE') {
            $this->sample_model->set_status($body->invoice_id, 'done');
        }

        return $this->respond(200, 'OK');
    }

    private function respond($code, $message)
    {
        http_response_code($code);
        echo $message;
    }
}
