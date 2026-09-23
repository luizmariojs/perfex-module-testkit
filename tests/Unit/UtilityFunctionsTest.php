<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;

final class UtilityFunctionsTest extends PerfexTestCase
{
    public function testTraducaoDevolveAChave(): void
    {
        self::assertSame('minha_chave', _l('minha_chave'));
    }

    public function testTraducaoComArgumentos(): void
    {
        self::assertSame('ola_Ana', _l('ola_%s', 'Ana'));
        self::assertSame('a_1_b_2', _l('a_%s_b_%s', [1, 2]));
        self::assertSame('sem_placeholder_%s_%s', _l('sem_placeholder_%s_%s', 'x'), 'argumentos insuficientes devolvem a chave');
    }

    public function testDbPrefix(): void
    {
        self::assertSame('tbl', db_prefix());
    }

    public function testUrls(): void
    {
        self::assertSame('http://localhost/admin/connect_asaas_nf/dashboard', admin_url('connect_asaas_nf/dashboard'));
        self::assertSame('http://localhost/admin/', admin_url());
        self::assertSame('http://localhost/gateways/webhook', site_url('/gateways/webhook'));
        self::assertSame('http://localhost/assets/x.css', base_url('assets/x.css'));
    }

    public function testModuleDirPath(): void
    {
        self::assertSame(dirname(__DIR__) . '/fixtures/sample_module/libraries/Sample_api.php', module_dir_path('sample_module', 'libraries/Sample_api.php'));
        self::assertSame(APP_MODULES_PATH . 'outro_modulo/', module_dir_path('outro_modulo'));
    }

    public function testFormatacaoDeterministica(): void
    {
        self::assertSame('R$ 1.234,50', app_format_money(1234.5, (object) ['symbol' => 'R$']));
        self::assertSame('R$ 10,00', app_format_money('10', 'R$'));
        self::assertSame('1.234,50', app_format_money(1234.5, 'R$', true));
        self::assertSame('INV-000123', format_invoice_number(123));
        self::assertSame('INV-000045', format_invoice_number((object) ['id' => 9, 'number' => 45]));
        self::assertSame('23/09/2026', _d('2026-09-23'));
        self::assertSame('23/09/2026', _d('2026-09-23 14:10:00'));
        self::assertSame('', _d(null));
        self::assertSame('', _d('0000-00-00'));
    }
}
