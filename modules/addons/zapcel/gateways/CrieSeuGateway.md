# Como Criar Seu Próprio Gateway de Pagamento

**Versão:** 2.1.1  
**Autor:** Hostcel  
**Dificuldade:** Intermediária  
**Tempo Estimado:** 30-60 minutos

---

## Introdução

Este guia ensina como criar um gateway de pagamento personalizado para o módulo Zapcel WHMCS. Com isso, você poderá extrair dados de PIX (Copia e Cola, QR Code) e Boletos (Linha Digitável, PDF) de qualquer gateway de pagamento que você utilize.

### O que você vai aprender

- Estrutura básica de um gateway
- Como extrair dados de PIX
- Como extrair dados de Boleto
- Como testar seu gateway
- Boas práticas e dicas

### Pré-requisitos

- Conhecimento básico de PHP
- Acesso ao código do seu gateway de pagamento no WHMCS
- Saber onde seu gateway armazena os dados de PIX/Boleto

---

## Estrutura de um Gateway

Todo gateway no Zapcel WHMCS segue esta estrutura:

```
/modules/addons/zapcel/gateways/
├── GatewayInterface.php      # Interface (NÃO MODIFICAR)
├── IuguGateway.php           # Exemplo: Gateway Iugu do Edvan
└── SeuGateway.php            # Seu novo gateway aqui
```

---

## Passo 1: Criar o Arquivo do Gateway

### 1.1 Copiar o Template

Copie o arquivo `IuguGateway.php` e renomeie para o nome do seu gateway:

**Exemplo:**
- Mercado Pago → `MercadoPagoGateway.php`
- Banco Inter → `BancoInterGateway.php`
- PagHiper → `PagHiperGateway.php`

### 1.2 Estrutura Básica

Abra o arquivo e modifique conforme abaixo:

```php
<?php

namespace WHMCS\Module\Addon\Zapcel\Gateways;

/**
 * Gateway: [NOME DO SEU GATEWAY]
 * 
 * Extrai dados de PIX e Boleto do gateway [NOME]
 * 
 * @author Seu Nome
 * @version 1.0.0
 */
class SeuGatewayGateway extends AbstractGateway
{
    /**
     * Nome do gateway (deve ser EXATAMENTE o nome do módulo no WHMCS)
     */
    protected $gatewayName = 'seugateway';

    /**
     * Extrai dados do PIX
     */
    public function extractPixData($invoiceId)
    {
        // Seu código aqui
    }

    /**
     * Extrai dados do Boleto
     */
    public function extractBoletoData($invoiceId)
    {
        // Seu código aqui
    }
}
```

---

## Passo 2: Descobrir o Nome do Gateway

O nome do gateway deve ser **exatamente** o nome do módulo de pagamento no WHMCS.

### Como descobrir:

1. Acesse **Setup → Payments → Payment Gateways** no WHMCS
2. Encontre seu gateway na lista
3. Observe o nome do arquivo em `/modules/gateways/`

**Exemplos:**
- Iugu → `iugu.php` → Nome: `iugu`
- Mercado Pago → `mercadopago.php` → Nome: `mercadopago`
- PagSeguro → `pagseguro.php` → Nome: `pagseguro`

### Definir no código:

```php
protected $gatewayName = 'mercadopago'; // Nome do arquivo sem .php
```

---

## Passo 3: Implementar Extração de PIX

### 3.1 Entender onde os dados estão

Primeiro, você precisa descobrir onde seu gateway armazena os dados do PIX. Existem 3 lugares comuns:

#### Opção 1: Tabela `tblinvoices`

Alguns gateways salvam direto na tabela de faturas:

```sql
SELECT notes FROM tblinvoices WHERE id = 123;
```

#### Opção 2: Tabela `tblaccounts`

Outros salvam em uma tabela separada:

```sql
SELECT * FROM tblaccounts WHERE invoiceid = 123;
```

#### Opção 3: Tabela Customizada

Alguns criam sua própria tabela:

```sql
SELECT * FROM mod_seugateway_pix WHERE invoice_id = 123;
```

### 3.2 Exemplo: Extrair PIX do Iugu

Veja como o Iugu faz:

