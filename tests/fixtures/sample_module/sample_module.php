<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Sample Module (fixture do perfex-module-testkit)
*/

hooks()->add_action('after_invoice_added', 'sample_module_after_invoice_added');
hooks()->add_filter('sample_invoice_label', 'sample_module_invoice_label', 10, 2);

if (!function_exists('sample_module_after_invoice_added')) {
    function sample_module_after_invoice_added($invoice_id)
    {
        update_option('sample_last_invoice', $invoice_id);
        log_activity('[Sample] Fatura adicionada #' . $invoice_id);
    }
}

if (!function_exists('sample_module_invoice_label')) {
    function sample_module_invoice_label($label, $invoice_id)
    {
        return $label . ' #' . $invoice_id;
    }
}
