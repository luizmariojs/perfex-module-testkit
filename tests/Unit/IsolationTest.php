<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\Testkit;
use PHPUnit\Framework\Attributes\Depends;

final class IsolationTest extends PerfexTestCase
{
    public function testPrimeiroGravaEstado(): void
    {
        update_option('vaza', 'sim');
        Testkit::db()->table('tblx', ['id', 'nome'], [['id' => 1, 'nome' => 'a']]);
        log_activity('primeiro');
        set_alert('success', 'ok');
        hooks()->add_action('evento', static fn () => null);
        Testkit::actingAs(7, ['x' => ['view']], true);
        get_instance()->algo = 'carregado';
        Testkit::customField('customers_bairro', 1, 'Centro');
        Testkit::actingAsContact(5, ['invoices']);
        add_theme_menu_item('notas', ['name' => 'Notas']);
        (new class () extends \App_gateway {
        })->addPayment(['amount' => 1]);

        self::assertSame('sim', get_option('vaza'));
    }

    #[Depends('testPrimeiroGravaEstado')]
    public function testSegundoNaoVeEstadoDoPrimeiro(): void
    {
        self::assertSame('', get_option('vaza'));
        self::assertFalse(Testkit::db()->table_exists('tblx'));
        self::assertSame([], Testkit::activity());
        self::assertSame([], Testkit::alerts());
        self::assertFalse(hooks()->has_action('evento'));
        self::assertFalse(get_staff_user_id());
        self::assertFalse(is_admin());
        self::assertFalse(isset(get_instance()->algo));
        self::assertSame('', get_custom_field_value(1, 'customers_bairro', 'customers'));
        self::assertFalse(is_client_logged_in());
        self::assertSame([], Testkit::clientMenu());
        self::assertSame([], Testkit::payments());
    }
}
