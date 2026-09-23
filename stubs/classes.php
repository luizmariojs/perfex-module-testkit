<?php

/**
 * Stubs das classes base do CodeIgniter 3 / Perfex CRM estendidas pelos módulos.
 *
 * Carregado apenas por \PerfexTestkit\Testkit::boot(). Cada classe só é declarada se ainda não existir.
 */

use PerfexTestkit\Input;
use PerfexTestkit\State;

if (!class_exists('CI_Controller', false)) {
    /**
     * No CI3 o controller É a instância (get_instance()). Ao ser construído, passa a ser a instância
     * atual do teste, herdando db, load e os componentes já carregados.
     */
    #[\AllowDynamicProperties]
    class CI_Controller
    {
        public function __construct()
        {
            State::becomeInstance($this);

            if (!isset($this->input)) {
                $this->input = new Input();
            }
        }

        public static function &get_instance()
        {
            return State::instance();
        }
    }
}

if (!class_exists('App_Controller', false)) {
    #[\AllowDynamicProperties]
    class App_Controller extends CI_Controller
    {
    }
}

if (!class_exists('AdminController', false)) {
    /** Sem checagem de login/sessão no kit. */
    #[\AllowDynamicProperties]
    class AdminController extends App_Controller
    {
    }
}

if (!class_exists('ClientsController', false)) {
    #[\AllowDynamicProperties]
    class ClientsController extends App_Controller
    {
    }
}

if (!class_exists('CI_Model', false)) {
    /** Como no CI3: propriedades inexistentes são buscadas na instância CI ($this->db, $this->load...). */
    #[\AllowDynamicProperties]
    class CI_Model
    {
        public function __construct()
        {
        }

        public function __get($key)
        {
            return get_instance()->$key;
        }
    }
}

if (!class_exists('App_Model', false)) {
    #[\AllowDynamicProperties]
    class App_Model extends CI_Model
    {
        public function __construct()
        {
            parent::__construct();
        }
    }
}

if (!class_exists('App_module_migration', false)) {
    /** Migrations de módulo: $this->ci e acesso delegado à instância CI ($this->db...). */
    #[\AllowDynamicProperties]
    class App_module_migration
    {
        protected $ci;

        public function __construct()
        {
            $this->ci = &get_instance();
        }

        public function __get($key)
        {
            return get_instance()->$key;
        }

        public function down()
        {
        }
    }
}
