<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\Testkit;

/**
 * Stubs absorvidos dos módulos consumidores na v0.2.0 (issue #5): navegação e erros, campos personalizados, área do
 * cliente, registro do módulo, helpers de view e App_gateway.
 */
final class ModuleStubsTest extends PerfexTestCase
{
    // --- navegação e erros ---------------------------------------------------------------------------

    public function testRedirectLancaComODestino(): void
    {
        $this->expectException(\TestRedirect::class);
        $this->expectExceptionMessage('http://localhost/admin/x');

        redirect(admin_url('x'));
    }

    public function testShow404Lanca(): void
    {
        $this->expectException(\TestNotFound::class);

        show_404();
    }

    public function testAccessDeniedLancaComAPermissao(): void
    {
        $this->expectException(\TestAccessDenied::class);
        $this->expectExceptionMessage('meu_modulo');

        access_denied('meu_modulo');
    }

    // --- campos personalizados -----------------------------------------------------------------------

    public function testCampoPersonalizadoDefinido(): void
    {
        Testkit::customField('customers_bairro', 10, 'Centro');

        self::assertSame('Centro', get_custom_field_value(10, 'customers_bairro', 'customers'));
        self::assertSame('', get_custom_field_value(11, 'customers_bairro', 'customers'), 'outro relid');
    }

    public function testCampoPersonalizadoAusenteEVazio(): void
    {
        self::assertSame('', get_custom_field_value(10, 'qualquer', 'customers'));
    }

    public function testCampoPersonalizadoNuloContinuaNulo(): void
    {
        Testkit::customField('x', 1, null);

        self::assertSame('', get_custom_field_value(1, 'x', 'customers'), 'nulo é tratado como ausente');
    }

    // --- área do cliente -------------------------------------------------------------------------------

    public function testSemContatoNaoHaClienteLogado(): void
    {
        self::assertFalse(is_client_logged_in());
        self::assertFalse(get_client_user_id());
        self::assertFalse(has_contact_permission('invoices'));
    }

    public function testContatoComPermissao(): void
    {
        Testkit::actingAsContact(5, ['invoices']);

        self::assertTrue(is_client_logged_in());
        self::assertSame(5, get_client_user_id());
        self::assertTrue(has_contact_permission('invoices'));
        self::assertFalse(has_contact_permission('estimates'));
        redirect_after_login_to_current_url();
    }

    public function testMenuDoCliente(): void
    {
        add_theme_menu_item('notas', ['name' => 'Notas', 'href' => '/notas', 'position' => 20]);

        self::assertSame(['notas' => ['name' => 'Notas', 'href' => '/notas', 'position' => 20]], Testkit::clientMenu());
    }

    // --- registro do módulo ----------------------------------------------------------------------------

    public function testHooksDeAtivacaoSemEfeito(): void
    {
        register_activation_hook('meu_modulo', static fn () => null);
        register_deactivation_hook('meu_modulo', static fn () => null);

        self::assertSame([], Testkit::activity());
    }

    public function testCapacidadesDaEquipeNoFiltro(): void
    {
        register_staff_capabilities('meu_modulo', ['capabilities' => ['view' => 'Ver', 'create' => 'Criar']], 'Meu módulo');

        $permissoes = Testkit::applyFilter('staff_permissions', []);

        self::assertSame('Meu módulo', $permissoes['meu_modulo']['name']);
        self::assertSame(['view' => 'Ver', 'create' => 'Criar'], $permissoes['meu_modulo']['capabilities']);
    }

    // --- helpers de view -------------------------------------------------------------------------------

    public function testHelpersDeView(): void
    {
        ob_start();
        init_head();
        init_tail();
        self::assertSame('', ob_get_clean(), 'cabeçalho e rodapé do tema não imprimem nada');

        self::assertSame('&lt;b&gt;&quot;x&quot;&lt;/b&gt;', html_escape('<b>"x"</b>'));
        self::assertSame(['a'], html_escape(['a']), 'não-string volta igual');
        self::assertSame('&lt;i&gt;', e('<i>'));
        self::assertSame('2026-10-08 18:00:00', _dt('2026-10-08 18:00:00'));
        self::assertSame('/modules/meu_modulo/assets/x.js', module_dir_url('meu_modulo', 'assets/x.js'));
        self::assertSame('R$', get_base_currency()->symbol);
        self::assertSame('<span class="label invoice-status-2">Pago</span>', format_invoice_status(2));
        self::assertSame('<form action="/salvar" method="post">', form_open('/salvar'));
        self::assertSame('</form>', form_close());
        self::assertSame('<input type="hidden" name="id" value="3">', form_hidden('id', 3));
    }

    // --- App_gateway -------------------------------------------------------------------------------------

    public function testGatewayRegistraPagamento(): void
    {
        $gateway = new class () extends \App_gateway {
        };
        $gateway->setId('meu_gateway');
        $gateway->setName('Meu gateway');
        $gateway->setSettings([]);

        self::assertSame('', $gateway->getSetting('qualquer'));
        self::assertTrue($gateway->addPayment(['amount' => 10, 'invoiceid' => 3]));
        self::assertSame([['amount' => 10, 'invoiceid' => 3]], Testkit::payments());
    }
}