```php
public function extractPixData($invoiceId)
{
    try {
        // 1. Buscar dados da fatura
        $invoice = Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->first();

        if (!$invoice) {
            return null;
        }

        // 2. Buscar dados do PIX na tabela do gateway
        $pixData = Capsule::table('mod_iugu_pix')
            ->where('invoice_id', $invoiceId)
            ->first();

        if (!$pixData) {
            return null;
        }

        // 3. Retornar dados formatados
        return [
            'qrcode' => $pixData->qrcode_url,      // URL da imagem QR Code
            'copiaecola' => $pixData->pix_code,    // Código Copia e Cola
            'expiration' => $pixData->expires_at   // Data de expiração (opcional)
        ];

    } catch (\Exception $e) {
        return null;
    }
}
```

### 3.3 Adaptar para seu Gateway

Modifique o código acima substituindo:

1. **Nome da tabela**: `mod_iugu_pix` → `mod_seugateway_pix`
2. **Nomes dos campos**: `qrcode_url`, `pix_code` → campos do seu gateway
3. **Lógica de busca**: Se necessário, adicione JOINs ou WHERE extras

**Exemplo para Mercado Pago:**

```php
public function extractPixData($invoiceId)
{
    try {
        // Buscar na tabela do Mercado Pago
        $pixData = Capsule::table('mod_mercadopago_transactions')
            ->where('invoice_id', $invoiceId)
            ->where('payment_method', 'pix')
            ->first();

        if (!$pixData) {
            return null;
        }

        // Retornar dados
        return [
            'qrcode' => $pixData->qr_code_base64,  // Imagem em base64
            'copiaecola' => $pixData->qr_code,     // Código PIX
            'expiration' => $pixData->expiration_date
        ];

    } catch (\Exception $e) {
        return null;
    }
}
```

### 3.4 Formato do Retorno

**IMPORTANTE:** O retorno deve ser um array com estas chaves:

```php
return [
    'qrcode' => 'URL_DA_IMAGEM_OU_BASE64',  // Obrigatório
    'copiaecola' => 'CODIGO_PIX_AQUI',      // Obrigatório
    'expiration' => '2025-12-31 23:59:59'   // Opcional
];
```

Se não houver dados, retorne `null`:

```php
return null;
```

---

## Passo 4: Implementar Extração de Boleto

### 4.1 Exemplo: Extrair Boleto do Iugu

```php
public function extractBoletoData($invoiceId)
{
    try {
        // 1. Buscar dados do boleto
        $boletoData = Capsule::table('mod_iugu_boletos')
            ->where('invoice_id', $invoiceId)
            ->first();

        if (!$boletoData) {
            return null;
        }

        // 2. Retornar dados formatados
        return [
            'linha_digitavel' => $boletoData->digitable_line,  // Linha digitável
            'pdf_url' => $boletoData->pdf_url,                 // URL do PDF
            'barcode' => $boletoData->barcode,                 // Código de barras (opcional)
            'expiration' => $boletoData->due_date              // Vencimento (opcional)
        ];

    } catch (\Exception $e) {
        return null;
    }
}
```

### 4.2 Adaptar para seu Gateway

**Exemplo para PagHiper:**

```php
public function extractBoletoData($invoiceId)
{
    try {
        // Buscar na tabela do PagHiper
        $boleto = Capsule::table('mod_paghiper')
            ->where('invoice_id', $invoiceId)
            ->where('type', 'boleto')
            ->first();

        if (!$boleto) {
            return null;
        }

        return [
            'linha_digitavel' => $boleto->digitable_line,
            'pdf_url' => $boleto->url_slip_pdf,
            'barcode' => $boleto->bar_code,
            'expiration' => $boleto->due_date
        ];

    } catch (\Exception $e) {
        return null;
    }
}
```

### 4.3 Formato do Retorno

```php
return [
    'linha_digitavel' => '12345.67890 12345.678901...',  // Obrigatório
    'pdf_url' => 'https://gateway.com/boleto.pdf',      // Obrigatório
    'barcode' => '12345678901234567890123456789012345', // Opcional
    'expiration' => '2025-12-31'                        // Opcional
];
```

Se não houver dados, retorne `null`.

---

## Passo 5: Descobrir Nomes das Tabelas e Campos

### 5.1 Acessar o Banco de Dados

Use phpMyAdmin, MySQL Workbench ou linha de comando:

