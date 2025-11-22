# Changelog - Zapcel WHMCS

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

---

## [2.1.1] - 2025-11-13

### 🎉 Atualização Maior - Sistema de Validação e AutoLogin

Esta versão adiciona funcionalidades críticas de validação de WhatsApp, sistema de autologin seguro, melhorias significativas na interface e correções importantes.

### ✨ Adicionado

#### Sistema de Validação WhatsApp
- **Validação completa de números WhatsApp** com código de 6 dígitos
- **Dashboard de validações** com estatísticas (validados, pendentes, invalidados)
- **Envio em massa** de códigos de validação para clientes pendentes
- **Controle de tentativas** com bloqueio após 5 tentativas incorretas
- **Expiração inteligente** de códigos (15 minutos)
- **Reenvio de códigos** para validações expiradas
- **Atualização automática** do número no WHMCS após validação bem-sucedida
- **Página de validação** moderna e responsiva na área do cliente
- **Ações individuais** por cliente (enviar, reenviar, visualizar, resetar)

#### Sistema de AutoLogin Seguro
- **AutoLogin com tokens criptografados** para acesso direto a faturas e tickets
- **Tokens únicos** gerados por mensagem com expiração de 72 horas
- **Rastreamento completo** de acessos (IP, data, quantidade)
- **Detecção de IP real** com suporte a Cloudflare, proxies e CDNs
- **Função `getClientIp()`** para detectar IP correto em qualquer ambiente
- **Página de erro elegante** para links inválidos ou expirados
- **Variáveis de autologin** nos templates: `{autologin_url}`, `{invoice_autologin_url}`, `{ticket_autologin_url}`
- **Tabela `mod_zapcel_autologin`** para gerenciamento de tokens

#### Interface e Usabilidade
- **Emojis nos tipos de eventos** para identificação visual rápida
- **Emojis nas mensagens de log** do NumberValidator
- **Badges coloridos** para status e tipos de eventos
- **DataTables** com paginação, ordenação e busca em tempo real
- **SweetAlert2** para confirmações e alertas elegantes
- **Botão de loading** durante envio em massa de validações
- **Filtros avançados** em todas as tabelas (logs, validações, templates)
- **Exportação de logs** para CSV e Excel
- **Preview em tempo real** ao editar templates

#### Detecção Automática de Tipos de Serviço
- **Detecção automática** do tipo de serviço (Hosting, VPS, Reseller, Outros)
- **Templates específicos** por tipo de serviço ativado
- **Variável `{service_type}`** disponível nos templates

#### Campos Personalizados (Avançado)
- **Suporte a campos personalizados** do WHMCS via JSON
- **Sintaxe:** `{"customfield":"ID_DO_CAMPO"}`
- **Documentação completa** no README.md

#### Logs e Depuração
- **Campo `response`** nos logs para armazenar resposta da API
- **Exibição de resposta** formatada nos logs (Sucesso, ID, Erro)
- **Logs de validação** com detalhes completos (código, tentativas, status)
- **Logs de autologin** com rastreamento de acessos
- **Função `zapcel_log_debug()`** para debug detalhado

### 🔧 Melhorado

#### Arquitetura e Código
- **Refatoração do `NumberValidator.php`** com separação clara de responsabilidades
- **Função `logValidationAction()`** centralizada para logs de validação
- **Função `updateClientPhoneNumber()`** para atualizar número no WHMCS
- **Função `sendPendingValidations()`** para envio em massa
- **Limpeza de números** antes de salvar (remove caracteres especiais)
- **Validação de formato E.164** para números internacionais

#### Interface Administrativa
- **Dashboard reorganizado** com cards de estatísticas
- **Gestão de templates** com contador de uso e última utilização
- **Gestão de validações** com ações em massa e individuais
- **Logs com filtros** por tipo, status, data e cliente
- **Busca em tempo real** em todas as tabelas
- **Paginação configurável** (25, 50, 100 resultados)

#### Internacionalização
- **Tradução completa** de todas as novas funcionalidades
- **Consistência** entre PT-BR e EN em toda interface
- **Emojis padronizados** em ambos os idiomas

#### Mensagens e Templates
- **Templates de validação** (código enviado, código reenviado)
- **Templates de autologin** com links diretos
- **Mensagens de erro** mais descritivas e amigáveis
- **Preview de mensagens** antes de enviar

### 🐛 Corrigido

#### Logs e Exibição
- **Correção crítica:** Resposta da API não aparecia nos logs de validação
- **Correção:** Campo `response` agora salva corretamente a resposta da API
- **Correção:** Admin exibe resposta mesmo quando está dentro de `api_response`
- **Correção:** Emojis nos tipos de eventos agora aparecem corretamente
- **Correção:** Badges de status com cores corretas

#### Validação WhatsApp
- **Correção:** Números internacionais agora são validados corretamente
- **Correção:** Limpeza de números antes de enviar para API
- **Correção:** Atualização do número no WHMCS após validação
- **Correção:** Expiração de códigos funciona corretamente
- **Correção:** Bloqueio após tentativas incorretas

#### AutoLogin
- **Correção:** Detecção de IP real com Cloudflare
- **Correção:** Tokens expirados são invalidados corretamente
- **Correção:** Redirecionamento após login funciona em todos os casos
- **Correção:** Página de erro para links inválidos

