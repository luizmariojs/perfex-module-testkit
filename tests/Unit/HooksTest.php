<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\Testkit;

final class HooksTest extends PerfexTestCase
{
    public function testLogRegistrado(): void
    {
        log_activity('mensagem de teste');

        $this->assertLogged('mensagem de teste');
        $this->assertNotLogged('outra coisa');
        self::assertSame('mensagem de teste', Testkit::activity()[0]['description']);
    }

    public function testAlertaCapturado(): void
    {
        set_alert('warning', 'Atenção: algo');

        $this->assertAlert('warning', 'Atenção');
    }

    public function testArquivoPrincipalRegistraHookEOTestePodeDispara(): void
    {
        $this->loadMainFile();

        $this->assertHookRegistered('after_invoice_added');
        Testkit::fireAction('after_invoice_added', 42);

        $this->assertOptionEquals('sample_last_invoice', 42);
        $this->assertLogged('Fatura adicionada #42');
    }

    public function testFiltroComArgumentosExtras(): void
    {
        $this->loadMainFile();

        $this->assertHookRegistered('sample_invoice_label', 'filter');
        self::assertSame('Fatura #7', Testkit::applyFilter('sample_invoice_label', 'Fatura', 7));
    }

    public function testPrioridadeEArgumentosAceitos(): void
    {
        $calls = [];
        hooks()->add_action('x', function (...$args) use (&$calls) { $calls[] = ['p20', $args]; }, 20, 2);
        hooks()->add_action('x', function (...$args) use (&$calls) { $calls[] = ['p5', $args]; }, 5);

        hooks()->do_action('x', 'a', 'b', 'c');

        self::assertSame([['p5', ['a']], ['p20', ['a', 'b']]], $calls);
        self::assertSame([['tag' => 'x', 'args' => ['a', 'b', 'c']]], hooks()->fired());
    }

    public function testCallbackPorNomeDeclaradoDepoisDoRegistro(): void
    {
        hooks()->add_action('tarde', 'perfex_testkit_funcao_declarada_depois');
        eval('function perfex_testkit_funcao_declarada_depois() { log_activity("chamada tardia"); }');

        Testkit::fireAction('tarde');

        $this->assertLogged('chamada tardia');
    }

    public function testLoadReaplicaHooksNasCargasSeguintes(): void
    {
        Testkit::load('sample_module.php');
        self::assertTrue(hooks()->has_action('after_invoice_added'));

        \PerfexTestkit\State::reset(); // simula o próximo teste
        self::assertFalse(hooks()->has_action('after_invoice_added'));

        Testkit::load('sample_module.php');
        $this->assertHookRegistered('after_invoice_added');
        $this->assertHookRegistered('sample_invoice_label', 'filter');
        self::assertCount(2, hooks()->registrations(), 'sem duplicar registros');
    }

    public function testLoadDeArquivoInexistente(): void
    {
        $this->expectException(\PerfexTestkit\TestkitError::class);

        try {
            Testkit::load('nao_existe.php');
        } finally {
            \PerfexTestkit\State::$violations = [];
        }
    }

    private function loadMainFile(): void
    {
        Testkit::load('sample_module.php');
    }
}
