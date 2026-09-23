<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sample_model extends App_Model
{
    public function get_record($invoice_id)
    {
        $this->db->where('invoice_id', $invoice_id);

        return $this->db->get(db_prefix() . 'sample_records')->row();
    }

    public function set_status($invoice_id, $status)
    {
        $this->db->where('invoice_id', $invoice_id);
        $this->db->update(db_prefix() . 'sample_records', ['status' => $status]);

        return $this->db->affected_rows();
    }

    public function create($invoice_id, $status = 'pending')
    {
        $this->db->insert(db_prefix() . 'sample_records', ['invoice_id' => $invoice_id, 'status' => $status]);

        return $this->db->insert_id();
    }

    public function totals_by_status()
    {
        return $this->db->query('SELECT status, COUNT(*) AS total FROM ' . db_prefix() . 'sample_records GROUP BY status')->result_array();
    }
}
