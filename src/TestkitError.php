<?php

declare(strict_types=1);

namespace PerfexTestkit;

/**
 * Erro de uso do kit (componente não resolvido, consulta não programada, chamada HTTP inesperada).
 *
 * Estende \Error — e não \Exception — para não ser engolido por `catch (Exception $e)` comum no
 * código dos módulos. Além disso, toda instância é registrada em State::$violations, e
 * PerfexTestCase falha o teste mesmo que o código testado capture \Throwable.
 */
class TestkitError extends \Error
{
    public function __construct(string $message)
    {
        parent::__construct($message);
        State::violation($message);
    }
}
