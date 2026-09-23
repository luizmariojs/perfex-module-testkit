<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('sample_greeting')) {
    function sample_greeting($name)
    {
        return _l('sample_hello_%s', $name);
    }
}
