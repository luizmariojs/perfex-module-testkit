<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\State;
use PerfexTestkit\Testkit;
use PerfexTestkit\UnresolvedComponentException;

final class LoaderTest extends PerfexTestCase
{
    public function testModelDoModulo(): void
    {
        $CI = &get_instance();
        $CI->load->model('sample_module/sample_model');

        self::assertInstanceOf(\Sample_model::class, $CI->sample_model);
    }

    public function testModelComNomeAlternativo(): void
    {
        get_instance()->load->model('sample_module/sample_model', 'registros');

        self::assertInstanceOf(\Sample_model::class, get_instance()->registros);
    }

    public function testLibraryComParametroEHelper(): void
    {
        $CI = &get_instance();
        $CI->load->library('sample_module/sample_api', 'transporte');
        $CI->load->helper('sample_module/sample');

        self::assertInstanceOf(\Sample_api::class, $CI->sample_api);
        self::assertSame('sample_hello_Ana', sample_greeting('Ana'));
    }

    public function testHelperNativoSemEfeito(): void
    {
        get_instance()->load->helper(['url', 'date']);

        self::assertSame([], State::$violations);
    }

    public function testModelNativoRegistradoComoDuble(): void
    {
        $double = new class () {
            public function get($id)
            {
                return (object) ['id' => $id, 'total' => 100];
            }
        };
        Testkit::double('invoices_model', $double);

        get_instance()->load->model('invoices_model');

        self::assertSame($double, get_instance()->invoices_model);
        self::assertSame(100, get_instance()->invoices_model->get(5)->total);
    }

    public function testComponenteDesconhecidoFalhaComONome(): void
    {
        try {
            get_instance()->load->model('invoices_model');
            self::fail('Deveria lançar UnresolvedComponentException');
        } catch (UnresolvedComponentException $e) {
            self::assertStringContainsString('invoices_model', $e->getMessage());
            self::assertStringContainsString('Testkit::double', $e->getMessage());
        }

        State::$violations = []; // violação esperada neste teste
    }

    public function testModelInexistenteNoModulo(): void
    {
        try {
            get_instance()->load->model('sample_module/nao_existe_model');
            self::fail('Deveria lançar UnresolvedComponentException');
        } catch (UnresolvedComponentException $e) {
            self::assertStringContainsString('nao_existe_model', $e->getMessage());
        }

        State::$violations = [];
    }

    public function testViolacaoEngolidaPeloCodigoAindaERegistrada(): void
    {
        try {
            get_instance()->load->model('outro_modulo/algum_model');
        } catch (\Throwable $e) {
            // código do módulo que engole qualquer erro
        }

        self::assertCount(1, State::$violations);
        self::assertStringContainsString('outro_modulo/algum_model', State::$violations[0]);

        State::$violations = [];
    }
}
