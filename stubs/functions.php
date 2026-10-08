<?php

/**
 * Stubs das funções globais do Perfex CRM / CodeIgniter 3 usadas pelos módulos.
 *
 * Carregado apenas por \PerfexTestkit\Testkit::boot(). Cada função só é definida se ainda não existir,
 * para que helpers reais do módulo (carregados antes) prevaleçam. Todo estado vive em
 * \PerfexTestkit\State e é zerado entre testes.
 *
 * Sem declare(strict_types): o Perfex é tolerante com tipos, e os stubs também.
 */

use PerfexTestkit\State;

// -------------------------------------------------------------------------------------------------
// Instância e hooks
// -------------------------------------------------------------------------------------------------

if (!function_exists('get_instance')) {
    /**
     * CodeIgniter: system/core/CodeIgniter.php — devolve o super-objeto por referência.
     */
    function &get_instance()
    {
        return State::instance();
    }
}

if (!function_exists('hooks')) {
    /**
     * Perfex: application/helpers/hooks_helper.php — objeto de ações e filtros.
     */
    function hooks()
    {
        return State::hooks();
    }
}

// -------------------------------------------------------------------------------------------------
// Options (Perfex: application/helpers/options_helper.php)
// -------------------------------------------------------------------------------------------------

if (!function_exists('get_option')) {
    /** Option inexistente devolve string vazia, como no Perfex. */
    function get_option($name)
    {
        return State::$options[$name] ?? '';
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null)
    {
        State::$options[$name] = (string) $value;

        return true;
    }
}

