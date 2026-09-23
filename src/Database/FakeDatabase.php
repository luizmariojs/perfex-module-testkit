<?php

declare(strict_types=1);

namespace PerfexTestkit\Database;

use PerfexTestkit\TestkitError;

/**
 * Banco de dados falso compatível com o query builder do CodeIgniter 3 ($this->db).
 *
 * Dois modos:
 * - **tabela**: CRUD simples sobre uma tabela declarada com table() é executado em memória
 *   (where / or_where / where_in / like / order_by / limit / select de colunas / insert / update / delete).
 * - **roteiro**: SQL cru (query()) e consultas que exigiriam interpretar SQL — join, group_by, having,
 *   select_sum/max/min/avg, distinct, group_start, where em texto livre, set() sem escape — nunca são
 *   interpretados. O SQL é montado (aproximado, para exibição e casamento) e respondido pelo que o teste
 *   programou com respond(). Sem resposta programada, o teste falha mostrando o SQL.
 *
 * Semântica aproximada do MySQL: comparação numérica quando os dois lados são numéricos, comparação de
 * strings sem diferenciar maiúsculas/minúsculas, NULL só casa com IS NULL, affected_rows conta só linhas
 * efetivamente alteradas.
 *
 * Toda operação é registrada (log()) para asserções.
 */
final class FakeDatabase
{
    /** @var array<string, array{columns: list<string>, rows: list<array<string, mixed>>, auto: int}> */
    private array $tables = [];

    /** @var list<array{pattern: string, rows: list<array<string, mixed>|object>|int, affected: int|null}> */
    private array $responses = [];

    /** @var list<array{type: string, table: string|null, sql: string, data: array<string, mixed>|null, write: bool, scripted: bool}> */
    private array $log = [];

    private int $affectedRows = 0;

    private int $insertId = 0;

    private string $lastQuery = '';

    // --- estado do builder (limpo após cada execução) ---------------------------------------------

    /** @var list<string> */
    private array $select = [];

    private ?string $from = null;

    /** @var list<array<string, mixed>> */
    private array $wheres = [];

    /** @var list<array{field: string, dir: string}> */
    private array $orderBy = [];

    private ?int $limit = null;

    private int $offset = 0;

    /** @var array<string, array{value: mixed, escape: bool}> */
    private array $set = [];

    /** @var list<string> trechos de SQL que forçam o modo roteiro (join, group by...) */
    private array $extraSql = [];

    /** @var list<string> motivos que forçam o modo roteiro */
    private array $complex = [];

    private bool $openGroup = false;

    // =============================================================================================
    // API do teste
    // =============================================================================================

    /**
     * Declara uma tabela (colunas e linhas iniciais). Redeclarar substitui a tabela.
     *
     * @param list<string>                             $columns
     * @param list<array<string, mixed>|object>         $rows
     */
    public function table(string $name, array $columns, array $rows = []): self
    {
        $this->tables[$name] = ['columns' => array_values($columns), 'rows' => [], 'auto' => 0];

        foreach ($rows as $row) {
            $row = (array) $row;
            $this->assertColumns($name, $row);
            $this->tables[$name]['rows'][] = $this->normalizeRow($name, $row);
            if (isset($row['id']) && is_numeric($row['id'])) {
                $this->tables[$name]['auto'] = max($this->tables[$name]['auto'], (int) $row['id']);
            }
        }

        return $this;
    }

    /**
     * Programa a resposta de consultas em modo roteiro.
     *
     * @param string                                   $pattern  regex (`/.../`) ou trecho de texto (sem diferenciar caixa)
     * @param list<array<string, mixed>|object>|int     $rows     linhas devolvidas; int para count_all_results
     * @param int|null                                 $affected affected_rows após escritas programadas
     */
    public function respond(string $pattern, array|int $rows = [], ?int $affected = null): self
    {
        $this->responses[] = ['pattern' => $pattern, 'rows' => $rows, 'affected' => $affected];

        return $this;
    }

    /** @return list<array<string, mixed>> linhas atuais da tabela */
    public function rows(string $table): array
    {
        if (!isset($this->tables[$table])) {
            throw new TestkitError("[perfex-module-testkit] Tabela \"{$table}\" não declarada.");
        }

        return $this->tables[$table]['rows'];
    }

    /** @return list<array{type: string, table: string|null, sql: string, data: array<string, mixed>|null, write: bool, scripted: bool}> */
    public function log(): array
    {
        return $this->log;
    }

