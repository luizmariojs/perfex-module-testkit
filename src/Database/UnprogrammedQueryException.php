<?php

declare(strict_types=1);

namespace PerfexTestkit\Database;

use PerfexTestkit\TestkitError;

/**
 * Consulta que o banco falso não executa em memória (SQL cru, join, group_by...) e para a qual o
 * teste não programou resposta com FakeDatabase::respond().
 */
final class UnprogrammedQueryException extends TestkitError
{
    public static function for(string $sql, string $reason): self
    {
        return new self(
            "[perfex-module-testkit] Consulta sem resposta programada ({$reason}).\n"
            . "SQL montado: {$sql}\n"
            . 'Programe com Testkit::db()->respond(\'/padrão/\', $linhas).'
        );
    }

    public static function undeclaredTable(string $table, string $sql): self
    {
        return new self(
            "[perfex-module-testkit] Tabela \"{$table}\" não declarada.\n"
            . "SQL montado: {$sql}\n"
            . "Declare com Testkit::db()->table('{$table}', ['id', ...], \$linhas)."
        );
    }
}