if (!function_exists('add_option')) {
    /** Não sobrescreve option existente (devolve false), como no Perfex. */
    function add_option($name, $value = '', $autoload = 1)
    {
        if (array_key_exists($name, State::$options)) {
            return false;
        }
        State::$options[$name] = (string) $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($name)
    {
        $existed = array_key_exists($name, State::$options);
        unset(State::$options[$name]);

        return $existed;
    }
}

// -------------------------------------------------------------------------------------------------
// Tradução, banco, URLs
// -------------------------------------------------------------------------------------------------

if (!function_exists('_l')) {
    /**
     * Perfex: application/helpers/general_helper.php. No kit devolve a própria chave; com $label,
     * aplica sprintf (como o Perfex faz sobre o texto traduzido).
     */
    function _l($line, $label = '', $log_errors = true)
    {
        if ($label === '' || $label === null) {
            return (string) $line;
        }

        try {
            return sprintf((string) $line, ...(is_array($label) ? array_values($label) : [$label]));
        } catch (\ValueError|\ArgumentCountError $e) {
            return (string) $line;
        }
    }
}

if (!function_exists('db_prefix')) {
    /** Prefixo das tabelas: `tbl`. */
    function db_prefix()
    {
        return 'tbl';
    }
}

if (!function_exists('base_url')) {
    /** `http://localhost/<uri>` (base configurável em Testkit::boot(..., ['base_url' => ...])). */
    function base_url($uri = '', $protocol = null)
    {
        return State::$config['base_url'] . ltrim((string) $uri, '/');
    }
}

if (!function_exists('site_url')) {
    /** `http://localhost/<uri>`. */
    function site_url($uri = '', $protocol = null)
    {
        return State::$config['base_url'] . ltrim((string) $uri, '/');
    }
}

if (!function_exists('admin_url')) {
    /** `http://localhost/admin/<url>`. */
    function admin_url($url = '')
    {
        return State::$config['base_url'] . 'admin/' . ltrim((string) $url, '/');
    }
}

if (!function_exists('module_dir_path')) {
    /**
     * Perfex: caminho no disco de um módulo. O módulo em teste resolve para a sua raiz real; outros
     * módulos para APP_MODULES_PATH/<modulo>/.
     */
    function module_dir_path($module, $concat = '')
    {
        $base = $module === basename((string) State::$moduleRoot)
            ? State::$moduleRoot . '/'
            : APP_MODULES_PATH . $module . '/';

        return $base . ltrim((string) $concat, '/');
    }
}

// -------------------------------------------------------------------------------------------------
// Formatação (saída fixa e documentada — não depende de configurações do Perfex)
// -------------------------------------------------------------------------------------------------

if (!function_exists('app_format_money')) {
    /**
     * `R$ 1.234,56`: símbolo da moeda (objeto com ->symbol, ou string) + espaço + número com 2 casas,
     * vírgula decimal e ponto de milhar. Sem símbolo quando $excludeSymbol ou moeda vazia.
     */
    function app_format_money($amount, $currency, $excludeSymbol = false)
    {
        $number = number_format((float) $amount, 2, ',', '.');
        $symbol = is_object($currency) ? (string) ($currency->symbol ?? '') : (string) $currency;

        return ($excludeSymbol || $symbol === '') ? $number : $symbol . ' ' . $number;
    }
}

if (!function_exists('format_invoice_number')) {
    /**
     * `INV-000123`. Aceita o id ou um objeto com ->number (ou ->id).
     */
    function format_invoice_number($id)
    {
        $number = is_object($id) ? ($id->number ?? $id->id ?? 0) : $id;

        return 'INV-' . str_pad((string) (int) $number, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('_d')) {
    /** Data no formato `d/m/Y`; vazio para data vazia ou inválida. */
    function _d($date)
    {
        if ($date === null || $date === '' || str_starts_with((string) $date, '0000-00-00')) {
            return '';
        }
        $time = strtotime((string) $date);

        return $time === false ? '' : date('d/m/Y', $time);
    }
}

// -------------------------------------------------------------------------------------------------
// Usuário e permissões (configuráveis por Testkit::actingAs())
// -------------------------------------------------------------------------------------------------

if (!function_exists('get_staff_user_id')) {
    /** Id do staff definido por Testkit::actingAs(), ou false (visitante). */
    function get_staff_user_id()
    {
        return State::$staffId;
    }
}

if (!function_exists('is_staff_logged_in')) {
    function is_staff_logged_in()
    {
        return State::$staffId !== false;
    }
}

if (!function_exists('is_admin')) {
    function is_admin($staffid = '')
    {
        return State::$isAdmin;
    }
}

if (!function_exists('has_permission')) {
    /** Admin tem tudo. Sem $can: verdadeiro se houver qualquer capability na feature. */
    function has_permission($permission, $staffid = '', $can = '')
    {
        if (State::$isAdmin) {
            return true;
        }
        $capabilities = State::$permissions[$permission] ?? [];

        return $can === '' ? $capabilities !== [] : in_array($can, $capabilities, true);
    }
}

if (!function_exists('staff_can')) {
    /** Admin tem tudo; senão verifica a capability na feature. */
    function staff_can($capability, $feature = null, $staff_id = '')
    {
        if (State::$isAdmin) {
            return true;
        }

        return $feature !== null && in_array($capability, State::$permissions[$feature] ?? [], true);
    }
}

// -------------------------------------------------------------------------------------------------
// Efeitos capturados
// -------------------------------------------------------------------------------------------------

if (!function_exists('log_activity')) {
    function log_activity($description, $staffid = null)
    {
        State::$activity[] = ['description' => (string) $description, 'staffid' => $staffid];
    }
}

if (!function_exists('set_alert')) {
    function set_alert($type, $message)
    {
        State::$alerts[] = ['type' => (string) $type, 'message' => (string) $message];
    }
}

// -------------------------------------------------------------------------------------------------
// Navegação e erros: interrompem o fluxo com exceções do kit (stubs/classes.php), sem encerrar o PHPUnit
// -------------------------------------------------------------------------------------------------

if (!function_exists('redirect')) {
    /** CodeIgniter: system/helpers/url_helper.php — lança TestRedirect com o destino. */
    function redirect($uri = '', $method = 'auto', $code = null)
    {
        throw new TestRedirect((string) $uri);
    }
}

if (!function_exists('show_404')) {
    /** CodeIgniter: system/core/Common.php — lança TestNotFound. */
    function show_404($page = '', $log_error = true)
    {
        throw new TestNotFound('404');
    }
}

if (!function_exists('access_denied')) {
    /** Perfex: application/helpers/admin_helper.php — lança TestAccessDenied com a permissão. */
    function access_denied($permission = '')
    {
        throw new TestAccessDenied((string) $permission);
    }
}

// -------------------------------------------------------------------------------------------------
// Campos personalizados (Perfex: application/helpers/custom_fields_helper.php)
// -------------------------------------------------------------------------------------------------

if (!function_exists('get_custom_field_value')) {
    /** Valor definido por Testkit::customField(); ausente ou nulo devolve string vazia. */
    function get_custom_field_value($rel_id, $field_or_slug, $field_to, $format = true)
    {
        return State::$customFields[$field_or_slug][$rel_id] ?? '';
    }
}

// -------------------------------------------------------------------------------------------------
// Área do cliente (Perfex: application/helpers/clients_helper.php, themes_helper.php)
// -------------------------------------------------------------------------------------------------

if (!function_exists('is_client_logged_in')) {
    /** Conforme Testkit::actingAsContact(). */
    function is_client_logged_in()
    {
        return State::$contact !== false;
    }
}

if (!function_exists('get_client_user_id')) {
    /** Cliente do contato logado (false sem contato). */
    function get_client_user_id()
    {
        return State::$contact;
    }
}

if (!function_exists('has_contact_permission')) {
    /** Permissões definidas em Testkit::actingAsContact(). */
    function has_contact_permission($permission, $contact_id = '')
    {
        return in_array($permission, State::$contactPermissions, true);
    }
}

if (!function_exists('redirect_after_login_to_current_url')) {
    /** Perfex: guarda a URL na sessão — sem efeito nos testes. */
    function redirect_after_login_to_current_url()
    {
    }
}

if (!function_exists('add_theme_menu_item')) {
    /** Item consultável por Testkit::clientMenu(). */
    function add_theme_menu_item($slug, $item)
    {
        State::$clientMenu[$slug] = $item;
    }
}

// -------------------------------------------------------------------------------------------------
// Registro do módulo (Perfex: application/helpers/modules_helper.php)
// -------------------------------------------------------------------------------------------------

if (!function_exists('register_activation_hook')) {
    /** Sem efeito nos testes. */
    function register_activation_hook($module, $function)
    {
    }
}

if (!function_exists('register_deactivation_hook')) {
    /** Sem efeito nos testes. */
    function register_deactivation_hook($module, $function)
    {
    }
}

if (!function_exists('register_staff_capabilities')) {
    /** Acrescenta a permissão ao filtro staff_permissions, como o core. */
    function register_staff_capabilities($feature_id, $config, $name = null)
    {
        hooks()->add_filter('staff_permissions', function ($permissions) use ($feature_id, $config, $name) {
            $permissions[$feature_id] = array_merge(['name' => $name ?: $feature_id], $config);

            return $permissions;
        });
    }
}

// -------------------------------------------------------------------------------------------------
// Render de views — saída fixa, sem tema, sessão nem banco
// -------------------------------------------------------------------------------------------------

if (!function_exists('init_head')) {
    /** Perfex: application/helpers/admin_helper.php — não imprime nada. */
    function init_head($aside = true)
    {
    }
}

if (!function_exists('init_tail')) {
    /** Perfex: application/helpers/admin_helper.php — não imprime nada. */
    function init_tail()
    {
    }
}

if (!function_exists('html_escape')) {
    /** CodeIgniter: system/core/Common.php — htmlspecialchars com ENT_QUOTES; não-string volta igual. */
    function html_escape($var, $double_encode = true)
    {
        return is_string($var) ? htmlspecialchars($var, ENT_QUOTES, 'UTF-8', $double_encode) : $var;
    }
}

if (!function_exists('e')) {
    /** Perfex: application/helpers/general_helper.php — escapa para HTML. */
    function e($var)
    {
        return htmlspecialchars((string) $var, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('_dt')) {
    /** Perfex: data e hora formatadas — no kit, o valor como veio. */
    function _dt($date, $is_timesheet = false)
    {
        return (string) $date;
    }
}

if (!function_exists('module_dir_url')) {
    /** Perfex: application/helpers/modules_helper.php — /modules/<módulo>/<segmento>. */
    function module_dir_url($module, $segment = '')
    {
        return '/modules/' . $module . '/' . ltrim((string) $segment, '/');
    }
}

if (!function_exists('get_base_currency')) {
    /** Perfex: moeda base — no kit, BRL (R$). */
    function get_base_currency()
    {
        return (object) ['id' => 1, 'name' => 'BRL', 'symbol' => 'R$'];
    }
}

if (!function_exists('format_invoice_status')) {
    /** Perfex: application/helpers/invoices_helper.php — rótulo com o nome do status em pt-BR. */
    function format_invoice_status($status, $classes = '', $label = true)
    {
        $names = [1 => 'Não pago', 2 => 'Pago', 3 => 'Parcialmente pago', 4 => 'Vencido', 5 => 'Cancelado', 6 => 'Rascunho'];

        return '<span class="label invoice-status-' . (int) $status . '">' . ($names[(int) $status] ?? '') . '</span>';
    }
}

if (!function_exists('form_open')) {
    /** CodeIgniter: system/helpers/form_helper.php — formulário POST, sem CSRF. */
    function form_open($action = '', $attributes = [], $hidden = [])
    {
        return '<form action="' . $action . '" method="post">';
    }
}

if (!function_exists('form_close')) {
    /** CodeIgniter: system/helpers/form_helper.php. */
    function form_close($extra = '')
    {
        return '</form>' . $extra;
    }
}

if (!function_exists('form_hidden')) {
    /** CodeIgniter: system/helpers/form_helper.php — um campo oculto. */
    function form_hidden($name, $value = '')
    {
        return '<input type="hidden" name="' . $name . '" value="' . $value . '">';
    }
}
