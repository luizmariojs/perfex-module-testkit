<?php

declare(strict_types=1);

namespace PerfexTestkit\Tests\Unit;

use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\Testkit;

final class OptionsTest extends PerfexTestCase
{
    public function testOptionPreDefinidaPeloTeste(): void
    {
        Testkit::option('regime', 4);

        self::assertSame('4', get_option('regime'));
    }

    public function testOptionInexistenteDevolveStringVazia(): void
    {
        self::assertSame('', get_option('nunca_definida'));
    }

    public function testGravacaoVerificavel(): void
    {
        update_option('x', 'y');

        $this->assertOptionEquals('x', 'y');
        self::assertSame('y', Testkit::option('x'));
    }

    public function testAddOptionNaoSobrescreve(): void
    {
        self::assertTrue(add_option('a', '1'));
        self::assertFalse(add_option('a', '2'));
        $this->assertOptionEquals('a', '1');
    }

    public function testDeleteOption(): void
    {
        Testkit::options(['a' => 1, 'b' => 2]);

        self::assertTrue(delete_option('a'));
        self::assertFalse(delete_option('a'));
        $this->assertOptionMissing('a');
        $this->assertOptionEquals('b', 2);
    }
}
