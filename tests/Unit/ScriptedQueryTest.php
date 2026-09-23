<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\Database\UnprogrammedQueryException;
use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\State;
use PerfexTestkit\Testkit;

final class ScriptedQueryTest extends PerfexTestCase
{
    public function testSqlCruProgramado(): void
    {
        Testkit::db()->respond('/COUNT\(\*\)/', [['total' => 3]]);

        $row = get_instance()->db->query('SELECT COUNT(*) AS total FROM tblinvoices WHERE status = 1')->row();

        self::assertSame(3, $row->total);
    }

    public function testBindsSubstituidos(): void
    {
        Testkit::db()->respond("WHERE hash = 'abc' AND id = 5", [['id' => 5]]);

        $result = get_instance()->db->query('SELECT id FROM tblinvoices WHERE hash = ? AND id = ?', ['abc', 5]);

        self::assertSame(5, $result->row()->id);
        $this->assertQueried("WHERE hash = 'abc' AND id = 5");
    }

    public function testConsultaNaoProgramadaFalhaComOSql(): void
    {
        try {
            get_instance()->db->select('i.id')->from('tblinvoices')
                ->join('tblclients c', 'c.userid = tblinvoices.clientid', 'left')
                ->where('c.active', 1)->get();
            self::fail('Deveria falhar');
        } catch (UnprogrammedQueryException $e) {
            self::assertStringContainsString('join', $e->getMessage());
            self::assertStringContainsString('SELECT i.id FROM tblinvoices LEFT JOIN tblclients c ON c.userid = tblinvoices.clientid WHERE c.active = 1', $e->getMessage());
        }

        State::$violations = [];
    }

    public function testJoinProgramadoEBuilderLimpoDepois(): void
    {
        Testkit::db()->respond('LEFT JOIN tblclients', [['id' => 1, 'company' => 'ACME']]);
        Testkit::db()->table('tblinvoices', ['id']);

        $rows = get_instance()->db->from('tblinvoices')->join('tblclients', 'x = y', 'left')->get()->result();

        self::assertSame('ACME', $rows[0]->company);
        self::assertSame(0, get_instance()->db->get('tblinvoices')->num_rows(), 'depois do roteiro, o builder volta ao modo tabela');
    }

    public function testAgregacoesEGroupBy(): void
    {
        Testkit::db()
            ->respond('SUM(total)', [['total' => 430.5]])
            ->respond('/GROUP BY status/', [['status' => 1, 'n' => 2], ['status' => 2, 'n' => 1]])
            ->respond('/COUNT\(\*\) AS numrows.*status IN/', 7);

        $db = get_instance()->db;

        self::assertSame(430.5, $db->select_sum('total')->get('tblinvoices')->row()->total);
        self::assertCount(2, $db->select('status, COUNT(*) AS n')->group_by('status')->get('tblinvoices')->result());
        self::assertSame(7, $db->group_start()->where_in('status', [1, 2])->group_end()->count_all_results('tblinvoices'));
    }

    public function testUltimaRespostaRegistradaVence(): void
    {
        Testkit::db()->respond('FROM tblx', [['v' => 'primeira']])->respond('FROM tblx', [['v' => 'segunda']]);

        self::assertSame('segunda', get_instance()->db->query('SELECT v FROM tblx')->row()->v);
    }

    public function testEscritaCruaProgramada(): void
    {
        Testkit::db()->respond('/^ALTER TABLE/', [], 0);

        self::assertTrue(get_instance()->db->query('ALTER TABLE tblx ADD COLUMN y INT'));
        self::assertCount(1, Testkit::db()->writes());
    }

    public function testSetSemEscapeUsaRoteiro(): void
    {
        Testkit::db()->table('tblx', ['id', 'contador'], [['id' => 1, 'contador' => 1]]);
        Testkit::db()->respond('SET contador = contador+1', [], 1);

        get_instance()->db->set('contador', 'contador+1', false)->where('id', 1)->update('tblx');

        self::assertSame(1, get_instance()->db->affected_rows());
        $this->assertQueried('UPDATE tblx SET contador = contador+1 WHERE id = 1');
    }
}