    /** @return list<array{type: string, table: string|null, sql: string, data: array<string, mixed>|null, write: bool, scripted: bool}> */
    public function writes(): array
    {
        return array_values(array_filter($this->log, static fn (array $entry) => $entry['write']));
    }

    // =============================================================================================
    // Introspecção
    // =============================================================================================

    public function table_exists(string $table): bool
    {
        return isset($this->tables[$table]);
    }

    public function field_exists(string $field, string $table): bool
    {
        return isset($this->tables[$table]) && in_array($field, $this->tables[$table]['columns'], true);
    }

    /** @return list<string>|false */
    public function list_fields(string $table): array|false
    {
        return $this->tables[$table]['columns'] ?? false;
    }

    /** @return list<string> */
    public function list_tables(): array
    {
        return array_keys($this->tables);
    }

    public function dbprefix(string $table = ''): string
    {
        return 'tbl' . $table;
    }

    // =============================================================================================
    // Builder
    // =============================================================================================

    /** @param string|list<string> $select */
    public function select(string|array $select = '*', ?bool $escape = null): self
    {
        foreach (is_array($select) ? $select : explode(',', $select) as $column) {
            $column = trim($column);
            if ($column === '') {
                continue;
            }
            if (str_contains($column, '(')) {
                $this->complex[] = 'select com função';
            }
            $this->select[] = $column;
        }

        return $this;
    }

    public function select_sum(string $select = '', string $alias = ''): self
    {
        return $this->aggregate('SUM', $select, $alias);
    }

    public function select_max(string $select = '', string $alias = ''): self
    {
        return $this->aggregate('MAX', $select, $alias);
    }

    public function select_min(string $select = '', string $alias = ''): self
    {
        return $this->aggregate('MIN', $select, $alias);
    }

    public function select_avg(string $select = '', string $alias = ''): self
    {
        return $this->aggregate('AVG', $select, $alias);
    }

    public function distinct(bool $value = true): self
    {
        if ($value) {
            $this->complex[] = 'distinct';
            $this->extraSql[] = 'DISTINCT';
        }

        return $this;
    }

    public function from(string|array $from): self
    {
        $from = is_array($from) ? implode(', ', $from) : trim($from);
        if (str_contains($from, ',') || str_contains($from, ' ')) {
            $this->complex[] = 'from com alias ou múltiplas tabelas';
        }
        $this->from = $from;

        return $this;
    }

    public function join(string $table, string $cond, string $type = '', ?bool $escape = null): self
    {
        $this->complex[] = 'join';
        $this->extraSql[] = trim(strtoupper($type) . ' JOIN ' . $table . ' ON ' . $cond);

        return $this;
    }

    public function where(mixed $key, mixed $value = null, ?bool $escape = null): self
    {
        return $this->addWhere('AND', $key, $value, $escape, func_num_args() >= 2);
    }

    public function or_where(mixed $key, mixed $value = null, ?bool $escape = null): self
    {
        return $this->addWhere('OR', $key, $value, $escape, func_num_args() >= 2);
    }

    public function where_in(?string $key = null, mixed $values = null, ?bool $escape = null): self
    {
        return $this->addIn('AND', $key, $values, false);
    }

    public function or_where_in(?string $key = null, mixed $values = null, ?bool $escape = null): self
    {
        return $this->addIn('OR', $key, $values, false);
    }

    public function where_not_in(?string $key = null, mixed $values = null, ?bool $escape = null): self
    {
        return $this->addIn('AND', $key, $values, true);
    }

    public function or_where_not_in(?string $key = null, mixed $values = null, ?bool $escape = null): self
    {
        return $this->addIn('OR', $key, $values, true);
    }

    public function like(mixed $field, string $match = '', string $side = 'both', ?bool $escape = null): self
    {
        return $this->addLike('AND', $field, $match, $side, false);
    }

    public function or_like(mixed $field, string $match = '', string $side = 'both', ?bool $escape = null): self
    {
        return $this->addLike('OR', $field, $match, $side, false);
    }

    public function not_like(mixed $field, string $match = '', string $side = 'both', ?bool $escape = null): self
    {
        return $this->addLike('AND', $field, $match, $side, true);
    }

    public function or_not_like(mixed $field, string $match = '', string $side = 'both', ?bool $escape = null): self
    {
        return $this->addLike('OR', $field, $match, $side, true);
    }

    public function group_start(string $not = '', string $type = 'AND '): self
    {
        $this->complex[] = 'group_start';
        $this->wheres[] = ['kind' => 'open', 'conj' => $this->openGroup ? '' : trim($type), 'not' => $not !== ''];
        $this->openGroup = true;

        return $this;
    }