```bash
mysql -u usuario -p nome_do_banco
```

### 5.2 Listar Tabelas do Gateway

```sql
SHOW TABLES LIKE '%seugateway%';
```

**Exemplo para Mercado Pago:**

```sql
SHOW TABLES LIKE '%mercadopago%';
```

Resultado:
```
mod_mercadopago_transactions
mod_mercadopago_config
```

### 5.3 Ver Estrutura da Tabela

```sql
DESCRIBE mod_mercadopago_transactions;
```

Resultado:
```
+------------------+--------------+
| Field            | Type         |
+------------------+--------------+
| id               | int(11)      |
| invoice_id       | int(11)      |
| payment_method   | varchar(50)  |
| qr_code          | text         |
| qr_code_base64   | longtext     |
| expiration_date  | datetime     |
+------------------+--------------+
```

### 5.4 Ver Dados de Exemplo

```sql
SELECT * FROM mod_mercadopago_transactions WHERE invoice_id = 123;
```

Isso mostra exatamente quais campos existem e como os dados estão armazenados.

---

## Passo 6: Testar o Gateway

### 6.1 Criar uma Fatura de Teste

1. No WHMCS, crie uma fatura de teste
2. Gere o PIX ou Boleto pelo seu gateway
3. Anote o ID da fatura (ex: 123)

### 6.2 Testar no Código

Adicione temporariamente no final do seu arquivo:

```php
// TESTE - REMOVER DEPOIS
if (isset($_GET['test_gateway'])) {
    $gateway = new SeuGatewayGateway();
    
    echo "<h2>Teste PIX</h2>";
    $pix = $gateway->extractPixData(123); // ID da fatura de teste
    echo "<pre>";
    print_r($pix);
    echo "</pre>";
    
    echo "<h2>Teste Boleto</h2>";
    $boleto = $gateway->extractBoletoData(123);
    echo "<pre>";
    print_r($boleto);
    echo "</pre>";
    
    die();
}
```

Acesse: `https://seusite.com/modules/addons/zapcel/gateways/SeuGateway.php?test_gateway=1`

### 6.3 Verificar Resultado

**Sucesso:**
```
Teste PIX
Array
(
    [qrcode] => https://...
    [copiaecola] => 00020126...
    [expiration] => 2025-12-31 23:59:59
)
```

**Erro:**
```
Teste PIX
NULL
```

Se retornar `NULL`, verifique:
- Nome da tabela está correto?
- Nome dos campos está correto?
- A fatura tem PIX/Boleto gerado?

---

## Passo 7: Ativar o Gateway

### 7.1 Remover Código de Teste

Delete o bloco de teste que adicionou no Passo 6.2.

### 7.2 Salvar o Arquivo

Salve o arquivo em:

```
/modules/addons/zapcel/gateways/SeuGatewayGateway.php
```

### 7.3 Verificar no Painel

1. Acesse **Addons → Zapcel → Configurações**
2. Role até a seção **Gateways de Pagamento**
3. Seu gateway deve aparecer na lista automaticamente

Se não aparecer, verifique:
- Nome da classe está correto? (`SeuGatewayGateway`)
- Arquivo está na pasta correta?
- Não tem erros de sintaxe PHP?

---

## Exemplos Completos

### Exemplo 1: Gateway Simples (Dados na tblinvoices)

```php
<?php

namespace WHMCS\Module\Addon\Zapcel\Gateways;

use Illuminate\Database\Capsule\Manager as Capsule;

class MeuGatewayGateway extends AbstractGateway
{
    protected $gatewayName = 'meugateway';

    public function extractPixData($invoiceId)
    {
        try {
            $invoice = Capsule::table('tblinvoices')
                ->where('id', $invoiceId)
                ->first();

            if (!$invoice || !$invoice->notes) {
                return null;
            }

            // Gateway salva dados no campo 'notes' em JSON
            $data = json_decode($invoice->notes, true);

            if (!isset($data['pix'])) {
                return null;
            }

            return [
                'qrcode' => $data['pix']['qrcode_url'],
                'copiaecola' => $data['pix']['code'],
                'expiration' => $data['pix']['expires_at']
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    public function extractBoletoData($invoiceId)
    {
        try {
            $invoice = Capsule::table('tblinvoices')
                ->where('id', $invoiceId)
                ->first();

            if (!$invoice || !$invoice->notes) {
                return null;
            }

            $data = json_decode($invoice->notes, true);

            if (!isset($data['boleto'])) {
                return null;
            }

            return [
                'linha_digitavel' => $data['boleto']['linha_digitavel'],
                'pdf_url' => $data['boleto']['pdf_url'],
                'expiration' => $data['boleto']['vencimento']
            ];

        } catch (\Exception $e) {
            return null;
        }
    }
}
```

