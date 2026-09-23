# perfex-module-testkit

Kit compartilhado de testes automatizados para módulos do Perfex CRM (CodeIgniter 3).

Fornece, como dependência de desenvolvimento via Composer:

- stubs das funções e classes do Perfex/CI3 (`get_instance()`, `get_option()`, `_l()`, `App_Model`, `CI_Controller`, ...);
- um banco de dados falso em memória compatível com o query builder usado pelos módulos;
- helpers para testar controllers de webhook (`php://input`, `$_SERVER`, código de resposta);
- um workflow reutilizável do GitHub Actions para rodar PHPUnit em matriz de versões do PHP.

Nenhum código do Perfex CRM é distribuído aqui — apenas substitutos escritos para teste.

> Em construção — ver `openspec/changes/create-perfex-testkit/`.