    public function or_group_start(): self
    {
        return $this->group_start('', 'OR ');
    }

    public function not_group_start(): self
    {
        return $this->group_start('NOT ', 'AND ');
    }

    public function or_not_group_start(): self
    {
        return $this->group_start('NOT ', 'OR ');
    }

    public function group_end(): self
    {
        $this->wheres[] = ['kind' => 'close', 'conj' => ''];

        return $this;
    }

    /** @param string|list<string> $by */
    public function group_by(string|array $by, ?bool $escape = null): self
    {
        $this->complex[] = 'group_by';
        $this->extraSql[] = 'GROUP BY ' . (is_array($by) ? implode(', ', $by) : $by);

        return $this;
    }

    public function having(mixed $key, mixed $value = null, ?bool $escape = null): self
    {
        $this->complex[] = 'having';
        $this->extraSql[] = 'HAVING ' . (is_array($key) ? json_encode($key) : $key . ($value !== null ? ' ' . $this->quote($value) : ''));

        return $this;
    }

    public function or_having(mixed $key, mixed $value = null, ?bool $escape = null): self
    {
        return $this->having($key, $value, $escape);
    }

    public function order_by(string $orderby, string $direction = '', ?bool $escape = null): self
    {
        if (strtoupper(trim($direction)) === 'RANDOM' || stripos($orderby, 'RAND(') !== false) {
            $this->complex[] = 'order_by aleatório';
            $this->extraSql[] = 'ORDER BY RAND()';

            return $this;
        }

        foreach (explode(',', $orderby) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $dir = strtoupper(trim($direction)) === 'DESC' ? 'DESC' : 'ASC';
            if (preg_match('/^(.+?)\s+(ASC|DESC)$/i', $part, $m)) {
                $part = $m[1];
                $dir  = strtoupper($m[2]);
            }
            if (str_contains($part, '(')) {
                $this->complex[] = 'order_by com função';
            }
            $this->orderBy[] = ['field' => $part, 'dir' => $dir];
        }

        return $this;
    }

    public function limit(?int $value, int $offset = 0): self
    {
        $this->limit = $value;
        if ($offset) {
            $this->offset = $offset;
        }

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function set(mixed $key, mixed $value = '', ?bool $escape = null): self
    {
        if (is_array($key) || is_object($key)) {
            foreach ((array) $key as $k => $v) {
                $this->set($k, $v, $escape);
            }

            return $this;
        }

        $this->set[(string) $key] = ['value' => $value, 'escape' => $escape !== false];

        return $this;
    }

    public function reset_query(): self
    {
        $this->resetBuilder();

        return $this;
    }

    // =============================================================================================
    // Execução
    // =============================================================================================

    public function get(string $table = '', ?int $limit = null, ?int $offset = null): FakeResult
    {
        if ($limit !== null) {
            $this->limit($limit, (int) $offset);
        }

        $table = $table !== '' ? $table : (string) $this->from;
        $sql   = $this->compileSelect($table);

        try {
            if ($this->complex !== []) {
                $response = $this->scripted($sql, implode(', ', array_unique($this->complex)));
                $this->record('select', $table, $sql, null, false, true);

                return new FakeResult(is_int($response['rows']) ? [] : $response['rows']);
            }

            $rows = $this->filter($table, $sql);
            $rows = $this->sort($rows);
            $rows = array_slice($rows, $this->offset, $this->limit);
            $rows = $this->project($rows);
            $this->record('select', $table, $sql, null, false, false);

            return new FakeResult($rows);
        } finally {
            $this->resetBuilder();
        }
    }

    public function get_where(string $table = '', mixed $where = null, ?int $limit = null, ?int $offset = null): FakeResult
    {
        if ($where !== null) {
            $this->where($where);
        }

        return $this->get($table, $limit, $offset);
    }

    public function count_all_results(string $table = '', bool $reset = true): int
    {
        $table = $table !== '' ? $table : (string) $this->from;
        $sql   = 'SELECT COUNT(*) AS numrows FROM ' . $table . $this->compileTail(false);

        try {
            if ($this->complex !== []) {
                $response = $this->scripted($sql, implode(', ', array_unique($this->complex)));
                $this->record('count', $table, $sql, null, false, true);

                return is_int($response['rows']) ? $response['rows'] : count($response['rows']);
            }

            $count = count($this->filter($table, $sql));
            $this->record('count', $table, $sql, null, false, false);

            return $count;
        } finally {
            if ($reset) {
                $this->resetBuilder();
            }
        }
    }

    public function count_all(string $table = ''): int
    {
        $sql = 'SELECT COUNT(*) AS numrows FROM ' . $table;
        if (!isset($this->tables[$table])) {
            throw UnprogrammedQueryException::undeclaredTable($table, $sql);
        }
        $this->record('count', $table, $sql, null, false, false);

        return count($this->tables[$table]['rows']);
    }

    public function insert(string $table = '', mixed $set = null, ?bool $escape = null): bool
    {
        if ($set !== null) {
            $this->set($set, '', $escape);
        }

        $table = $table !== '' ? $table : (string) $this->from;
        $data  = $this->takeSet();
        $sql   = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', array_keys($data['values'])),
            implode(', ', array_map(fn ($v, $k) => $data['raw'][$k] ?? false ? (string) $v : $this->quote($v), $data['values'], array_keys($data['values'])))
        );

        try {
            if ($data['raw'] !== []) {
                $response = $this->scripted($sql, 'set() sem escape');
                $this->affectedRows = $response['affected'] ?? 1;
                $this->record('insert', $table, $sql, $data['values'], true, true);

                return true;
            }

            if (!isset($this->tables[$table])) {
                throw UnprogrammedQueryException::undeclaredTable($table, $sql);
            }

            $row = $data['values'];
            $this->assertColumns($table, $row);

            if (in_array('id', $this->tables[$table]['columns'], true) && !isset($row['id'])) {
                $row['id'] = ++$this->tables[$table]['auto'];
            } elseif (isset($row['id']) && is_numeric($row['id'])) {
                $this->tables[$table]['auto'] = max($this->tables[$table]['auto'], (int) $row['id']);
            }

            $this->tables[$table]['rows'][] = $this->normalizeRow($table, $row);
            $this->insertId     = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
            $this->affectedRows = 1;
            $this->record('insert', $table, $sql, $row, true, false);

            return true;
        } finally {
            $this->resetBuilder();
        }
    }

