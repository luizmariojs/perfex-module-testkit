<?php

declare(strict_types=1);

namespace PerfexTestkit;

/**
 * Substituto de $CI->load (CI_Loader) para models, libraries e helpers.
 *
 * Resolução:
 * - dublê registrado via Testkit::double() (pelo nome da propriedade ou pelo caminho pedido) tem prioridade;
 * - `<modulo>/<nome>` em que <modulo> é o módulo em teste resolve para o arquivo do módulo
 *   (models/Nome_model.php, libraries/Nome.php, helpers/nome_helper.php — com subdiretórios);
 * - qualquer outro model/library lança UnresolvedComponentException;
 * - helpers nativos (sem prefixo do módulo, ex.: `url`) são aceitos sem efeito: suas funções vêm dos stubs.
 */
final class Loader
{
    /** @var array<string, true> */
    private static array $loadedHelpers = [];

    /**
     * @param string|array<int|string, string> $model
     */
    public function model(string|array $model, string $name = '', mixed $db_conn = false): self
    {
        if (is_array($model)) {
            foreach ($model as $key => $value) {
                is_int($key) ? $this->model($value) : $this->model($key, $value);
            }

            return $this;
        }

        $property = $name !== '' ? $name : strtolower(basename($model));
        $this->attach('model', $model, $property, 'models', static fn (string $class) => new $class());

        return $this;
    }

    /**
     * @param string|array<int, string> $library
     */
    public function library(string|array $library, mixed $params = null, ?string $object_name = null): self
    {
        if (is_array($library)) {
            foreach ($library as $item) {
                $this->library($item, $params);
            }

            return $this;
        }

        $property = $object_name ?? strtolower(basename($library));
        $this->attach('library', $library, $property, 'libraries', static fn (string $class) => $params === null ? new $class() : new $class($params));

        return $this;
    }

    /**
     * @param string|array<int, string> $helpers
     */
    public function helper(string|array $helpers): self
    {
        foreach ((array) $helpers as $helper) {
            $relative = $this->moduleRelative($helper);
            if ($relative === null) {
                continue; // helper nativo do Perfex/CI: funções fornecidas pelos stubs
            }

            $file = State::$moduleRoot . '/helpers/' . preg_replace('/_helper$/', '', $relative) . '_helper.php';
            if (!is_file($file)) {
                throw UnresolvedComponentException::for('helper', $helper, 'arquivo não encontrado: ' . $file);
            }

            if (!isset(self::$loadedHelpers[$file])) {
                require_once $file;
                self::$loadedHelpers[$file] = true;
            }
        }

        return $this;
    }

    /**
     * @param callable(string): object $factory
     */
    private function attach(string $type, string $path, string $property, string $dir, callable $factory): void
    {
        $instance = &State::instance();

        if (isset(State::$doubles[$property]) || isset(State::$doubles[$path])) {
            $instance->{$property} = State::$doubles[$property] ?? State::$doubles[$path];

            return;
        }

        if (isset($instance->{$property})) {
            return; // já carregado (mesmo comportamento do CI3)
        }

        $relative = $this->moduleRelative($path);
        if ($relative === null) {
            throw UnresolvedComponentException::for(
                $type,
                $path,
                'não pertence ao módulo em teste. Registre um dublê com Testkit::double(\'' . $property . '\', $objeto).'
            );
        }

        $segments  = explode('/', $relative);
        $className = ucfirst(array_pop($segments));
        $file      = State::$moduleRoot . '/' . $dir . '/' . ($segments ? implode('/', $segments) . '/' : '') . $className . '.php';

        if (!is_file($file)) {
            throw UnresolvedComponentException::for($type, $path, 'arquivo não encontrado: ' . $file);
        }

        require_once $file;

        if (!class_exists($className, false)) {
            throw UnresolvedComponentException::for($type, $path, 'o arquivo ' . $file . ' não declara a classe ' . $className);
        }

        $instance->{$property} = $factory($className);
    }

    /**
     * Caminho relativo ao módulo em teste, ou null se o componente não é do módulo.
     */
    private function moduleRelative(string $path): ?string
    {
        $path   = trim(str_replace('\\', '/', $path), '/');
        $module = basename((string) State::$moduleRoot);

        if (str_starts_with(strtolower($path), strtolower($module) . '/')) {
            return substr($path, strlen($module) + 1);
        }

        return null;
    }
}
