<?php

declare(strict_types=1);

namespace PerfexTestkit;

/**
 * Substituto do objeto devolvido por hooks() no Perfex (ações e filtros com prioridade).
 *
 * Registra os callbacks para asserção e permite dispará-los no teste. O callback não é validado no
 * registro (o Perfex aceita nomes de funções declaradas depois, inclusive dentro de function_exists);
 * só precisa ser chamável quando a ação/filtro é disparado.
 */
final class Hooks
{
    /** @var array<string, array<int, list<array{callback: mixed, accepted_args: int}>>> */
    private array $actions = [];

    /** @var array<string, array<int, list<array{callback: mixed, accepted_args: int}>>> */
    private array $filters = [];

    /** @var list<array{tag: string, args: list<mixed>}> */
    private array $fired = [];

    /** @var list<array{type: string, tag: string, callback: mixed, priority: int, accepted_args: int}> registros em ordem */
    private array $registrations = [];

    public function add_action(string $tag, mixed $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $this->actions[$tag][$priority][] = ['callback' => $callback, 'accepted_args' => $accepted_args];
        $this->registrations[] = ['type' => 'action', 'tag' => $tag, 'callback' => $callback, 'priority' => $priority, 'accepted_args' => $accepted_args];

        return true;
    }

    public function add_filter(string $tag, mixed $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $this->filters[$tag][$priority][] = ['callback' => $callback, 'accepted_args' => $accepted_args];
        $this->registrations[] = ['type' => 'filter', 'tag' => $tag, 'callback' => $callback, 'priority' => $priority, 'accepted_args' => $accepted_args];

        return true;
    }

    public function do_action(string $tag, mixed ...$args): void
    {
        $this->fired[] = ['tag' => $tag, 'args' => $args];

        foreach ($this->sorted($this->actions[$tag] ?? []) as $entry) {
            ($entry['callback'])(...array_slice($args, 0, $entry['accepted_args']));
        }
    }

    public function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->sorted($this->filters[$tag] ?? []) as $entry) {
            $value = ($entry['callback'])(...array_slice([$value, ...$args], 0, max(1, $entry['accepted_args'])));
        }

        return $value;
    }

    public function has_action(string $tag): bool
    {
        return !empty($this->actions[$tag]);
    }

    public function has_filter(string $tag): bool
    {
        return !empty($this->filters[$tag]);
    }

    public function remove_action(string $tag, mixed $callback, int $priority = 10): bool
    {
        return $this->remove($this->actions, $tag, $callback, $priority);
    }

    public function remove_filter(string $tag, mixed $callback, int $priority = 10): bool
    {
        return $this->remove($this->filters, $tag, $callback, $priority);
    }

    /** @return list<array{type: string, tag: string, callback: mixed, priority: int, accepted_args: int}> */
    public function registrations(): array
    {
        return $this->registrations;
    }

    /**
     * Reaplica registros capturados anteriormente (usado por Testkit::load() nas cargas seguintes).
     *
     * @param list<array{type: string, tag: string, callback: mixed, priority: int, accepted_args: int}> $registrations
     */
    public function replay(array $registrations): void
    {
        foreach ($registrations as $r) {
            $r['type'] === 'filter'
                ? $this->add_filter($r['tag'], $r['callback'], $r['priority'], $r['accepted_args'])
                : $this->add_action($r['tag'], $r['callback'], $r['priority'], $r['accepted_args']);
        }
    }

    /** @return list<array{tag: string, args: list<mixed>}> ações disparadas via do_action, em ordem */
    public function fired(): array
    {
        return $this->fired;
    }

    /**
     * @param array<int, list<array{callback: mixed, accepted_args: int}>> $byPriority
     * @return list<array{callback: mixed, accepted_args: int}>
     */
    private function sorted(array $byPriority): array
    {
        ksort($byPriority);

        return array_merge(...array_values($byPriority ?: [[]]));
    }

    /**
     * @param array<string, array<int, list<array{callback: mixed, accepted_args: int}>>> $registry
     */
    private function remove(array &$registry, string $tag, mixed $callback, int $priority): bool
    {
        foreach ($registry[$tag][$priority] ?? [] as $i => $entry) {
            if ($entry['callback'] === $callback) {
                unset($registry[$tag][$priority][$i]);
                $registry[$tag][$priority] = array_values($registry[$tag][$priority]);

                return true;
            }
        }

        return false;
    }
}
