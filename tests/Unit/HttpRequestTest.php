<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\Testkit;
use PHPUnit\Framework\Attributes\Depends;

final class HttpRequestTest extends PerfexTestCase
{
    public function testWebhookLeCorpoEHeaders(): void
    {
        Testkit::request('POST', ['asaas-access-token' => 'tok123', 'Content-Type' => 'application/json'], ['event' => 'INVOICE_AUTHORIZED']);

        self::assertSame('POST', $_SERVER['REQUEST_METHOD']);
        self::assertSame('tok123', $_SERVER['HTTP_ASAAS_ACCESS_TOKEN']);
        self::assertSame('application/json', $_SERVER['CONTENT_TYPE']);
        self::assertSame('{"event":"INVOICE_AUTHORIZED"}', file_get_contents('php://input'));
        self::assertSame('{"event":"INVOICE_AUTHORIZED"}', file_get_contents('php://input'), 'php://input pode ser lido mais de uma vez');
        self::assertSame('INVOICE_AUTHORIZED', json_decode((string) file_get_contents('php://input'))->event);
    }

    public function testLeituraPorFopenEmPartes(): void
    {
        Testkit::request('POST', [], 'abcdef');

        $h = fopen('php://input', 'rb');
        self::assertSame('abc', fread($h, 3));
        self::assertSame('def', stream_get_contents($h));
        self::assertTrue(feof($h));
        fclose($h);
    }

    public function testOutrosCaminhosPhpContinuamFuncionando(): void
    {
        Testkit::request('POST', [], 'corpo');

        $memory = fopen('php://memory', 'w+');
        fwrite($memory, 'em memória');
        rewind($memory);
        self::assertSame('em memória', stream_get_contents($memory));
        fclose($memory);

        $temp = fopen('php://temp', 'w+');
        fwrite($temp, 'temporário');
        rewind($temp);
        self::assertSame('temporário', fread($temp, 100));
        fclose($temp);

        self::assertSame('corpo', file_get_contents('php://input'));
    }

    public function testGetPostEInputDoController(): void
    {
        Testkit::request('POST', ['X-Requested-With' => 'XMLHttpRequest'], '', ['page' => '2'], ['name' => 'Ana']);

        $input = get_instance()->input;
        self::assertSame('2', $_GET['page']);
        self::assertSame('Ana', $_POST['name']);
        self::assertSame(['page' => '2', 'name' => 'Ana'], $_REQUEST);
        self::assertSame('Ana', $input->post('name'));
        self::assertSame('2', $input->get('page'));
        self::assertNull($input->post('nada'));
        self::assertSame('post', $input->method());
        self::assertTrue($input->is_ajax_request());
        self::assertSame('XMLHttpRequest', $input->get_request_header('X-Requested-With'));
    }

    public function testNovaRequisicaoSubstituiAAnterior(): void
    {
        Testkit::request('POST', ['X-A' => '1'], 'primeiro');
        Testkit::request('GET', [], 'segundo');

        self::assertSame('GET', $_SERVER['REQUEST_METHOD']);
        self::assertArrayNotHasKey('HTTP_X_A', $_SERVER);
        self::assertSame('segundo', file_get_contents('php://input'));
    }

    public function testPrimeiroSimulaRequisicao(): void
    {
        $_SERVER['PERFEX_TESTKIT_MARCA'] = 'original';
        Testkit::request('PUT', ['X-Marca' => 'simulada'], 'corpo', ['a' => '1'], ['b' => '2']);

        self::assertSame('PUT', $_SERVER['REQUEST_METHOD']);
        self::assertContains('php', stream_get_wrappers());
    }

    #[Depends('testPrimeiroSimulaRequisicao')]
    public function testSegundoNaoVeRequisicaoAnteriorEWrapperNativoRestaurado(): void
    {
        self::assertArrayNotHasKey('HTTP_X_MARCA', $_SERVER);
        self::assertNotSame('PUT', $_SERVER['REQUEST_METHOD'] ?? null);
        self::assertSame('original', $_SERVER['PERFEX_TESTKIT_MARCA']);
        self::assertSame([], $_GET);
        self::assertSame([], $_POST);

        // Wrapper nativo de volta: php://memory funciona e php://input (CLI) não traz o corpo simulado
        $memory = fopen('php://memory', 'w+');
        self::assertIsResource($memory);
        fclose($memory);
        self::assertSame('', (string) @file_get_contents('php://input'));

        unset($_SERVER['PERFEX_TESTKIT_MARCA']);
    }
}