#### Interface
- **Correção:** Botão "Enviar Pendentes" não ficava em loop infinito
- **Correção:** Loading aparece apenas após confirmação
- **Correção:** Filtros de data funcionam corretamente
- **Correção:** Exportação CSV com encoding correto (UTF-8)

#### Banco de Dados
- **Correção:** Criação de tabelas com charset UTF-8
- **Correção:** Índices adicionados para performance
- **Correção:** Queries otimizadas para evitar N+1

#### Segurança
- **Correção:** SQL injection em queries de logs
- **Correção:** XSS em exibição de mensagens
- **Correção:** CSRF em formulários administrativos
- **Correção:** Validação de permissões em todas as ações

### 📝 Documentação

- **README.md completamente reescrito** com mais de 500 linhas
- **Seção de Troubleshooting** com soluções para problemas comuns
- **Seção de Campos Personalizados** com exemplos práticos
- **Seção de AutoLogin** com detalhes de segurança
- **Seção de Validação WhatsApp** com funcionamento completo
- **Exemplos de código** em todas as seções técnicas
- **Índice navegável** com links internos
- **Badges informativos** (versão, PHP, WHMCS, licença)

### 🗂️ Estrutura de Arquivos

#### Novos Arquivos
- `autologin.php` - Endpoint de autologin seguro
- `api/NumberValidator.php` - Sistema de validação (refatorado)
- `templates/zapcel_validation.tpl` - Página de validação

#### Arquivos Modificados
- `admin/index.php` - Dashboard, validações, logs melhorados
- `hooks.php` - Novos hooks e funções auxiliares
- `api/WhatsAppAPI.php` - Suporte a validação
- `langs/pt.php` - Novas traduções
- `langs/en.php` - Novas traduções

#### Tabelas do Banco
- `mod_zapcel_validation` - Armazena validações de WhatsApp
- `mod_zapcel_autologin` - Armazena tokens de autologin
- `mod_zapcel_logs` - Campo `response` adicionado

### 🚀 Performance

- **Queries otimizadas** com índices corretos
- **Cache de configurações** para reduzir acessos ao banco
- **Lazy loading** de componentes pesados
- **Paginação eficiente** com LIMIT/OFFSET
- **Busca com índices** para performance

### 🔒 Segurança

- **Tokens criptografados** para autologin
- **Validação de entrada** em todos os formulários
- **Prepared statements** em todas as queries
- **Escape de output** para prevenir XSS
- **Verificação de permissões** em todas as ações administrativas
- **Rate limiting** em validações (bloqueio após 5 tentativas)
- **Expiração de tokens** (72h para autologin, 15min para validação)

### ⚠️ Breaking Changes

Nenhuma mudança que quebre compatibilidade com versões anteriores.

### 🔄 Migração da v2.0.0 para v2.1.1

1. Faça backup do banco de dados
2. Substitua os arquivos do módulo
3. Acesse o painel administrativo
4. As novas tabelas serão criadas automaticamente
5. Configure a validação WhatsApp em **Configurações** (opcional)

---

## [2.0.0] - 2025-10-18

### 🎉 Refatoração Completa

Esta versão representa uma refatoração completa do módulo Zapcel WHMCS, com foco em código limpo, organização, segurança e escalabilidade.

### ✨ Adicionado

- Namespace PSR-4 em todas as classes (`WHMCS\Module\Addon\Zapcel\*`)
- Documentação completa com README.md
- Arquivo CHANGELOG.md para rastreamento de versões
- Implementação completa de todas as funções helper no `hooks.php`
- Validação e sanitização consistente de dados de entrada
- Tratamento de erros padronizado com logs em todos os hooks
- Verificação de sessão antes de acessar `$_SESSION['uid']`
- Suporte a operador de coalescência nula (`??`) para evitar warnings

### 🔧 Corrigido

- **Duplicidade de classe AdminDispatcher** removida
- **Carregamento incorreto** de `admin/index.php` no arquivo principal
- **Estrutura de diretórios** reorganizada conforme padrão WHMCS
- **Namespaces inconsistentes** padronizados em todas as classes
- **Funções helper** que estavam apenas como stubs agora implementadas
- **CSS embutido** mantido no arquivo `assets/css/admin.css`
- **Indentação e formatação** de código padronizadas
- **Caminhos de templates** corrigidos (`validacao` e `whatsapp-field`)
- **Lógica de validação WhatsApp** na função `zapcel_clientarea()`

### 🗂️ Reorganizado

- Movidos arquivos API para `/api/`
- Movidos gateways para `/gateways/`
- Movidos templates para `/templates/client/`
- Movidos idiomas para `/langs/`
- Movidos assets para `/assets/css/`

### 🔒 Segurança

- Uso consistente de Eloquent/Capsule para prevenir SQL Injection
- Validação de acesso administrativo em todas as funções relevantes
- Proteção contra acesso direto aos arquivos PHP
- Sanitização de dados antes de exibição

### 📝 Documentação

- README.md com instruções completas de instalação e uso
- Comentários PHPDoc em todas as funções públicas
- Comentários inline claros e relevantes
- Relatório de refatoração detalhado

### 🚀 Performance

- Remoção de código duplicado
- Otimização de queries ao banco de dados
- Cache estático de configurações em `zapcel_get_settings()`

---

## [1.x.x] - Versões Anteriores

Versões de lançamento.

---

**Desenvolvido com ❤️ por Hostcel**  