### Exemplo 2: Gateway com Tabela Própria

```php
<?php

namespace WHMCS\Module\Addon\Zapcel\Gateways;

use Illuminate\Database\Capsule\Manager as Capsule;

class BancoInterGateway extends AbstractGateway
{
    protected $gatewayName = 'bancointer';

    public function extractPixData($invoiceId)
    {
        try {
            $pix = Capsule::table('mod_bancointer_pix')
                ->where('invoice_id', $invoiceId)
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$pix) {
                return null;
            }

            return [
                'qrcode' => $pix->qrcode_image_url,
                'copiaecola' => $pix->emv_code,
                'expiration' => $pix->expiration_datetime
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    public function extractBoletoData($invoiceId)
    {
        try {
            $boleto = Capsule::table('mod_bancointer_boletos')
                ->where('invoice_id', $invoiceId)
                ->where('status', '!=', 'cancelled')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$boleto) {
                return null;
            }

            return [
                'linha_digitavel' => $boleto->linha_digitavel,
                'pdf_url' => $boleto->pdf_link,
                'barcode' => $boleto->codigo_barras,
                'expiration' => $boleto->data_vencimento
            ];

        } catch (\Exception $e) {
            return null;
        }
    }
}
```

---

## Boas Práticas

### 1. Sempre use try-catch

```php
try {
    // seu código
} catch (\Exception $e) {
    return null;
}
```

### 2. Verifique se os dados existem

```php
if (!$data || !isset($data->campo)) {
    return null;
}
```

### 3. Use orderBy para pegar o mais recente

```php
->orderBy('created_at', 'desc')
->first();
```

### 4. Documente seu código

```php
/**
 * Extrai dados do PIX do Banco Inter
 * 
 * Busca na tabela mod_bancointer_pix o PIX mais recente
 * que esteja com status 'active'
 */
```

### 5. Teste com dados reais

Sempre teste com uma fatura real que tenha PIX/Boleto gerado.

---

## Troubleshooting

### Problema: Gateway não aparece na lista

**Solução:**
1. Verifique o nome da classe: `SeuGatewayGateway`
2. Verifique o namespace: `namespace WHMCS\Module\Addon\Zapcel\Gateways;`
3. Verifique se estende `AbstractGateway`
4. Verifique erros de sintaxe PHP

### Problema: Retorna sempre NULL

**Solução:**
1. Verifique o nome da tabela no banco
2. Verifique os nomes dos campos
3. Verifique se a fatura tem PIX/Boleto gerado
4. Use `var_dump()` para debugar:

```php
$data = Capsule::table('sua_tabela')->where('invoice_id', $invoiceId)->first();
var_dump($data);
die();
```

### Problema: Erro "Class not found"

**Solução:**
1. Verifique o namespace no topo do arquivo
2. Verifique se importou o Capsule:

```php
use Illuminate\Database\Capsule\Manager as Capsule;
```

### Problema: Dados não aparecem na mensagem

**Solução:**
1. Verifique se o gateway está ativo nas configurações
2. Verifique se o template usa as variáveis corretas:
   - `{pix_qrcode}` - QR Code
   - `{pix_copiaecola}` - Código PIX
   - `{boleto_linha_digitavel}` - Linha digitável
   - `{boleto_pdf_url}` - Link do PDF

---

## Suporte

### Precisa de Ajuda?

1. **Documentação:** Leia o README.md completo
2. **Central de Ajuda:** https://help.manus.im
3. **Contato:** suporte@hostcel.com.br

### Compartilhe seu Gateway

Se você criou um gateway funcional, considere compartilhar com a comunidade:

1. Crie um Pull Request no GitHub
2. Ou envie para suporte@hostcel.com.br

---

**Desenvolvido com ❤️ por Hostcel**  
**Boa sorte com seu gateway!** 🚀

