<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\Testkit;

final class PermissionsTest extends PerfexTestCase
{
    public function testVisitantePorPadrao(): void
    {
        self::assertFalse(get_staff_user_id());
        self::assertFalse(is_staff_logged_in());
        self::assertFalse(is_admin());
        self::assertFalse(staff_can('view', 'x'));
    }

    public function testPermissaoNegada(): void
    {
        Testkit::actingAs(3, ['x' => ['view']]);

        self::assertSame(3, get_staff_user_id());
        self::assertFalse(staff_can('edit', 'x'));
        self::assertFalse(has_permission('x', '', 'delete'));
        self::assertFalse(has_permission('y'));
    }

    public function testPermissaoConcedida(): void
    {
        Testkit::actingAs(3, ['x' => ['view', 'edit']]);

        self::assertTrue(staff_can('edit', 'x'));
        self::assertTrue(has_permission('x', '', 'view'));
        self::assertTrue(has_permission('x'));
    }

    public function testAdminTemTudo(): void
    {
        Testkit::actingAs(1, [], true);

        self::assertTrue(is_admin());
        self::assertTrue(staff_can('delete', 'qualquer'));
        self::assertTrue(has_permission('qualquer', '', 'create'));
    }

    public function testControllerRespeitaPermissao(): void
    {
        require_once Testkit::moduleRoot() . '/controllers/Sample_settings.php';
        Testkit::request('POST', [], '', [], ['name' => 'Novo']);

        Testkit::actingAs(2, ['sample_module' => ['view']]);
        self::assertFalse((new \Sample_settings())->save());
        $this->assertAlert('danger', 'access_denied');
        $this->assertOptionMissing('sample_name');

        Testkit::actingAs(2, ['sample_module' => ['view', 'edit']]);
        self::assertTrue((new \Sample_settings())->save());
        $this->assertOptionEquals('sample_name', 'Novo');
        $this->assertAlert('success');
    }
}
