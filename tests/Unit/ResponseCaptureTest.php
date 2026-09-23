<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\Testkit;

final class ResponseCaptureTest extends PerfexTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once Testkit::moduleRoot() . '/controllers/Sample_webhook.php';
        Testkit::db()->table('tblsample_records', ['id', 'invoice_id', 'status'], [['id' => 1, 'invoice_id' => 10, 'status' => 'pending']]);
        Testkit::option('sample_webhook_token', 'segredo');
    }

    public function testRespostaDeNaoAutorizado(): void
    {
        Testkit::request('POST', ['X-Sample-Token' => 'errado'], ['event' => 'RECORD_DONE', 'invoice_id' => 10]);

        $response = Testkit::call(fn () => (new \Sample_webhook())->index());

        $this->assertStatus(401, $response);
        $this->assertBody('Unauthorized', $response);
        $this->assertNoWrites();
    }

    public function testMetodoNaoPermitido(): void
    {
        Testkit::request('GET');

        $response = Testkit::call(fn () => (new \Sample_webhook())->index());

        $this->assertStatus(405, $response);
    }

    public function testEventoValidoAtualizaORegistro(): void
    {
        Testkit::request('POST', ['X-Sample-Token' => 'segredo'], ['event' => 'RECORD_DONE', 'invoice_id' => 10]);

        $response = Testkit::call(fn () => (new \Sample_webhook())->index());

        $this->assertStatus(200, $response);
        $this->assertBodyContains('OK', $response);
        $this->assertRowExists('tblsample_records', ['invoice_id' => 10, 'status' => 'done']);
    }

    public function testCodigoPadrao200ESaidaAninhada(): void
    {
        $response = Testkit::call(function () {
            echo 'a';
            ob_start();
            echo 'b';
        });

        self::assertSame(200, $response->status);
        self::assertSame('ab', $response->body);
        self::assertNull((new \PerfexTestkit\Http\Response(200, 'x'))->json());
    }

    public function testExcecaoNoControllerNaoVazaBuffer(): void
    {
        $level = ob_get_level();

        try {
            Testkit::call(function () {
                echo 'parcial';
                throw new \RuntimeException('falhou');
            });
        } catch (\RuntimeException $e) {
            self::assertSame('falhou', $e->getMessage());
        }

        self::assertSame($level, ob_get_level());
    }
}
