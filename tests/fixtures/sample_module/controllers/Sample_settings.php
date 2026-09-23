<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sample_settings extends AdminController
{
    public function save()
    {
        if (!staff_can('edit', 'sample_module')) {
            set_alert('danger', _l('access_denied'));

            return false;
        }

        update_option('sample_name', $this->input->post('name'));
        set_alert('success', _l('settings_updated'));

        return true;
    }
}
