<?php

declare(strict_types=1);

namespace PerfexTestkit;

/**
 * O código pediu ao loader um model/library/helper que não existe no módulo nem foi registrado
 * como dublê via Testkit::double().
 */
final class UnresolvedComponentException extends TestkitError
{
    public static function for(string $type, string $name, string $hint): self
    {
        return new self(sprintf('[perfex-module-testkit] %s "%s" não resolvido: %s', $type, $name, $hint));
    }
}