    /**
     * @param list<array<string, mixed>> $set
     */
    public function insert_batch(string $table, array $set, ?bool $escape = null, int $batch_size = 100): int
    {
        $count = 0;
        foreach ($set as $row) {
            $this->insert($table, $row, $escape);
            $count++;
        }
        $this->affectedRows = $count;

        return $count;
    }

    public function update(string $table = '', mixed $set = null, mixed $where = null, ?int $limit = null): bool
    {
        if ($set !== null) {
            $this->set($set);
        }
        if ($where !== null) {
            $this->where($where);
        }
        if ($limit !== null) {
            $this->limit($limit);
        }

        $table = $table !== '' ? $table : (string) $this->from;
        $data  = $this->takeSet();
        $pairs = [];
        foreach ($data['values'] as $k => $v) {
            $pairs[] = $k . ' = ' . (($data['raw'][$k] ?? false) ? (string) $v : $this->quote($v));
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $pairs) . $this->compileTail(true);

        try {
            if ($this->complex !== [] || $data['raw'] !== []) {
                $reasons  = array_merge($this->complex, $data['raw'] !== [] ? ['set() sem escape'] : []);
                $response = $this->scripted($sql, implode(', ', array_unique($reasons)));
                $this->affectedRows = $response['affected'] ?? 0;
                $this->record('update', $table, $sql, $data['values'], true, true);

                return true;
            }

            if (!isset($this->tables[$table])) {
                throw UnprogrammedQueryException::undeclaredTable($table, $sql);
            }
            $this->assertColumns($table, $data['values']);

            $changed = 0;
            $matched = 0;
            foreach ($this->tables[$table]['rows'] as $i => $row) {
                if ($this->limit !== null && $matched >= $this->limit) {
                    break;
                }
                if (!$this->matches($row)) {
                    continue;
                }
                $matched++;
                $new = array_merge($row, $data['values']);
                if ($new != $row) {
                    $changed++;
                }
                $this->tables[$table]['rows'][$i] = $new;
            }

            $this->affectedRows = $changed;
            $this->record('update', $table, $sql, $data['values'], true, false);

            return true;
        } finally {
            $this->resetBuilder();
        }
    }

