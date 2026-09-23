<?php

/**
 * Executado em processo separado pelos autotestes de inicialização (sem o bootstrap dos testes).
 * Uso: php boot_probe.php <modo>   modo = boot | redeclare
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$mode = $argv[1] ?? 'boot';
$out  = [];

if ($mode === 'redeclare') {
    // Helper "do módulo" define _l antes do boot: a versão do módulo deve prevalecer
    function _l($line, $label = '', $log_errors = true)
    {
        return 'modulo:' . $line;
    }
}

$probe = static fn () => [
    'get_option'    => function_exists('get_option'),
    'get_instance'  => function_exists('get_instance'),
    'hooks'         => function_exists('hooks'),
    'BASEPATH'      => defined('BASEPATH'),
    'FCPATH'        => defined('FCPATH'),
    'CI_Controller' => class_exists('CI_Controller', false),
    'App_Model'     => class_exists('App_Model', false),
];

$out['before'] = $probe();
\PerfexTestkit\Testkit::boot(__DIR__ . '/sample_module');
$out['after']  = $probe();
$out['fcpath'] = FCPATH;
$out['l']      = _l('chave');

echo json_encode($out);
