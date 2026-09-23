<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Inicialização explícita e guarda contra redeclaração — verificadas em processo PHP separado,
 * pois o bootstrap dos testes já inicializou o kit neste processo.
 */
final class BootTest extends TestCase
{
    public function testAutoloadSemBootNaoDefineNadaEBootDefineTudo(): void
    {
        $out = $this->probe('boot');

        foreach ($out['before'] as $symbol => $exists) {
            self::assertFalse($exists, "{$symbol} não deveria existir antes do boot");
        }
        foreach ($out['after'] as $symbol => $exists) {
            self::assertTrue($exists, "{$symbol} deveria existir após o boot");
        }
    }

    public function testFcpathApontaParaDiretorioTemporario(): void
    {
        $out = $this->probe('boot');

        self::assertStringStartsWith(rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), '/'), realpath($out['fcpath']) ?: $out['fcpath']);
        self::assertStringNotContainsString(dirname(__DIR__, 2), $out['fcpath']);
    }

    public function testFuncaoDoModuloDefinidaAntesDoBootPrevalece(): void
    {
        $out = $this->probe('redeclare');

        self::assertSame('modulo:chave', $out['l']);
        self::assertTrue($out['after']['get_option'], 'os demais stubs continuam sendo definidos');
    }

    /** @return array<string, mixed> */
    private function probe(string $mode): array
    {
        $cmd    = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/fixtures/boot_probe.php') . ' ' . escapeshellarg($mode) . ' 2>&1';
        $output = (string) shell_exec($cmd);
        $data   = json_decode($output, true);

        self::assertIsArray($data, 'Saída inesperada do probe: ' . $output);

        return $data;
    }
}
