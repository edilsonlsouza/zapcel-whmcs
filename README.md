# 📱 Zapcel WHMCS - Módulo de Integração WhatsApp

![Versão](https://img.shields.io/badge/versão-2.1.1-blue.svg)
![WHMCS](https://img.shields.io/badge/WHMCS-7.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![Licença](https://img.shields.io/badge/licença-Gratuita-success.svg)
![Status](https://img.shields.io/badge/status-Produção-brightgreen.svg)

**Versão:** 2.1.1  
**Autor:** Hostcel  
**Última Atualização:** Abril 2026 
**Licença:** Gratuita (requer API Zapcel - serviço pago)

---

## 📖 Índice

1. [Visão Geral](#-visão-geral)
2. [Funcionalidades](#-funcionalidades)
3. [Requisitos](#-requisitos)
4. [Instalação](#-instalação)
5. [Configuração](#️-configuração)
6. [Estrutura de Arquivos](#-estrutura-de-arquivos)
7. [Uso do Painel Administrativo](#-uso-do-painel-administrativo)
8. [Validação de WhatsApp](#-validação-de-whatsapp)
9. [AutoLogin Seguro](#-autologin-seguro)
10. [Templates e Variáveis](#-templates-e-variáveis)
11. [Campos Personalizados (Avançado)](#-campos-personalizados-avançado)
12. [Internacionalização](#-internacionalização)
13. [Logs e Depuração](#-logs-e-depuração)
14. [Troubleshooting](#-troubleshooting)
15. [Suporte](#-suporte)
16. [Changelog](#-changelog)

---

## 🎯 Visão Geral

O **Zapcel WHMCS** é um módulo addon **100% gratuito** que integra seu sistema WHMCS com a API profissional da Zapcel, permitindo enviar notificações automáticas via WhatsApp para seus clientes em eventos importantes como:

- 💰 Criação, pagamento e cancelamento de faturas
- 🔔 Lembretes automáticos de vencimento (1º, 2º e 3º aviso)
- 🎫 Abertura e resposta de tickets
- 🌐 Ativação, suspensão e cancelamento de serviços
- 👤 Cadastro e edição de clientes
- 🔑 Alteração de senhas
- 💼 Criação e aceitação de cotações

### 🌟 Diferenciais

- ✅ **Interface Moderna**: Design responsivo e intuitivo
- ✅ **Totalmente Internacionalizado**: Português e Inglês
- ✅ **Validação de Números**: Sistema completo de validação WhatsApp
- ✅ **AutoLogin Seguro**: Links diretos para faturas e tickets
- ✅ **Logs Detalhados**: Rastreamento completo de todas as mensagens
- ✅ **Dashboard com Estatísticas**: Gráficos e métricas em tempo real
- ✅ **Templates Personalizáveis**: Editor visual com variáveis dinâmicas
- ✅ **Código Limpo**: Arquitetura modular e bem documentada

---

## ✨ Funcionalidades

### 📋 Gestão de Templates

- **Templates Automáticos**: Mensagens pré-configuradas para todos os eventos do WHMCS
- **Respostas Rápidas**: Mensagens personalizadas para envio manual
- **Editor Visual**: Interface com preview em tempo real
- **Variáveis Dinâmicas**: Personalize com dados do cliente, fatura, ticket ou serviço
- **Contador de Caracteres**: Controle o tamanho das mensagens
- **Emojis Integrados**: Adicione emojis facilmente
- **Ativação Individual**: Ative/desative templates específicos

### 🔔 Notificações Automáticas

#### 💰 Faturas
- Fatura criada
- Fatura paga
- Fatura cancelada
- 1º lembrete de vencimento
- 2º lembrete de vencimento
- 3º lembrete de vencimento

#### 🎫 Tickets
- Ticket aberto
- Nova resposta do suporte

#### 🌐 Serviços
- Serviço ativado (com detecção automática do tipo: Hosting, VPS, Reseller, Outros)
- Serviço suspenso
- Serviço reativado
- Serviço cancelado
- Solicitação de cancelamento

#### 👤 Clientes
- Cliente adicionado
- Cliente editado
- Senha alterada (com envio seguro da nova senha)

#### 💼 Cotações
- Cotação criada
- Cotação modificada
- Cotação aceita

### ✅ Sistema de Validação WhatsApp

- **Código de Verificação**: Envio automático de código de 6 dígitos
- **Expiração Inteligente**: Códigos válidos por 15 minutos
- **Controle de Tentativas**: Bloqueio após 5 tentativas incorretas
- **Reenvio de Código**: Possibilidade de reenviar códigos expirados
- **Atualização Automática**: Sincroniza números validados no WHMCS
- **Envio em Massa**: Valide todos os clientes pendentes com um clique
- **Dashboard Completo**: Estatísticas de validações (validados, pendentes, invalidados)
- **Ações Individuais**: Enviar, reenviar, visualizar e resetar validações

### 🔗 AutoLogin Seguro

- **Links Diretos**: Acesso instantâneo a faturas e tickets via WhatsApp
- **Tokens Criptografados**: Segurança avançada com tokens únicos
- **Expiração Configurável**: Links válidos por 72 horas
- **Rastreamento Completo**: Registra IP, data e quantidade de acessos
- **Detecção de IP Real**: Suporte a Cloudflare, proxies e CDNs
- **Página de Erro Elegante**: Interface amigável para links inválidos/expirados

### 📊 Painel Administrativo

- **Dashboard Moderno**: Estatísticas e gráficos em tempo real
- **Gestão de Templates**: Edição inline, filtros e contador de uso
- **Logs Detalhados**: Registro completo com exportação CSV/Excel
- **Gestão de Validações**: Controle total sobre validações de clientes
- **Busca Avançada**: Filtros por tipo, status, data e cliente
- **DataTables**: Tabelas interativas com ordenação e paginação
- **Envio Manual**: Envie mensagens personalizadas para qualquer cliente

### 🌐 Internacionalização

- **Português Brasileiro**: Tradução completa e nativa
- **Inglês**: Tradução profissional de toda a interface
- **Troca Fácil**: Alternância entre idiomas nas configurações
- **Consistência**: Todas as mensagens, botões e notificações traduzidas

---

## 📦 Requisitos

### Servidor

- **WHMCS**: Versão 8.0 ou superior
- **PHP**: Versão 8.1 ou superior
- **ionCube**: Versão 13 ou superior
- **MySQL**: Versão 5.7 ou superior
- **Extensões PHP**: `curl`, `json`, `mbstring`

### Serviços Externos

- **API Zapcel**: Conta ativa com credenciais (Token e Instance ID)
  - Contrate em: [https://zap.hostcel.com.br](https://zap.hostcel.com.br)

### Opcional (para AutoLogin com Cloudflare)

- Configuração do Cloudflare para passar headers de IP real

---

## 🚀 Instalação

### Passo 1: Download

Baixe a versão mais recente do módulo:
- GitHub: [Releases](https://github.com/edilsonlsouza/zapcel-whmcs/releases)
- Ou clone o repositório:

```bash
git clone https://github.com/edilsonlsouza/zapcel-whmcs.git
```

### Passo 2: Upload

1. Descompacte o arquivo baixado
2. Envie a pasta `zapcel` para o diretório `/modules/addons/` do seu WHMCS

```
/seu-whmcs/
└── modules/
    └── addons/
        └── zapcel/  ← Pasta do módulo aqui
```

### Passo 3: Ativação

1. Acesse o painel administrativo do WHMCS
2. Vá para **Setup → Addon Modules** (ou **Configuração → Módulos Addon**)
3. Localize **Zapcel** na lista
4. Clique em **Activate** (Ativar)

### Passo 4: Permissões

1. Após ativar, clique em **Configure** (Configurar)
2. Marque os grupos de administradores que terão acesso ao módulo
3. Clique em **Save** (Salvar)

### Passo 5: Instalação de Tabelas

O módulo cria automaticamente as seguintes tabelas no banco de dados:

- `mod_zapcel_templates` - Templates de mensagens
- `mod_zapcel_logs` - Logs de envio
- `mod_zapcel_validation` - Validações de WhatsApp
- `mod_zapcel_autologin` - Tokens de autologin

---

## ⚙️ Configuração

### Configuração Inicial

1. Acesse **Addons → Zapcel** no menu do WHMCS
2. Clique na aba **Configurações**
3. Preencha os campos:

#### Credenciais da API

- **ID da Instância**: Seu Instance ID da Zapcel
- **Token de Acesso**: Seu Token da API Zapcel

#### Opções Gerais

- **Ativar Módulo**: Habilita/desabilita o envio de mensagens
- **Idioma**: Escolha entre Português ou Inglês
- **Número de Teste**: Número para enviar mensagens de teste

#### Validação WhatsApp

- **Ativar Validação**: Habilita o sistema de validação
- **Forçar Validação**: Bloqueia acesso à área do cliente sem validação
- **Origem do Número**: Campo do WHMCS onde está o número (padrão: `phonenumber`)

4. Clique em **Salvar Configurações**

### Teste de Conexão

1. Na aba **Configurações**, clique em **Testar Conexão**
2. Verifique se a API está respondendo corretamente
3. Se houver erro, verifique suas credenciais

---

## 📁 Estrutura de Arquivos

```
/modules/addons/zapcel/
├── zapcel.php                    # Arquivo principal do módulo
├── hooks.php                     # Hooks do WHMCS e funções auxiliares
├── autologin.php                 # Endpoint de autologin seguro (coloque na razi do whmcs)
├── README.md                     # Documentação completa
├── CHANGELOG.md                  # Histórico de alterações
│
├── admin/
│   └── index.php                 # Painel administrativo completo
│
├── api/
│   ├── WhatsAppAPI.php           # Integração com API Zapcel
│   ├── MessageProcessor.php      # Processador de templates
│   ├── NumberValidator.php       # Sistema de validação de números
│   ├── StatisticsManager.php     # Gerenciador de Estatísticas e Relatórios
│   └── WhatsAppAPI.php           # Classe de Integração WhatsApp API
│
├── assets/
│   └── css/
│       └── admin.css             # Estilos do painel administrativo
│
├── langs/
│   ├── en.php                    # Traduções em Inglês
│   └── pt.php                    # Traduções em Português-BR
│
└── templates/
    ├── zapcel_validation_success.tpl        # Página de validação WhatsApp
    └── zapcel_validation.tpl                # Campo WhatsApp no perfil
```

### Descrição dos Componentes

#### Arquivos Principais

- **zapcel.php**: Configuração do módulo, criação de tabelas e hooks de ativação
- **hooks.php**: Todos os hooks de eventos do WHMCS (faturas, tickets, serviços, etc)
- **autologin.php**: Sistema de autologin seguro com tokens criptografados

#### API

- **WhatsAppAPI.php**: Comunicação com a API Zapcel (envio de mensagens, validação)
- **MessageProcessor.php**: Processamento de templates e substituição de variáveis
- **NumberValidator.php**: Lógica completa de validação de números WhatsApp
- **validation.php**: Endpoints AJAX para validação (enviar código, verificar, reenviar)

#### Admin

- **admin/index.php**: Painel administrativo completo com:
  - Dashboard com estatísticas
  - Gestão de templates
  - Logs detalhados
  - Gestão de validações
  - Configurações
  - Envio manual de mensagens

#### Templates

- **validacao.tpl**: Página de validação WhatsApp na área do cliente
- **whatsapp-field.tpl**: Campo de WhatsApp no perfil do cliente

---

## 🎛️ Uso do Painel Administrativo

### Dashboard

Acesse **Addons → Zapcel** para ver:

- 📊 **Estatísticas Gerais**: Total de mensagens, taxa de sucesso, falhas
- 📈 **Gráficos**: Evolução de envios por dia/mês
- 🔔 **Status da API**: Conexão e créditos disponíveis
- ✅ **Validações**: Total de validados, pendentes e invalidados

### Gestão de Templates

1. Clique na aba **Templates**
2. Visualize todos os templates disponíveis
3. **Ações disponíveis**:
   - ✏️ **Editar**: Modifique o conteúdo da mensagem
   - ✅ **Ativar/Desativar**: Habilite ou desabilite o envio
   - 📊 **Estatísticas**: Veja quantas vezes foi usado
   - 🧪 **Testar**: Envie uma mensagem de teste

#### Editando um Template

1. Clique em **Editar** no template desejado
2. Modifique o conteúdo usando variáveis dinâmicas
3. Use o **Preview** para ver como ficará a mensagem
4. Clique em **Salvar**

### Logs do Sistema

1. Clique na aba **Logs**
2. **Filtros disponíveis**:
   - 📅 **Data**: Filtre por período
   - 📋 **Tipo**: Filtre por tipo de evento
   - ✅ **Status**: Sucesso ou falha
   - 👤 **Cliente**: Busque por cliente específico
3. **Ações**:
   - 👁️ **Visualizar**: Veja detalhes completos do log
   - 📥 **Exportar**: Exporte logs para CSV/Excel
   - 🗑️ **Limpar**: Remova logs antigos

### Gestão de Validações

1. Clique na aba **Validação WhatsApp**
2. Visualize todos os clientes e seus status
3. **Ações individuais**:
   - 📧 **Enviar**: Envia código de validação
   - 🔄 **Reenviar**: Reenvia código expirado
   - 👁️ **Visualizar**: Vê detalhes da validação
   - 🗑️ **Resetar**: Remove validação do cliente
4. **Envio em Massa**:
   - Clique em **Enviar Pendentes** para validar todos de uma vez

### Envio Manual de Mensagens

1. Clique na aba **Enviar Mensagem**
2. Selecione o cliente
3. Escolha um template de resposta rápida (opcional)
4. Digite a mensagem
5. Clique em **Enviar**

---

## ✅ Validação de WhatsApp

### Como Funciona

1. Cliente acessa a área do cliente
2. Se não tiver WhatsApp validado, é redirecionado para `/zapcel_validacao.tpl`
3. Sistema envia código de 6 dígitos via WhatsApp
4. Cliente digita o código recebido
5. Após validação, o número é atualizado no WHMCS

### Configuração

1. Acesse **Addons → Zapcel → Configurações**
2. Ative **Validação WhatsApp**
3. Escolha se deseja **Forçar Validação** (bloqueia acesso sem validar)
4. Salve as configurações

### Funcionalidades

- ⏰ **Expiração**: Códigos válidos por 15 minutos
- 🔒 **Segurança**: Bloqueio após 5 tentativas incorretas
- 🔄 **Reenvio**: Cliente pode solicitar novo código
- 📊 **Dashboard**: Acompanhe estatísticas de validações
- 📧 **Envio em Massa**: Valide todos os clientes pendentes

### Página de Validação

A página de validação (`/zapcel_validacao.tpl`) possui:

- Interface moderna e responsiva
- Contador de tempo do código
- Botão de reenvio
- Mensagens de erro amigáveis
- Redirecionamento automático após validação

---

## 🔗 AutoLogin Seguro

### O que é

O AutoLogin permite que clientes acessem diretamente faturas e tickets através de links enviados por WhatsApp, sem precisar fazer login manualmente.

### Como Funciona

1. Módulo gera token criptografado único
2. Token é salvo no banco com data de expiração (72h)
3. Link é enviado na mensagem: `https://seusite.com/whmcs/autologin.php?token=ABC123`
4. Cliente clica no link
5. Sistema valida token e faz login automático
6. Cliente é redirecionado para fatura/ticket

### Segurança

- ✅ **Tokens Únicos**: Cada link tem um token diferente
- ✅ **Expiração**: Links válidos por 72 horas
- ✅ **Rastreamento**: Registra IP, data e quantidade de acessos
- ✅ **IP Real**: Detecta IP real mesmo com Cloudflare/Proxy
- ✅ **Uso Único**: Após expiração, token é invalidado

### Variáveis de AutoLogin

Use nas mensagens:

- `{autologin_url}` - Link direto para a fatura/ticket
- `{invoice_autologin_url}` - Link específico para fatura
- `{ticket_autologin_url}` - Link específico para ticket

### Configuração com Cloudflare

Se usar Cloudflare, configure para passar o IP real:

1. No painel do Cloudflare, ative **"Restore original visitor IP"**
2. Ou instale o módulo **mod_cloudflare** no servidor

---

## 📝 Templates e Variáveis

### Variáveis Globais

Disponíveis em todos os templates:

```
{client_id}          - ID do cliente
{client_name}        - Nome completo do cliente
{client_firstname}   - Primeiro nome
{client_lastname}    - Sobrenome
{client_email}       - E-mail do cliente
{client_phone}       - Telefone do cliente
{company_name}       - Nome da sua empresa
{system_url}         - URL do WHMCS
{quebrar_mensagem}   - Envia a mensagem em partes (ótima para codigo do pix)
```

### Variáveis de Faturas

```
{invoice_id}         - ID da fatura
{invoice_number}     - Número da fatura
{invoice_date}       - Data de criação
{invoice_duedate}    - Data de vencimento
{invoice_total}      - Valor total
{invoice_status}     - Status (Paga, Pendente, etc)
{invoice_url}        - Link para visualizar fatura
{autologin_url}      - Link de autologin para fatura
```

### Variáveis de Tickets

```
{ticket_id}          - ID do ticket
{ticket_number}      - Número do ticket
{ticket_subject}     - Assunto do ticket
{ticket_department}  - Departamento
{ticket_priority}    - Prioridade
{ticket_status}      - Status
{ticket_url}         - Link para visualizar ticket
{autologin_url}      - Link de autologin para ticket
```

### Variáveis de Serviços

```
{service_id}         - ID do serviço
{service_name}       - Nome do produto/serviço
{service_domain}     - Domínio do serviço
{service_status}     - Status do serviço
{service_type}       - Tipo (Hosting, VPS, etc)
{service_nextduedate}- Próxima data de vencimento
```

### Variáveis de Cotações

```
{quote_id}           - ID da cotação
{quote_number}       - Número da cotação
{quote_date}         - Data de criação
{quote_validuntil}   - Válida até
{quote_total}        - Valor total
{quote_url}          - Link para visualizar cotação
```

### Formatação WhatsApp

Use formatação nativa do WhatsApp:

```
*Texto em Negrito*
_Texto em Itálico_
~Texto Riscado~
```monospace```
😀 Emojis
```

---

## 🔧 Campos Personalizados (Avançado)

### O que são

Campos personalizados (Custom Fields) do WHMCS podem ser usados nas mensagens através de variáveis JSON.

### Sintaxe

```json
{"customfield":"ID_DO_CAMPO"}
```

### Como Descobrir o ID

1. Acesse **Setup → Custom Fields** no WHMCS
2. Clique para editar o campo desejado
3. Observe a URL: `configcustomfields.php?action=manage&id=123`
4. O número após `id=` é o ID do campo (exemplo: **123**)

### Exemplo Prático

Suponha que você tenha um campo personalizado **"CPF"** com ID **15**:

```
Olá {client_name}!

Seu CPF cadastrado é: {"customfield":"15"}

Obrigado!
```

O sistema substituirá `{"customfield":"15"}` pelo valor do CPF do cliente.

### Tipos Suportados

- ✅ Campos de Clientes (Client Custom Fields)
- ✅ Campos de Produtos/Serviços (Product Custom Fields)
- ✅ Campos de Faturas (se disponível)

### Requisitos

- ⚠️ Conhecimento básico de **JSON**
- ⚠️ Saber identificar **IDs dos campos** no WHMCS
- ⚠️ **Testar mensagens** antes de ativar templates

---

## 🌐 Internacionalização

### Idiomas Disponíveis

- 🇧🇷 **Português Brasileiro** (`pt`)
- 🇺🇸 **Inglês** (`en`)

### Trocar Idioma

1. Acesse **Addons → Zapcel → Configurações**
2. Selecione o idioma desejado
3. Clique em **Salvar**

### Arquivos de Tradução

- `/langs/pt.php` - Português
- `/langs/en.php` - Inglês

### Adicionar Novo Idioma

1. Copie o arquivo `/langs/pt.php`
2. Renomeie para o código do idioma (ex: `es.php` para Espanhol)
3. Traduza todas as strings
4. Adicione o idioma nas configurações do módulo

---

## 📋 Logs e Depuração

### Tipos de Logs

1. **Logs de Mensagens** (`mod_zapcel_logs`)
   - Todas as mensagens enviadas
   - Status de sucesso/falha
   - Resposta da API
   - Data e hora

2. **Logs de Validação** (dentro de `mod_zapcel_logs`)
   - Códigos enviados
   - Tentativas de validação
   - Sucessos e falhas

3. **Logs de AutoLogin** (`mod_zapcel_autologin`)
   - Tokens gerados
   - Acessos realizados
   - IPs de origem

### Visualizar Logs

1. Acesse **Addons → Zapcel → Logs**
2. Use os filtros para encontrar logs específicos
3. Clique em **Visualizar** para ver detalhes completos

### Exportar Logs

1. Na aba **Logs**, aplique os filtros desejados
2. Clique em **Exportar CSV** ou **Exportar Excel**
3. O arquivo será baixado automaticamente

### Limpar Logs Antigos

1. Na aba **Logs**, clique em **Limpar Logs**
2. Escolha o período (ex: logs com mais de 90 dias)
3. Confirme a operação

### Debug Mode

Para ativar logs de debug detalhados, adicione no arquivo `hooks.php`:

```php
define('ZAPCEL_DEBUG', true);
```

Isso criará logs detalhados em `/modules/addons/zapcel/debug.log`.

---

## 🔍 Troubleshooting

### Mensagens não estão sendo enviadas

**Possíveis causas:**

1. ✅ **Verifique as credenciais da API**
   - Acesse **Configurações → Testar Conexão**
   - Confirme Token e Instance ID

2. ✅ **Verifique se o template está ativado**
   - Acesse **Templates**
   - Confirme que o template do evento está ativo

3. ✅ **Verifique os logs**
   - Acesse **Logs**
   - Procure por erros na resposta da API

4. ✅ **Verifique o número do cliente**
   - Confirme que o cliente tem número de WhatsApp cadastrado
   - Número deve estar no formato internacional (+5511999999999)

### Validação não funciona

**Possíveis causas:**

1. ✅ **Validação não está ativada**
   - Acesse **Configurações**
   - Ative **Validação WhatsApp**

2. ✅ **Cliente não recebe código**
   - Verifique logs de envio
   - Confirme que número está correto

3. ✅ **Código expira muito rápido**
   - Códigos são válidos por 15 minutos
   - Cliente pode solicitar reenvio

### AutoLogin não funciona

**Possíveis causas:**

1. ✅ **Link expirado**
   - Links são válidos por 72 horas
   - Gere novo link reenviando a mensagem

2. ✅ **Token inválido**
   - Verifique se o link está completo
   - Não deve ter quebras de linha

3. ✅ **IP bloqueado**
   - Verifique configurações de firewall
   - Confirme que Cloudflare está passando IP real

### Erro "Call to undefined function"

**Solução:**

1. Verifique se todas as extensões PHP estão instaladas:
   ```bash
   php -m | grep -E "curl|json|mbstring"
   ```

2. Reinstale o módulo:
   - Desative em **Addon Modules**
   - Delete a pasta `/modules/addons/zapcel/`
   - Faça upload novamente
   - Ative o módulo

### Erro de banco de dados

**Solução:**

1. Verifique se as tabelas foram criadas:
   ```sql
   SHOW TABLES LIKE 'mod_zapcel_%';
   ```

2. Se não existirem, desative e ative o módulo novamente

3. Ou execute manualmente os SQLs de criação (veja `zapcel.php`)

---

## 🛠️ Suporte

### Documentação

- **README.md**: Este arquivo (documentação completa)
- **CHANGELOG.md**: Histórico de alterações e atualizações

### Central de Ajuda

Para dúvidas, sugestões ou problemas técnicos:

🔗 **https://www.hostcel.com.br/tutoriais/zapcel-whmcs-modulo-de-integracao-whatsapp/**

### Contato

- **Website**: [https://hostcel.com.br](https://hostcel.com.br)
- **E-mail**: suporte@hostcel.com.br

### API Zapcel

Para contratar ou obter suporte da API Zapcel:

🔗 **https://zap.hostcel.com.br**

---

## 📝 Changelog

### v2.1.1 (Novembro 2025)

#### ✨ Novidades
- ✅ Sistema completo de validação WhatsApp com dashboard
- ✅ AutoLogin seguro com tokens criptografados
- ✅ Detecção automática de tipo de serviço (Hosting, VPS, Reseller)
- ✅ Envio em massa de validações pendentes
- ✅ Suporte a campos personalizados via JSON
- ✅ Exportação de logs para CSV/Excel
- ✅ Interface moderna com DataTables e SweetAlert2
- ✅ Emojis nos logs e tipos de eventos
- ✅ Detecção de IP real (Cloudflare, proxies)

#### 🔧 Melhorias
- ✅ Refatoração completa do código
- ✅ Arquitetura modular e escalável
- ✅ Logs mais detalhados com resposta da API
- ✅ Templates com preview em tempo real
- ✅ Filtros avançados em todas as tabelas
- ✅ Tradução completa PT-BR e EN
- ✅ Documentação expandida

#### 🐛 Correções
- ✅ Correção de exibição de resposta da API nos logs
- ✅ Correção de validação de números internacionais
- ✅ Correção de encoding de caracteres especiais
- ✅ Correção de timezone em logs
- ✅ Correção de SQL injection em queries

### v2.0.0 (Versão Anterior)

- Versão inicial refatorada
- Templates personalizáveis
- Dashboard com estatísticas
- Sistema de logs

---

## 📄 Licença

Este módulo é **100% gratuito** e pode ser usado sem restrições em qualquer instalação WHMCS.

Para funcionar, requer uma conta ativa na **API Zapcel** (serviço pago).

**Desenvolvido por:** Hostcel  
**Copyright © 2025 Hostcel. Todos os direitos reservados.**

---

## 🚀 Contribuindo

Contribuições são bem-vindas! Para contribuir:

1. Fork o repositório
2. Crie uma branch para sua feature (`git checkout -b feature/MinhaFeature`)
3. Commit suas mudanças (`git commit -m 'Adiciona MinhaFeature'`)
4. Push para a branch (`git push origin feature/MinhaFeature`)
5. Abra um Pull Request

---

## 🙏 Agradecimentos

- **Zapcel**: Pela API profissional de WhatsApp
- **WHMCS**: Pela plataforma de billing
- **Comunidade**: Por feedback e sugestões

---

**🎉 Transforme a comunicação da sua empresa hoje mesmo!**

[![Download](https://img.shields.io/badge/Download-Última%20Versão-success.svg)](https://github.com/edilsonlsouza/zapcel-whmcs/releases)
[![Documentação](https://img.shields.io/badge/Docs-Completa-blue.svg)](https://www.hostcel.com.br/tutoriais/zapcel-whmcs-modulo-de-integracao-whatsapp/)
[![Suporte](https://img.shields.io/badge/Suporte-24/7-orange.svg)](https://www.hostcel.com.br)

