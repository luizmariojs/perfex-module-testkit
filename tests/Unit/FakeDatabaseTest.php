<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\State;
use PerfexTestkit\TestkitError;
use PerfexTestkit\Testkit;

final class FakeDatabaseTest extends PerfexTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Testkit::db()->table('tblinvoices', ['id', 'hash', 'total', 'status', 'clientid', 'duedate'], [
            ['id' => 1, 'hash' => 'aaa', 'total' => 100.0, 'status' => 1, 'clientid' => 10, 'duedate' => '2026-09-01'],
            ['id' => 2, 'hash' => 'bbb', 'total' => 250.5, 'status' => 2, 'clientid' => 10, 'duedate' => '2026-09-10'],
            ['id' => 3, 'hash' => 'ccc', 'total' => 80.0, 'status' => 1, 'clientid' => 20, 'duedate' => null],
        ]);
    }

    // --- 4.1 schema ------------------------------------------------------------------------------

    public function testColunaDeOutroModuloAusente(): void
    {
        $db = get_instance()->db;

        self::assertTrue($db->table_exists('tblinvoices'));
        self::assertFalse($db->table_exists('tblasaas_invoice_files'));
        self::assertTrue($db->field_exists('hash', 'tblinvoices'));
        self::assertFalse($db->field_exists('asaas_cobranca_id', 'tblinvoices'));
        self::assertSame(['id', 'hash', 'total', 'status', 'clientid', 'duedate'], $db->list_fields('tblinvoices'));
        self::assertFalse($db->list_fields('tblnada'));
    }

    public function testColunaDesconhecidaNaEscritaFalhaComoNoMysql(): void
    {
        $this->expectException(TestkitError::class);
        $this->expectExceptionMessage('Unknown column');

        try {
            get_instance()->db->insert('tblinvoices', ['asaas_cobranca_id' => 'pay_1']);
        } finally {
            State::$violations = [];
        }
    }

    // --- 4.2 leitura -----------------------------------------------------------------------------

    public function testInserirELer(): void
    {
        $row = get_instance()->db->where('id', 1)->get('tblinvoices')->row();

        self::assertIsObject($row);
        self::assertSame('aaa', $row->hash);
    }

    public function testOperadoresNaChave(): void
    {
        $db = get_instance()->db;

        self::assertSame([2], array_column($db->where('total >', 100)->get('tblinvoices')->result_array(), 'id'));
        self::assertSame([1, 2], array_column($db->where('total >=', '100')->get('tblinvoices')->result_array(), 'id'));
        self::assertSame([2], array_column($db->where('status !=', 1)->get('tblinvoices')->result_array(), 'id'));
        self::assertSame([3], array_column($db->where('duedate', null)->get('tblinvoices')->result_array(), 'id'));
        self::assertSame([1, 2], array_column($db->where('duedate IS NOT NULL')->get('tblinvoices')->result_array(), 'id'));
    }

    public function testWhereArrayEOrWhereComPrecedenciaSql(): void
    {
        // status = 1 AND clientid = 20 OR id = 2   =>   (status=1 AND clientid=20) OR id=2
        $rows = get_instance()->db
            ->where(['status' => 1, 'clientid' => 20])
            ->or_where('id', 2)
            ->get('tblinvoices')->result_array();

        self::assertSame([2, 3], array_column($rows, 'id'));
    }

    public function testWhereInLikeOrderLimitSelect(): void
    {
        $db = get_instance()->db;

        self::assertSame([1, 3], array_column($db->where_in('hash', ['aaa', 'ccc'])->get('tblinvoices')->result_array(), 'id'));
        self::assertSame([2], array_column($db->where_not_in('id', [1, 3])->get('tblinvoices')->result_array(), 'id'));
        self::assertSame([2], array_column($db->like('hash', 'B')->get('tblinvoices')->result_array(), 'id'), 'like sem diferenciar caixa');
        self::assertSame([1], array_column($db->like('hash', 'a', 'after')->get('tblinvoices')->result_array(), 'id'));

        $rows = $db->select('id, total AS valor')->order_by('total', 'DESC')->limit(2)->get('tblinvoices')->result_array();
        self::assertSame([['id' => 2, 'valor' => 250.5], ['id' => 1, 'valor' => 100.0]], $rows);

        $rows = $db->order_by('clientid DESC, id ASC')->limit(1, 1)->get('tblinvoices')->result_array();
        self::assertSame(1, $rows[0]['id']);
    }

    public function testGetWhereFromECount(): void
    {
        $db = get_instance()->db;

        self::assertSame(2, $db->get_where('tblinvoices', ['clientid' => 10])->num_rows());
        self::assertSame('ccc', $db->from('tblinvoices')->where('id', 3)->get()->row()->hash);
        self::assertSame(2, $db->where('status', 1)->count_all_results('tblinvoices'));
        self::assertSame(3, $db->count_all('tblinvoices'));
    }

    public function testBuilderLimpoEntreConsultas(): void
    {
        $db = get_instance()->db;

        $db->where('id', 1)->get('tblinvoices');

        self::assertSame(3, $db->get('tblinvoices')->num_rows());
    }

    public function testTabelaNaoDeclaradaFalhaComDica(): void
    {
        try {
            get_instance()->db->get('tblitemable');
            self::fail('Deveria falhar');
        } catch (TestkitError $e) {
            self::assertStringContainsString('tblitemable', $e->getMessage());
            self::assertStringContainsString("table('tblitemable'", $e->getMessage());
        }

        State::$violations = [];
    }

    // --- 4.3 escrita -----------------------------------------------------------------------------

    public function testAtualizarComFiltro(): void
    {
        $db = get_instance()->db;

        $db->where('clientid', 10)->update('tblinvoices', ['status' => 2]);

        self::assertSame(1, $db->affected_rows(), 'a fatura 2 já estava com status 2 (MySQL conta só as alteradas)');
        $this->assertRowExists('tblinvoices', ['id' => 1, 'status' => 2]);
        $this->assertRowExists('tblinvoices', ['id' => 3, 'status' => 1]);
    }

    public function testUpdateComSetEWhereNoArgumento(): void
    {
        $db = get_instance()->db;

        $db->set('status', 5)->update('tblinvoices', null, ['id' => 3]);

        $this->assertRowExists('tblinvoices', ['id' => 3, 'status' => 5]);
        self::assertSame(1, $db->affected_rows());
    }

    public function testInsertComAutoIncremento(): void
    {
        $db = get_instance()->db;

        $db->insert('tblinvoices', ['hash' => 'ddd', 'total' => 10]);

        self::assertSame(4, $db->insert_id());
        self::assertSame(1, $db->affected_rows());
        $this->assertRowExists('tblinvoices', ['id' => 4, 'hash' => 'ddd', 'status' => null]);
    }

    public function testDelete(): void
    {
        $db = get_instance()->db;

        $db->delete('tblinvoices', ['clientid' => 10]);

        self::assertSame(2, $db->affected_rows());
        self::assertSame([3], array_column(Testkit::db()->rows('tblinvoices'), 'id'));
    }

    // --- 4.4 resultados --------------------------------------------------------------------------

    public function testNenhumaLinha(): void
    {
        $result = get_instance()->db->where('id', 99)->get('tblinvoices');

        self::assertNull($result->row());
        self::assertNull($result->row_array());
        self::assertSame([], $result->result());
        self::assertSame([], $result->result_array());
        self::assertSame(0, $result->num_rows());
    }

    public function testFormatosDeResultado(): void
    {
        $result = get_instance()->db->where('id <=', 2)->get('tblinvoices');

        self::assertSame('bbb', $result->row(1)->hash);
        self::assertSame('aaa', $result->row('hash'), 'row(coluna) devolve a coluna da primeira linha, como no CI3');
        self::assertSame('aaa', $result->row_array()['hash']);
        self::assertContainsOnlyInstancesOf(\stdClass::class, $result->result());
        self::assertSame(2, $result->num_rows());
    }

    // --- 4.6 registro e asserções ----------------------------------------------------------------

    public function testNenhumaEscrita(): void
    {
        get_instance()->db->where('id', 1)->get('tblinvoices');

        $this->assertNoWrites();
        $this->assertQueried("SELECT * FROM tblinvoices WHERE id = 1");
        $this->assertQueried('/WHERE id = 1$/');
    }

    public function testLinhaGravadaERegistroDeOperacoes(): void
    {
        get_instance()->db->where('id', 2)->update('tblinvoices', ['status' => 'canceled']);

        $this->assertRowExists('tblinvoices', ['id' => 2, 'status' => 'canceled']);
        $this->assertRowMissing('tblinvoices', ['status' => 2]);

        $writes = Testkit::db()->writes();
        self::assertCount(1, $writes);
        self::assertSame('update', $writes[0]['type']);
        self::assertSame("UPDATE tblinvoices SET status = 'canceled' WHERE id = 2", $writes[0]['sql']);
        self::assertSame(['status' => 'canceled'], $writes[0]['data']);
        self::assertSame("UPDATE tblinvoices SET status = 'canceled' WHERE id = 2", get_instance()->db->last_query());
    }
}
