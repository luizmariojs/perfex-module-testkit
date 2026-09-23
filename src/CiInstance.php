<?php

declare(strict_types=1);

namespace PerfexTestkit;

/**
 * Instância CI padrão (antes de algum controller ser construído no teste).
 *
 * Aceita propriedades dinâmicas, como o super-objeto do CodeIgniter.
 */
#[\AllowDynamicProperties]
final class CiInstance
{
    public Loader $load;

    public Database\FakeDatabase $db;

    public Input $input;

    public function __construct()
    {
        $this->load  = new Loader();
        $this->db    = State::db();
        $this->input = new Input();
    }
}
