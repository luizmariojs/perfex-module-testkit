<?php

declare(strict_types=1);

namespace PerfexTestkit\Database;

/**
 * Substituto de CI_DB_result.
 */
final class FakeResult
{
    /** @var list<array<string, mixed>> */
    private array $rows;

    /**
     * @param list<array<string, mixed>|object> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = array_values(array_map(static fn ($row) => (array) $row, $rows));
    }

    /**
     * Como no CI3: $n numérico devolve a linha n (ou null); $n string devolve a coluna da primeira linha.
     */
    public function row(int|string $n = 0, string $type = 'object'): mixed
    {
        if (!is_int($n) && !ctype_digit($n)) {
            return $this->rows[0][$n] ?? null;
        }

        $row = $this->rows[(int) $n] ?? null;
        if ($row === null) {
            return null;
        }

        return $type === 'array' ? $row : (object) $row;
    }

    /** @return array<string, mixed>|null */
    public function row_array(int $n = 0): ?array
    {
        return $this->rows[$n] ?? null;
    }

    /** @return list<object>|list<array<string, mixed>> */
    public function result(string $type = 'object'): array
    {
        return $type === 'array' ? $this->rows : array_map(static fn (array $row) => (object) $row, $this->rows);
    }

    /** @return list<array<string, mixed>> */
    public function result_array(): array
    {
        return $this->rows;
    }

    public function num_rows(): int
    {
        return count($this->rows);
    }

    public function first_row(string $type = 'object'): mixed
    {
        return $this->row(0, $type);
    }

    public function last_row(string $type = 'object'): mixed
    {
        return $this->rows === [] ? null : $this->row(count($this->rows) - 1, $type);
    }

    /** @return list<string> */
    public function list_fields(): array
    {
        return array_keys($this->rows[0] ?? []);
    }

    public function free_result(): void
    {
    }
}