    public function delete(string $table = '', mixed $where = '', ?int $limit = null, bool $reset_data = true): bool
    {
        if ($where !== '' && $where !== null) {
            $this->where($where);
        }
        if ($limit !== null) {
            $this->limit($limit);
        }

        $table = $table !== '' ? $table : (string) $this->from;
        $sql   = 'DELETE FROM ' . $table . $this->compileTail(true);

        try {
            if ($this->complex !== []) {
                $response = $this->scripted($sql, implode(', ', array_unique($this->complex)));
                $this->affectedRows = $response['affected'] ?? 0;
                $this->record('delete', $table, $sql, null, true, true);

                return true;
            }

            if (!isset($this->tables[$table])) {
                throw UnprogrammedQueryException::undeclaredTable($table, $sql);
            }

            $kept    = [];
            $removed = 0;
            foreach ($this->tables[$table]['rows'] as $row) {
                if (($this->limit === null || $removed < $this->limit) && $this->matches($row)) {
                    $removed++;
                    continue;
                }
                $kept[] = $row;
            }

            $this->tables[$table]['rows'] = $kept;
            $this->affectedRows = $removed;
            $this->record('delete', $table, $sql, null, true, false);

            return true;
        } finally {
            $this->resetBuilder();
        }
    }

    /**
     * SQL cru — sempre modo roteiro. Leitura (SELECT/SHOW/DESCRIBE/EXPLAIN) devolve FakeResult;
     * escrita devolve true.
     *
     * @param array<int, mixed>|false $binds
     */
    public function query(string $sql, array|false $binds = false, ?bool $return_object = null): FakeResult|bool
    {
        if (is_array($binds)) {
            $values = array_values($binds);
            $i      = 0;
            $sql    = preg_replace_callback('/\?/', function () use (&$i, $values) {
                return array_key_exists($i, $values) ? $this->quote($values[$i++]) : '?';
            }, $sql) ?? $sql;
        }

        $isRead   = (bool) preg_match('/^\s*\(?\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN|WITH)\b/i', $sql);
        $response = $this->scripted($sql, 'SQL cru');
        $this->record('query', null, $sql, null, !$isRead, true);

        if ($isRead) {
            return new FakeResult(is_int($response['rows']) ? [] : $response['rows']);
        }

        $this->affectedRows = $response['affected'] ?? 0;

        return true;
    }

    public function simple_query(string $sql): FakeResult|bool
    {
        return $this->query($sql);
    }

    public function affected_rows(): int
    {
        return $this->affectedRows;
    }

    public function insert_id(): int
    {
        return $this->insertId;
    }

    public function last_query(): string
    {
        return $this->lastQuery;
    }

    /** @return array{code: int, message: string} */
    public function error(): array
    {
        return ['code' => 0, 'message' => ''];
    }

    public function escape(mixed $value): string|int|float
    {
        return is_int($value) || is_float($value) ? $value : $this->quote($value);
    }

    public function escape_str(string|array $str, bool $like = false): string|array
    {
        if (is_array($str)) {
            return array_map(fn ($s) => $this->escape_str($s, $like), $str);
        }

        $escaped = addslashes($str);

        return $like ? str_replace(['%', '_'], ['\\%', '\\_'], $escaped) : $escaped;
    }

    public function escape_like_str(string|array $str): string|array
    {
        return $this->escape_str($str, true);
    }

    public function trans_start(bool $test_mode = false): bool
    {
        return true;
    }

    public function trans_complete(): bool
    {
        return true;
    }

    public function trans_begin(bool $test_mode = false): bool
    {
        return true;
    }

    public function trans_commit(): bool
    {
        return true;
    }

    public function trans_rollback(): bool
    {
        return true;
    }

    public function trans_status(): bool
    {
        return true;
    }

    public function trans_off(): void
    {
    }

    // =============================================================================================
    // Internos
    // =============================================================================================

    private function aggregate(string $fn, string $select, string $alias): self
    {
        $this->complex[] = 'select_' . strtolower($fn);
        $this->select[]  = $fn . '(' . $select . ') AS ' . ($alias !== '' ? $alias : $select);

        return $this;
    }

