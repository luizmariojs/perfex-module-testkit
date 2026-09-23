<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Docs;

use PerfexTestkit\Http\RecordingTransport;
use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\Testkit;

/**
 * Exemplos do README.md, copiados literalmente (apenas os nomes dos métodos em camelCase de teste).
 * Se este teste quebrar, o README está desatualizado.
 */
final class ReadmeExamplesTest extends PerfexTestCase
{
    // --- Model com banco falso ---------------------------------------------------------------------

    public function test_muda_status(): void
    {
        Testkit::db()->table('tblsample_records', ['id', 'invoice_id', 'status'], [
            ['id' => 1, 'invoice_id' => 10, 'status' => 'pending'],
        ]);

        $CI = &get_instance();
        $CI->load->model('sample_module/sample_model');
        $affected = $CI->sample_model->set_status(10, 'done');

        $this->assertSame(1, $affected);
        $this->assertRowExists('tblsample_records', ['invoice_id' => 10, 'status' => 'done']);
    }

    public function test_sql_cru_programado(): void
    {
        Testkit::db()->respond('/GROUP BY status/', [['status' => 'done', 'total' => 3]]);

        get_instance()->load->model('sample_module/sample_model');

        $this->assertSame(3, get_instance()->sample_model->totals_by_status()[0]['total']);
    }

    // --- Webhook -------------------------------------------------------------------------------------

    public function test_token_invalido(): void
    {
        require_once Testkit::moduleRoot() . '/controllers/Sample_webhook.php';
        Testkit::db()->table('tblsample_records', ['id', 'invoice_id', 'status']);
        Testkit::option('sample_webhook_token', 'segredo');

        Testkit::request('POST', ['X-Sample-Token' => 'errado'], ['event' => 'RECORD_DONE', 'invoice_id' => 10]);
        $response = Testkit::call(fn () => (new \Sample_webhook())->index());

        $this->assertStatus(401, $response);
        $this->assertBody('Unauthorized', $response);
        $this->assertNoWrites();
    }

    // --- Transporte injetado ------------------------------------------------------------------------

    public function test_agenda_nota(): void
    {
        Testkit::option('sample_api_key', 'chave');
        $transport = (new RecordingTransport())->queue(200, ['id' => 'inv_1', 'status' => 'SCHEDULED']);

        get_instance()->load->library('sample_module/sample_api', $transport);
        $result = get_instance()->sample_api->schedule_invoice(['value' => 100, 'customer' => 'cus_1']);

        $this->assertSame('SCHEDULED', $result['data']['status']);
        $transport->assertSent('POST', '/v3/invoices', fn ($body) => $body['customer'] === 'cus_1');
    }

    // --- Hooks ---------------------------------------------------------------------------------------

    public function test_hook_de_fatura(): void
    {
        Testkit::load('sample_module.php'); // registra os hooks do módulo

        Testkit::fireAction('after_invoice_added', 42);

        $this->assertOptionEquals('sample_last_invoice', 42);
        $this->assertLogged('Fatura adicionada #42');
    }
}
