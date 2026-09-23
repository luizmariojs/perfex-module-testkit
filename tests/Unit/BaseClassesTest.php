<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\Testkit;

final class BaseClassesTest extends PerfexTestCase
{
    public function testModelUsaOBancoFalsoDaInstancia(): void
    {
        Testkit::db()->table('tblsample_records', ['id', 'invoice_id', 'status'], [['id' => 1, 'invoice_id' => 10, 'status' => 'pending']]);
        get_instance()->load->model('sample_module/sample_model');

        self::assertSame('pending', get_instance()->sample_model->get_record(10)->status);
        self::assertSame(Testkit::db(), get_instance()->sample_model->db);
    }

    public function testControllerViraInstanciaECompartilhaComponentes(): void
    {
        require_once Testkit::moduleRoot() . '/controllers/Sample_webhook.php';
        Testkit::db()->table('tblsample_records', ['id', 'invoice_id', 'status']);

        $controller = new \Sample_webhook();

        self::assertSame($controller, get_instance());
        self::assertInstanceOf(\Sample_model::class, $controller->sample_model);
        self::assertSame($controller->sample_model, get_instance()->sample_model);
        self::assertSame(Testkit::db(), $controller->db);
    }

    public function testControllerHerdaComponentesCarregadosAntes(): void
    {
        get_instance()->load->model('sample_module/sample_model');
        $model = get_instance()->sample_model;

        require_once Testkit::moduleRoot() . '/controllers/Sample_settings.php';
        $controller = new \Sample_settings();

        self::assertSame($model, $controller->sample_model);
    }

    public function testAdminControllerSemLogin(): void
    {
        require_once Testkit::moduleRoot() . '/controllers/Sample_settings.php';

        self::assertInstanceOf(\AdminController::class, new \Sample_settings());
    }

    public function testMigrationAcessaInstancia(): void
    {
        $migration = new class () extends \App_module_migration {
            public function up()
            {
                add_option('sample_version', '1.0.0');

                return $this->db->table_exists(db_prefix() . 'sample_records') && $this->ci === get_instance();
            }
        };
        Testkit::db()->table('tblsample_records', ['id']);

        self::assertTrue($migration->up());
        $this->assertOptionEquals('sample_version', '1.0.0');
    }
}