    private function addWhere(string $conj, mixed $key, mixed $value, ?bool $escape, bool $hasValue): self
    {
        if (is_array($key) || is_object($key)) {
            foreach ((array) $key as $k => $v) {
                is_int($k) ? $this->addRawWhere($conj, (string) $v) : $this->addWhere($conj, $k, $v, $escape, true);
            }

            return $this;
        }

        $key = trim(str_replace('`', '', (string) $key));

        if (!$hasValue || $value === null) {
            if (preg_match('/^([\w.]+)$/', $key, $m) && $hasValue) {
                return $this->pushWhere(['kind' => 'cmp', 'conj' => $conj, 'field' => $m[1], 'op' => 'IS NULL', 'value' => null]);
            }
            if (preg_match('/^([\w.]+)\s+(IS NULL|IS NOT NULL)$/i', $key, $m)) {
                return $this->pushWhere(['kind' => 'cmp', 'conj' => $conj, 'field' => $m[1], 'op' => strtoupper($m[2]), 'value' => null]);
            }
            if (preg_match('/^([\w.]+)\s*(!=|<>)$/', $key, $m) && $hasValue) {
                return $this->pushWhere(['kind' => 'cmp', 'conj' => $conj, 'field' => $m[1], 'op' => 'IS NOT NULL', 'value' => null]);
            }

            return $this->addRawWhere($conj, $key);
        }

        if ($escape === false && is_string($value) && !is_numeric($value)) {
            return $this->addRawWhere($conj, $key . ' ' . $value);
        }

        if (preg_match('/^([\w.]+)$/', $key, $m)) {
            return $this->pushWhere(['kind' => 'cmp', 'conj' => $conj, 'field' => $m[1], 'op' => '=', 'value' => $value]);
        }
        if (preg_match('/^([\w.]+)\s*(!=|<>|>=|<=|=|>|<)$/', $key, $m)) {
            return $this->pushWhere(['kind' => 'cmp', 'conj' => $conj, 'field' => $m[1], 'op' => $m[2] === '<>' ? '!=' : $m[2], 'value' => $value]);
        }

        return $this->addRawWhere($conj, $key . ' ' . $this->quote($value));
    }

    private function addRawWhere(string $conj, string $sql): self
    {
        $this->complex[] = 'where em texto livre';

        return $this->pushWhere(['kind' => 'raw', 'conj' => $conj, 'sql' => $sql]);
    }

    private function addIn(string $conj, ?string $key, mixed $values, bool $not): self
    {
        if ($key === null || $values === null) {
            return $this;
        }

        return $this->pushWhere([
            'kind'  => $not ? 'notin' : 'in',
            'conj'  => $conj,
            'field' => trim(str_replace('`', '', $key)),
            'value' => array_values((array) $values),
        ]);
    }

    private function addLike(string $conj, mixed $field, string $match, string $side, bool $not): self
    {
        if (is_array($field)) {
            foreach ($field as $f => $m) {
                $this->addLike($conj, $f, (string) $m, $side, $not);
            }

            return $this;
        }

        return $this->pushWhere([
            'kind'  => $not ? 'notlike' : 'like',
            'conj'  => $conj,
            'field' => trim(str_replace('`', '', (string) $field)),
            'value' => $match,
            'side'  => strtolower($side),
        ]);
    }

