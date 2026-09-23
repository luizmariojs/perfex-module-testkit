<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\Http\RecordingTransport;
use PerfexTestkit\Http\UnexpectedRequestException;
use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\State;
use PerfexTestkit\Testkit;

final class RecordingTransportTest extends PerfexTestCase
{
    public function testRespostaEnfileirada(): void
    {
        Testkit::option('sample_api_key', 'chave');
        $transport = (new RecordingTransport())->queue(200, ['id' => 'inv_1', 'status' => 'SCHEDULED']);

        get_instance()->load->library('sample_module/sample_api', $transport);
        $result = get_instance()->sample_api->schedule_invoice(['value' => 100.0, 'customer' => 'cus_1']);

        self::assertSame(200, $result['status']);
        self::assertSame('SCHEDULED', $result['data']['status']);

        $transport->assertSent('POST', '/v3/invoices', fn ($body) => $body['value'] == 100 && $body['customer'] === 'cus_1');
        self::assertSame('chave', $transport->lastCall()['headers']['access_token']);
        self::assertSame(0, $transport->pending());
    }

    public function testChamadaInesperada(): void
    {
        $transport = new RecordingTransport();

        try {
            $transport->request('DELETE', 'https://api-sandbox.asaas.com/v3/invoices/inv_1');
            self::fail('Deveria falhar');
        } catch (UnexpectedRequestException $e) {
            self::assertStringContainsString('DELETE https://api-sandbox.asaas.com/v3/invoices/inv_1', $e->getMessage());
        }

        self::assertCount(1, $transport->calls());
        State::$violations = [];
    }

    public function testFilaEmOrdemEAssertNothingSent(): void
    {
        $vazio = new RecordingTransport();
        $vazio->assertNothingSent();

        $transport = (new RecordingTransport())->queue(201, 'um')->queue(500, 'dois', ['X-Id' => '2']);

        self::assertSame('um', $transport->request('GET', 'https://x/1')->body);
        $segunda = $transport->request('GET', 'https://x/2');
        self::assertSame(500, $segunda->status);
        self::assertSame(['X-Id' => '2'], $segunda->headers);
        self::assertNull($segunda->json());
    }
}