    /** @param array<string, mixed> $where */
    private function pushWhere(array $where): self
    {
        if ($this->openGroup) {
            $where['conj']   = '';
            $this->openGroup = false;
        }
        $this->wheres[] = $where;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filter(string $table, string $sql): array
    {
        if (!isset($this->tables[$table])) {
            throw UnprogrammedQueryException::undeclaredTable($table, $sql);
        }

        return array_values(array_filter($this->tables[$table]['rows'], fn (array $row) => $this->matches($row)));
    }

    /**
     * Avalia as condições respeitando a precedência do SQL (AND antes de OR).
     *
     * @param array<string, mixed> $row
     */
    private function matches(array $row): bool
    {
        if ($this->wheres === []) {
            return true;
        }

        $groups  = [[]];
        foreach ($this->wheres as $i => $where) {
            if ($i > 0 && $where['conj'] === 'OR') {
                $groups[] = [];
            }
            $groups[count($groups) - 1][] = $where;
        }

        foreach ($groups as $group) {
            $all = true;
            foreach ($group as $where) {
                if (!$this->evaluate($where, $row)) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $where
     * @param array<string, mixed> $row
     */
    private function evaluate(array $where, array $row): bool
    {
        $value = $row[$this->column($where['field'])] ?? null;

        switch ($where['kind']) {
            case 'in':
            case 'notin':
                if ($value === null) {
                    return false;
                }
                $found = false;
                foreach ($where['value'] as $candidate) {
                    if (self::compare($value, '=', $candidate)) {
                        $found = true;
                        break;
                    }
                }

                return $where['kind'] === 'in' ? $found : !$found;

            case 'like':
            case 'notlike':
                if ($value === null) {
                    return false;
                }
                $needle   = mb_strtolower((string) $where['value']);
                $haystack = mb_strtolower((string) $value);
                $found    = match ($where['side']) {
                    'before' => str_ends_with($haystack, $needle),
                    'after'  => str_starts_with($haystack, $needle),
                    'none'   => $haystack === $needle,
                    default  => str_contains($haystack, $needle),
                };

                return $where['kind'] === 'like' ? $found : !$found;

            default:
                return self::compare($value, $where['op'], $where['value']);
        }
    }

    private static function compare(mixed $a, string $op, mixed $b): bool
    {
        if ($op === 'IS NULL') {
            return $a === null;
        }
        if ($op === 'IS NOT NULL') {
            return $a !== null;
        }
        if ($a === null || $b === null) {
            return false;
        }

        $a = is_bool($a) ? (int) $a : $a;
        $b = is_bool($b) ? (int) $b : $b;

        $cmp = is_numeric($a) && is_numeric($b)
            ? ((float) $a <=> (float) $b)
            : strcmp(mb_strtolower((string) $a), mb_strtolower((string) $b));

        return match ($op) {
            '='  => $cmp === 0,
            '!=' => $cmp !== 0,
            '>'  => $cmp > 0,
            '<'  => $cmp < 0,
            '>=' => $cmp >= 0,
            '<=' => $cmp <= 0,
            default => false,
        };
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sort(array $rows): array
    {
        if ($this->orderBy === []) {
            return $rows;
        }

        usort($rows, function (array $x, array $y): int {
            foreach ($this->orderBy as $order) {
                $column = $this->column($order['field']);
                $a      = $x[$column] ?? null;
                $b      = $y[$column] ?? null;

                if ($a === $b) {
                    continue;
                }
                if ($a === null || $b === null) {
                    $cmp = $a === null ? -1 : 1; // NULL primeiro em ASC (MySQL)
                } elseif (is_numeric($a) && is_numeric($b)) {
                    $cmp = (float) $a <=> (float) $b;
                } else {
                    $cmp = strcmp(mb_strtolower((string) $a), mb_strtolower((string) $b));
                }

                if ($cmp !== 0) {
                    return $order['dir'] === 'DESC' ? -$cmp : $cmp;
                }
            }

            return 0;
        });

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function project(array $rows): array
    {
        if ($this->select === [] || in_array('*', $this->select, true)) {
            return $rows;
        }

        $map = [];
        foreach ($this->select as $column) {
            if (str_ends_with($column, '.*')) {
                return $rows;
            }
            if (preg_match('/^(.+?)\s+(?:AS\s+)?([\w]+)$/i', $column, $m)) {
                $map[$m[2]] = $this->column($m[1]);
            } else {
                $map[$this->column($column)] = $this->column($column);
            }
        }

        return array_map(static function (array $row) use ($map): array {
            $out = [];
            foreach ($map as $alias => $column) {
                $out[$alias] = $row[$column] ?? null;
            }

            return $out;
        }, $rows);
    }

    private function column(string $field): string
    {
        $field = str_replace('`', '', $field);
        $dot   = strrpos($field, '.');

        return $dot === false ? $field : substr($field, $dot + 1);
    }

    /**
     * @return array{rows: list<array<string, mixed>|object>|int, affected: int|null}
     */
    private function scripted(string $sql, string $reason): array
    {
        $this->lastQuery = $sql;

        for ($i = count($this->responses) - 1; $i >= 0; $i--) {
            $response = $this->responses[$i];
            if ($this->patternMatches($response['pattern'], $sql)) {
                return $response;
            }
        }

        throw UnprogrammedQueryException::for($sql, $reason);
    }

    private function patternMatches(string $pattern, string $sql): bool
    {
        if (strlen($pattern) > 2 && in_array($pattern[0], ['/', '#', '~'], true)) {
            $result = @preg_match($pattern, $sql);
            if ($result !== false) {
                return $result === 1;
            }
        }

        return stripos($sql, $pattern) !== false;
    }

    private function compileSelect(string $table): string
    {
        $distinct = in_array('DISTINCT', $this->extraSql, true) ? 'DISTINCT ' : '';
        $columns  = $this->select === [] ? '*' : implode(', ', $this->select);

        return 'SELECT ' . $distinct . $columns . ' FROM ' . $table . $this->compileTail(false, true);
    }

    private function compileTail(bool $forWrite, bool $withOrderLimit = false): string
    {
        $sql = '';

        foreach ($this->extraSql as $extra) {
            if (str_contains($extra, 'JOIN ')) {
                $sql .= ' ' . $extra;
            }
        }

        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        foreach ($this->extraSql as $extra) {
            if (str_starts_with($extra, 'GROUP BY') || str_starts_with($extra, 'HAVING')) {
                $sql .= ' ' . $extra;
            }
        }

        if ($withOrderLimit || $forWrite) {
            if ($this->orderBy !== []) {
                $sql .= ' ORDER BY ' . implode(', ', array_map(static fn ($o) => $o['field'] . ' ' . $o['dir'], $this->orderBy));
            } elseif (in_array('ORDER BY RAND()', $this->extraSql, true)) {
                $sql .= ' ORDER BY RAND()';
            }
            if ($this->limit !== null) {
                $sql .= ' LIMIT ' . ($this->offset ? $this->offset . ', ' : '') . $this->limit;
            }
        }

        return $sql;
    }

    private function compileWheres(): string
    {
        $sql   = '';
        $first = true;

        foreach ($this->wheres as $where) {
            if ($where['kind'] === 'close') {
                $sql  .= ')';
                continue;
            }

            if (!$first && $where['conj'] !== '') {
                $sql .= ' ' . $where['conj'] . ' ';
            }
            $first = false;

            $sql .= match ($where['kind']) {
                'open'    => ($where['not'] ? 'NOT ' : '') . '(',
                'raw'     => $where['sql'],
                'in'      => $where['field'] . ' IN (' . implode(', ', array_map(fn ($v) => $this->quote($v), $where['value'])) . ')',
                'notin'   => $where['field'] . ' NOT IN (' . implode(', ', array_map(fn ($v) => $this->quote($v), $where['value'])) . ')',
                'like'    => $where['field'] . " LIKE " . $this->quote($this->likePattern($where)),
                'notlike' => $where['field'] . " NOT LIKE " . $this->quote($this->likePattern($where)),
                default   => in_array($where['op'], ['IS NULL', 'IS NOT NULL'], true)
                    ? $where['field'] . ' ' . $where['op']
                    : $where['field'] . ' ' . $where['op'] . ' ' . $this->quote($where['value']),
            };

            if ($where['kind'] === 'open') {
                $first = true;
            }
        }

        return $sql;
    }

    /** @param array<string, mixed> $where */
    private function likePattern(array $where): string
    {
        return match ($where['side']) {
            'before' => '%' . $where['value'],
            'after'  => $where['value'] . '%',
            'none'   => (string) $where['value'],
            default  => '%' . $where['value'] . '%',
        };
    }

    private function quote(mixed $value): string
    {
        return match (true) {
            $value === null  => 'NULL',
            is_bool($value)  => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            is_array($value) => '(' . implode(', ', array_map(fn ($v) => $this->quote($v), $value)) . ')',
            default          => "'" . addslashes((string) $value) . "'",
        };
    }

    /**
     * @return array{values: array<string, mixed>, raw: array<string, true>}
     */
    private function takeSet(): array
    {
        $values = [];
        $raw    = [];
        foreach ($this->set as $key => $entry) {
            $values[$key] = $entry['value'];
            if (!$entry['escape']) {
                $raw[$key] = true;
            }
        }

        return ['values' => $values, 'raw' => $raw];
    }

    /** @param array<string, mixed> $row */
    private function assertColumns(string $table, array $row): void
    {
        foreach (array_keys($row) as $column) {
            if (!in_array($column, $this->tables[$table]['columns'], true)) {
                throw new TestkitError(sprintf(
                    '[perfex-module-testkit] Coluna "%s" não existe na tabela "%s" (colunas declaradas: %s). No MySQL isso seria "Unknown column".',
                    $column,
                    $table,
                    implode(', ', $this->tables[$table]['columns'])
                ));
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(string $table, array $row): array
    {
        $normalized = [];
        foreach ($this->tables[$table]['columns'] as $column) {
            $normalized[$column] = $row[$column] ?? null;
        }

        return $normalized;
    }

    /** @param array<string, mixed>|null $data */
    private function record(string $type, ?string $table, string $sql, ?array $data, bool $write, bool $scripted): void
    {
        $this->lastQuery = $sql;
        $this->log[]     = [
            'type'     => $type,
            'table'    => $table,
            'sql'      => $sql,
            'data'     => $data,
            'write'    => $write,
            'scripted' => $scripted,
        ];
    }

    private function resetBuilder(): void
    {
        $this->select    = [];
        $this->from      = null;
        $this->wheres    = [];
        $this->orderBy   = [];
        $this->limit     = null;
        $this->offset    = 0;
        $this->set       = [];
        $this->extraSql  = [];
        $this->complex   = [];
        $this->openGroup = false;
    }
}
