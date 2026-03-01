<?php
/**
 * Zapcel WHMCS
 * Módulo de notificações via WhatsApp profissional
 * 
 * @package    Zapcel
 * @author     Hostcel
 * @version    2.0.0
 * @license    Commercial
 */

// Bloqueia acesso direto
if (!defined('WHMCS')) {
    die('Acesso não autorizado.');
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Zapcel\Api\WhatsAppAPI;
use WHMCS\Module\Addon\Zapcel\Api\StatisticsManager;

// CORREÇÃO: Incluir o arquivo do dispatcher administrativo
require_once __DIR__ . '/admin/index.php';

/**
 * Configuração do módulo
 */
function zapcel_config()
{
    global $CONFIG;
    
    // Verifica atualizações
    $currentVersion = '2.1.1';
    $updateInfo = zapcel_check_updates($currentVersion);
    
    return [
        'name' => 'Zapcel WHMCS',
        'description' => 'Sistema profissional de notificações via WhatsApp com templates otimizados e relatórios detalhados.',
        'version' => $currentVersion,
        'author' => '<a href="https://www.hostcel.com.br" target="_blank"><img src="https://www.hostcel.com.br/wp-content/uploads/2022/12/logo-marca-hostcel.png" height="20" alt="Hostcel"/></a>',
        'language' => 'portuguese-br',
        'fields' => [
            'zapcel_enabled' => [
                'FriendlyName' => 'Ativar Zapcel',
                'Type' => 'yesno',
                'Description' => 'Habilitar o envio de notificações via WhatsApp',
                'Default' => 'on',
            ],
            'zapcel_instance_id' => [
                'FriendlyName' => 'ID da Instância',
                'Type' => 'text',
                'Size' => '15',
                'Default' => '',
                'Description' => 'ID da instância do Zapcel',
            ],
            'zapcel_access_token' => [
                'FriendlyName' => 'Token de Acesso',
                'Type' => 'text',
                'Size' => '15',
                'Default' => '',
                'Description' => 'Token de acesso da API Zapcel',
            ],
            'zapcel_validation' => [
                'FriendlyName' => 'Validação WhatsApp',
                'Type' => 'dropdown',
                'Options' => [
                    '0' => 'Desativada',
                    '1' => 'Ativada',
                ],
                'Default' => '0',
                'Description' => 'Exigir validação do número WhatsApp para acesso à área do cliente',
            ],
            'zapcel_phone_source' => [
                'FriendlyName' => 'Origem do Número',
                'Type' => 'text',
                'Default' => 'phonenumber',
                'Description' => 'Campo do cadastro do cliente onde está o número WhatsApp (padrão: phonenumber)',
            ],
            'zapcel_signature' => [
                'FriendlyName' => 'Assinatura',
                'Type' => 'textarea',
                'Rows' => 3,
                'Cols' => 60,
                'Default' => 'Atenciosamente, Equipe ' . $CONFIG['CompanyName'],
                'Description' => 'Assinatura usada nas mensagens (variável: {assinatura})',
            ],
            'enable_logging' => [
                'FriendlyName' => 'Ativar logging detalhado',
                'Type' => 'dropdown',
                'Options' => [
                    '0' => 'Desativado',
                    '1' => 'Ativado',
                ],
                'Default' => '0',
                'Description' => 'Recomendado para debug e monitoramento do sistema (pode gerar muitos registros)',
            ],
            'zapcel_support' => [
                'FriendlyName' => 'Suporte & Atualizações',
                'Description' => zapcel_render_support_field($currentVersion, $updateInfo),
            ],
        ],
    ];
}

/**
 * Verifica atualizações disponíveis
 */
function zapcel_check_updates($currentVersion)
{
    $currentVersionInt = (int) preg_replace("/[^0-9]/", "", $currentVersion);
    
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.hostcel.com.br/wp-outros/modulos/?modulo=zapcel',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Zapcel-WHMCS/' . $currentVersion,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && !empty($response)) {
            $latestVersion = trim($response);
            $latestVersionInt = (int) preg_replace("/[^0-9]/", "", $latestVersion);
            
            return [
                'latest' => $latestVersion,
                'latest_int' => $latestVersionInt,
                'current_int' => $currentVersionInt,
                'status' => $latestVersionInt <=> $currentVersionInt,
            ];
        }
    } catch (Exception $e) {
        // Silencia erros de verificação de atualização
    }
    
    return null;
}

/**
 * Renderiza campo de suporte com informações de versão
 */
function zapcel_render_support_field($currentVersion, $updateInfo)
{
    $output = '&copy; 2017-' . date('Y') . ' <a href="https://www.hostcel.com.br" target="_blank" style="text-decoration:underline;">Hostcel</a>';
    $output .= ' - Sua Versão: ' . $currentVersion;
    
    if ($updateInfo) {
        if ($updateInfo['status'] === 0) {
            $output .= ' <i class="fas fa-check-circle" style="color:#4E7408;"></i> <span style="color:#4E7408;">Versão mais recente!</span>';
        } elseif ($updateInfo['status'] === 1) {
            $output .= ' <i class="fas fa-exclamation-circle" style="color:#d3678d;"></i> <span style="color:#d3678d;">Atualização disponível: ' . $updateInfo['latest'] . '</span>, <a style="color:#d3678d;" href="https://provedor.co/modulozapcel" target="_blank">Download</a>';
        } else {
            $output .= ' <i class="fas fa-exclamation-circle" style="color:#d6b200;"> <span style="color:#d6b200;">Você está executando uma versão beta.</span>';
        }
    } else {
        $output .= ' <i class="fas fa-sync-alt text-muted"></i> <span class="text-muted">Verificação indisponível</span>';
    }
    
    $output .= ' - <g-emoji class="g-emoji" alias="heart" fallback-src="https://github.githubassets.com/images/icons/emoji/unicode/2764.png">❤️</g-emoji> ';
    $output .= '<a href="https://zap.hostcel.com.br" target="_blank" style="text-decoration:underline;">Testar Zapcel</a>';
    
    return $output;
}

/**
 * Ativação do módulo - VERSÃO CORRIGIDA UTF8MB4
 */
function zapcel_activate()
{
    try {
        $currentTime = date('Y-m-d H:i:s');
        
        // Tabela de templates de mensagem
        if (!Capsule::schema()->hasTable('mod_zapcel_templates')) {
            Capsule::schema()->create('mod_zapcel_templates', function ($table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->string('trigger_event', 100);
                $table->text('template');
                $table->boolean('active')->default(true);
                $table->integer('usage_count')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                
                // DEFINE CHARSET E COLLATION DA TABELA
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_general_ci';
            });
        }
        
        // Tabela de validação de WhatsApp
        if (!Capsule::schema()->hasTable('mod_zapcel_validation')) {
            Capsule::schema()->create('mod_zapcel_validation', function ($table) {
                $table->increments('id');
                $table->integer('client_id')->unsigned();
                $table->string('phone_number', 20);
                $table->string('verification_code', 10)->nullable(); // NOME CORRETO
                $table->boolean('validated')->default(false);
                $table->string('status', 20)->default('pending'); // CAMPO ADICIONADO
                $table->integer('attempts')->default(0); // CAMPO ADICIONADO
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                
                $table->unique('client_id');
                $table->index('verification_code'); // NOME CORRETO
                
                // DEFINE CHARSET E COLLATION DA TABELA
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_general_ci';
            });
        }
        
        // Tabela de logs e estatísticas
        if (!Capsule::schema()->hasTable('mod_zapcel_logs')) {
            Capsule::schema()->create('mod_zapcel_logs', function ($table) {
                $table->increments('id');
                $table->integer('client_id')->unsigned()->nullable();
                $table->string('event_type', 100);
                $table->string('phone_number', 20)->nullable();
                $table->text('message');
                $table->boolean('success');
                $table->text('response')->nullable();
                $table->string('message_id', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                
                $table->index('client_id');
                $table->index('event_type');
                $table->index('success');
                $table->index('created_at');
                
                // DEFINE CHARSET E COLLATION DA TABELA
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_general_ci';
            });
        }
        
        // Tabela de gateways de pagamento
        if (!Capsule::schema()->hasTable('mod_zapcel_gateways')) {
            Capsule::schema()->create('mod_zapcel_gateways', function ($table) {
                $table->increments('id');
                $table->string('gateway_name', 100);
                $table->string('display_name', 255);
                $table->boolean('active')->default(false);
                $table->text('config')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                
                $table->unique('gateway_name');
                
                // DEFINE CHARSET E COLLATION DA TABELA
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_general_ci';
            });
        }

        // Tabela de Auto Login - Tokens
        if (!Capsule::schema()->hasTable('mod_zapcel_autologin')) {
            Capsule::schema()->create('mod_zapcel_autologin', function ($table) {
                $table->increments('id');
                $table->integer('client_id')->unsigned();
                $table->string('token', 32);
                $table->string('target_type', 20);
                $table->integer('target_id')->unsigned();
                $table->timestamp('expires_at');
                $table->string('status', 20)->default('active');
                $table->integer('access_count')->default(0);
                $table->timestamp('last_access_at')->nullable();
                $table->string('last_ip', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
                
                $table->unique('token');
                $table->index('client_id');
                $table->index('status');
                $table->index('expires_at');
                $table->index(['target_type', 'target_id']);
                $table->index(['client_id', 'target_type', 'target_id']);
                
                // DEFINE CHARSET E COLLATION DA TABELA
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_general_ci';
            });
        }

        // Tabela de Auto Login - Acessos Detalhados
        if (!Capsule::schema()->hasTable('mod_zapcel_autologin_access')) {
            Capsule::schema()->create('mod_zapcel_autologin_access', function ($table) {
                $table->increments('id');
                $table->integer('autologin_id')->unsigned();
                $table->string('ip_address', 45);
                $table->integer('access_count')->default(1);
                $table->timestamp('first_access');
                $table->timestamp('last_access');
                $table->text('user_agent')->nullable();
                
                $table->index('autologin_id');
                $table->index('ip_address');
                $table->index('last_access');
                
                // DEFINE CHARSET E COLLATION DA TABELA
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_general_ci';
                
                // Foreign key (não suportado diretamente no Schema Builder)
                // Será criado via SQL raw após a criação
            });
            
            // Adiciona foreign key manualmente
            try {
                Capsule::statement('
                    ALTER TABLE `mod_zapcel_autologin_access`
                    ADD CONSTRAINT `fk_autologin_access`
                    FOREIGN KEY (`autologin_id`)
                    REFERENCES `mod_zapcel_autologin`(`id`)
                    ON DELETE CASCADE
                ');
            } catch (Exception $e) {
                // Ignora se já existir
            }
        }
        
        /*
            * Cria/atualiza as tabelas mod_zapcel_campaigns e mod_zapcel_campaign_queue (V3)
            * - Mantém o padrão do módulo (engine/charset/collation)
            * - Cria índices e UNIQUE para impedir duplicidade
            * - Cria FK com ON DELETE CASCADE
        */
        function zapcel_create_campaign_tables()
        {
            // =========================
            // mod_zapcel_campaigns
            // =========================
            if (!Capsule::schema()->hasTable('mod_zapcel_campaigns')) {
                Capsule::schema()->create('mod_zapcel_campaigns', function ($table) {
                    $table->increments('id');
        
                    $table->string('name', 255);
                    $table->text('message_template');
                    $table->string('language', 10)->default('pt');
                    $table->text('filters'); // JSON
        
                    $table->dateTime('schedule_start')->nullable();
        
                    // enums serão ajustados via SQL raw abaixo
                    $table->string('send_mode', 20)->default('business_hours'); // placeholder
                    $table->string('status', 20)->default('draft');            // placeholder
        
                    $table->integer('delay_min')->default(7);
                    $table->integer('delay_max')->default(13);
                    $table->integer('batch_size')->default(21);
        
                    $table->integer('total_contacts')->default(0);
                    $table->integer('sent_count')->default(0);
                    $table->integer('pending_count')->default(0);
                    $table->integer('failed_count')->default(0);
        
                    $table->dateTime('last_run')->nullable();
        
                    // timestamps padrão
                    $table->timestamps();
        
                    // ENGINE/CHARSET/COLLATION
                    $table->engine = 'InnoDB';
                    $table->charset = 'utf8mb4';
                    $table->collation = 'utf8mb4_unicode_ci';
                });
            }
        
            // Ajustes exatos do seu dump (ENUMs + defaults + índices)
            try {
                Capsule::statement("
                    ALTER TABLE `mod_zapcel_campaigns`
                    MODIFY `send_mode` enum('all_day','business_hours') DEFAULT 'business_hours'
                ");
            } catch (\Exception $e) {}
        
            try {
                Capsule::statement("
                    ALTER TABLE `mod_zapcel_campaigns`
                    MODIFY `status` enum('draft','active','paused','finished','scheduled') DEFAULT 'draft'
                ");
            } catch (\Exception $e) {}
        
            try { Capsule::statement("CREATE INDEX `idx_status` ON `mod_zapcel_campaigns` (`status`)"); } catch (\Exception $e) {}
            try { Capsule::statement("CREATE INDEX `idx_schedule_start` ON `mod_zapcel_campaigns` (`schedule_start`)"); } catch (\Exception $e) {}
        
            // =========================
            // mod_zapcel_campaign_queue
            // =========================
            if (!Capsule::schema()->hasTable('mod_zapcel_campaign_queue')) {
                Capsule::schema()->create('mod_zapcel_campaign_queue', function ($table) {
                    $table->increments('id');
        
                    $table->integer('campaign_id')->unsigned();
                    $table->integer('client_id')->unsigned();
        
                    // no dump final: NOT NULL DEFAULT 0
                    $table->integer('service_id')->unsigned()->default(0);
        
                    // dump: varchar(20)
                    $table->string('phone_number', 20);
        
                    // dump: message NOT NULL
                    $table->text('message');
        
                    // enum será ajustado via SQL raw abaixo
                    $table->string('status', 20)->default('pending'); // placeholder
        
                    $table->integer('attempts')->default(0);
        
                    $table->dateTime('sent_at')->nullable();
                    $table->text('error_message')->nullable();
        
                    $table->integer('delay_used')->nullable();
                    $table->dateTime('next_send_at')->nullable();
                    $table->dateTime('processing_started_at')->nullable();
        
                    $table->timestamps();
        
                    // ENGINE/CHARSET/COLLATION
                    $table->engine = 'InnoDB';
                    $table->charset = 'utf8mb4';
                    $table->collation = 'utf8mb4_unicode_ci';
                });
            }
        
            // Ajusta ENUM exato da fila
            try {
                Capsule::statement("
                    ALTER TABLE `mod_zapcel_campaign_queue`
                    MODIFY `status` enum('pending','processing','sent','failed') DEFAULT 'pending'
                ");
            } catch (\Exception $e) {}
        
            // Índices / anti-duplicidade (do seu dump)
            try { Capsule::statement("CREATE UNIQUE INDEX `uq_campaign_client_service` ON `mod_zapcel_campaign_queue` (`campaign_id`,`client_id`,`service_id`)"); } catch (\Exception $e) {}
            try { Capsule::statement("CREATE UNIQUE INDEX `uq_campaign_phone` ON `mod_zapcel_campaign_queue` (`campaign_id`,`phone_number`)"); } catch (\Exception $e) {}
        
            try { Capsule::statement("CREATE INDEX `idx_campaign_id` ON `mod_zapcel_campaign_queue` (`campaign_id`)"); } catch (\Exception $e) {}
            try { Capsule::statement("CREATE INDEX `idx_status` ON `mod_zapcel_campaign_queue` (`status`)"); } catch (\Exception $e) {}
            try { Capsule::statement("CREATE INDEX `idx_next_send_at` ON `mod_zapcel_campaign_queue` (`next_send_at`)"); } catch (\Exception $e) {}
            try { Capsule::statement("CREATE INDEX `idx_processing` ON `mod_zapcel_campaign_queue` (`status`,`processing_started_at`)"); } catch (\Exception $e) {}
        
            // índice mais importante para consumo do cron (campanha + status + next_send_at)
            try { Capsule::statement("CREATE INDEX `idx_campaign_status_next` ON `mod_zapcel_campaign_queue` (`campaign_id`,`status`,`next_send_at`)"); } catch (\Exception $e) {}
        
            try { Capsule::statement("CREATE INDEX `idx_processing_started` ON `mod_zapcel_campaign_queue` (`processing_started_at`)"); } catch (\Exception $e) {}
            try { Capsule::statement("CREATE INDEX `idx_client` ON `mod_zapcel_campaign_queue` (`client_id`)"); } catch (\Exception $e) {}
        
            // Foreign Key (igual seu padrão do autologin)
            try {
                Capsule::statement("
                    ALTER TABLE `mod_zapcel_campaign_queue`
                    ADD CONSTRAINT `fk_campaign`
                    FOREIGN KEY (`campaign_id`)
                    REFERENCES `mod_zapcel_campaigns`(`id`)
                    ON DELETE CASCADE
                ");
            } catch (\Exception $e) {}
        }
        
        // Insere templates padrão com data correta
        zapcel_install_default_templates();
        zapcel_create_campaign_tables();
        
        return [
            'status' => 'success',
            'description' => 'Zapcel WHMCS ativado com sucesso! Templates padrão instalados.'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Erro na ativação: ' . $e->getMessage()
        ];
    }
}

/**
 * Templates padrão melhorados - Tom simpático, educado e leve
 * Emojis seguros: ✅❌⚠️✔️⭐✉️☎️⚡ℹ️❗▶️⏸️⏹️⭕✖️⚙️⚫➡️▪️⬆️⬇️⬅️➕➖◼️◻️◾▫️●♻️⏰⭕™️
 */
function zapcel_install_default_templates()
{
    $defaultTemplates = [
        // 1. VALIDAÇÃO WHATSAPP
        [
            'name' => 'Validação WhatsApp',
            'trigger_event' => 'whatsapp_validation',
            'template' => "✔️ *Validação de WhatsApp*

Olá *{cliente}*, tudo bem?

Para validar seu número e receber nossas notificações, use o código abaixo:

⚡ *Código:* `{codigo_verificacao}`

⏰ Válido por 15 minutos

➡️ Acesse sua conta e insira este código para concluir.

{assinatura}",
            'active' => true
        ],
        
        // 2. CLIENTE ADICIONADO
        [
            'name' => 'Boas-vindas',
            'trigger_event' => 'client_added',
            'template' => "⭐ *Bem-vindo(a) à {provedor}!*

Olá *{cliente}*, é um prazer tê-lo(a) conosco!

✅ Seu cadastro foi realizado com sucesso
✉️ E-mail: {email}
☎️ Telefone: {telefone}
⏰ Data: {data_cadastro}

▶️ *Acesse sua área do cliente:*
{url_whmcs}

ℹ️ Precisa de ajuda? Estamos à disposição!

{assinatura}",
            'active' => true
        ],
        
        // 3. SENHA ALTERADA
        [
            'name' => 'Senha Alterada',
            'trigger_event' => 'password_changed',
            'template' => "⚙️ *Senha Alterada com Sucesso*

Olá *{cliente}*,

Sua senha foi alterada em *{data_alteracao}*.

⚠️ Se não foi você, entre em contato imediatamente!

✔️ *Dicas de segurança:*
▪️ Use senhas fortes e únicas
▪️ Não compartilhe suas credenciais
▪️ Ative a autenticação em duas etapas

{assinatura}",
            'active' => true
        ],
        
        // 4. FATURA CRIADA
        [
            'name' => 'Fatura Criada',
            'trigger_event' => 'invoice_created',
            'template' => "✔️ *Nova Fatura Disponível*

Olá *{cliente}*,

Uma nova fatura foi gerada em sua conta:

⭕ *Detalhes:*
▪️ Fatura: *#{numero_fatura}*
▪️ Valor: *{valor}*
▪️ Vencimento: *{vencimento}*
▪️ Itens: {itens_fatura}

⚡ Abaixo o código *PIX copia e cola*:
{quebrar_mensagem}
{codigopix}
{quebrar_mensagem}
✔️ Vou enviar o *QRCode do Pix*:
{quebrar_mensagem}
{qr_code_url}
{quebrar_mensagem}
✔️ *Código de barras:*
{quebrar_mensagem}
{linhadigitavel}
{quebrar_mensagem}

➡️ *Acessar fatura completa:*
{link_fatura}

ℹ️ Pague em dia e evite suspensões!

{assinatura}",
            'active' => true
        ],
        
        // 5. FATURA CANCELADA
        [
            'name' => 'Fatura Cancelada',
            'trigger_event' => 'invoice_cancelled',
            'template' => "✖️ *Fatura Cancelada*

Olá *{cliente}*,

A fatura *#{numero_fatura}* foi cancelada.

⭕ *Detalhes:*
▪️ Valor: {valor}
▪️ Cancelado em: *{data_cancelamento}*
▪️ Motivo: {motivo_cancelamento}

✅ Esta fatura não requer mais pagamento.

➡️ *Ver detalhes:*
{link_fatura}

{assinatura}",
            'active' => true
        ],
        
        // 6. LEMBRETE DE VENCIMENTO
        [
            'name' => 'Lembrete de Vencimento',
            'trigger_event' => 'invoice_reminder',
            'template' => "⏰ *Lembrete: Fatura Vencendo!*

Olá *{cliente}*,

⚠️ Venvimento: *{vencimento}*

⭕ *Detalhes:*
▪️ Fatura: *#{numero_fatura}*
▪️ Valor: *{valor}*
▪️ Dias restantes: {dias_vencimento}

⚡ Abaixo o código *PIX copia e cola*:
{quebrar_mensagem}
{codigopix}
{quebrar_mensagem}
✔️ Vou enviar o *QRCode do Pix*:
{quebrar_mensagem}
{qr_code_url}
{quebrar_mensagem}
✔️ *Código de barras:*
{quebrar_mensagem}
{linhadigitavel}
{quebrar_mensagem}

➡️ *Ver fatura:*
{link_fatura}

⚫ *Evite:*
▪️ Suspensão do serviço
▪️ Juros e multas
▪️ Interrupção no atendimento

{assinatura}",
            'active' => true
        ],
        
        // 7. PAGAMENTO CONFIRMADO
        [
            'name' => 'Pagamento Confirmado',
            'trigger_event' => 'invoice_paid',
            'template' => "✅ *Pagamento Confirmado!*

Olá *{cliente}*,

⭐ Recebemos seu pagamento com sucesso!

⭕ *Detalhes:*
▪️ Fatura: *#{numero_fatura}*
▪️ Valor: *{valor}*
▪️ Pago em: *{data_pagamento}*
▪️ Método: {metodo_pagamento}

✔️ Recibo disponível em sua área do cliente

▶️ Seus serviços continuam ativos e funcionando perfeitamente!

⭐ *Obrigado pela confiança!*

{assinatura}",
            'active' => true
        ],
        
        // 8. TICKET ABERTO
        [
            'name' => 'Ticket Aberto',
            'trigger_event' => 'ticket_opened',
            'template' => "✔️ *Ticket Aberto*

Olá *{cliente}*,

Seu ticket foi registrado com sucesso!

⭕ *Informações:*
▪️ Ticket: *#{numero_ticket}*
▪️ Assunto: *{assunto}*
▪️ Departamento: {departamento}
▪️ Prioridade: {prioridade}

⏰ *Tempo médio de resposta:* 2 horas

➡️ *Acompanhar ticket:*
{link_ticket}

✅ Estamos trabalhando para resolver!

{assinatura}",
            'active' => true
        ],
        
        // 9. RESPOSTA NO TICKET
        [
            'name' => 'Resposta no Ticket',
            'trigger_event' => 'ticket_reply',
            'template' => "✉️ *Nova Resposta no Ticket*

Olá *{cliente}*,

Sua solicitação foi respondida!

✔️ *Ticket:* #{numero_ticket}
⭕ *Assunto:* {assunto}

▪️ *Atendente:* {atendente}

➡️ *Acessar ticket:*
{link_ticket}

ℹ️ Precisa de mais ajuda? Responda no ticket!

{assinatura}",
            'active' => true
        ],
        
        // 10. SERVIÇO ATIVADO
        [
            'name' => 'Serviço Ativado',
            'trigger_event' => 'service_activated',
            'template' => "▶️ *Serviço Ativado!*

Olá *{cliente}*,

⭐ Seu serviço foi ativado com sucesso!

⭕ *Detalhes:*
▪️ Serviço: *{servico}*
▪️ Domínio: *{dominio}*
▪️ Ativado em: *{data_ativacao}*

✔️ *Próximos passos:*
1. Acesse o painel de controle
2. Configure suas preferências
3. Comece a usar!

➡️ *Área do cliente:*
{url_whmcs}

ℹ️ Precisa de ajuda? Estamos aqui!

{assinatura}",
            'active' => true
        ],
        
        // 11. SERVIÇO SUSPENSO
        [
            'name' => 'Serviço Suspenso',
            'trigger_event' => 'service_suspended',
            'template' => "⏸️ *Serviço Suspenso*

Olá *{cliente}*,

Seu serviço foi suspenso temporariamente.

⭕ *Detalhes:*
▪️ Serviço: *{servico}*
▪️ Domínio: *{dominio}*
▪️ Suspenso em: *{data_suspensao}*
▪️ Motivo: {motivo}

♻️ *Para reativar:*
1. Regularize pendências financeiras
2. Entre em contato conosco
3. Aguarde a reativação

☎️ *Suporte:*
{url_whmcs}

⏰ Resolva rápido para evitar cancelamento!

{assinatura}",
            'active' => true
        ],
        
        // 12. SERVIÇO REATIVADO
        [
            'name' => 'Serviço Reativado',
            'trigger_event' => 'service_unsuspended',
            'template' => "✅ *Serviço Reativado!*

Olá *{cliente}*,

⭐ Seu serviço foi reativado com sucesso!

⭕ *Detalhes:*
▪️ Serviço: *{servico}*
▪️ Domínio: *{dominio}*
▪️ Reativado em: *{data_reativacao}*

▶️ *Status:* Suspenso → *Ativo*

✔️ Tudo voltou ao normal! Seu serviço já está funcionando.

ℹ️ *Dica:* Mantenha seus pagamentos em dia para evitar novas suspensões.

⭐ *Obrigado por resolver!*

{assinatura}",
            'active' => true
        ],
        
        // 13. SERVIÇO CANCELADO
        [
            'name' => 'Serviço Cancelado',
            'trigger_event' => 'service_terminated',
            'template' => "⏹️ *Serviço Cancelado*

Olá *{cliente}*,

Seu serviço foi cancelado conforme solicitado.

⭕ *Detalhes:*
▪️ Serviço: {servico}
▪️ Domínio: {dominio}
▪️ Cancelado em: *{data_cancelamento}*

⚙️ *Backup de dados:*
Seus dados estarão disponíveis por 7 dias para download.

⬅️ *Quer voltar?*
Estamos sempre aqui para atendê-lo novamente!

⭐ Sentiremos sua falta!

{assinatura}",
            'active' => true
        ],
        
        // 14. SOLICITAÇÃO DE CANCELAMENTO
        [
            'name' => 'Solicitação de Cancelamento',
            'trigger_event' => 'cancellation_request',
            'template' => "✔️ *Solicitação de Cancelamento Recebida*

Olá *{cliente}*,

Recebemos sua solicitação de cancelamento.

⭕ *Detalhes:*
▪️ Serviço: *{nome_servico}*
▪️ ID: #{id_servico}
▪️ Domínio: {dominio}
▪️ Tipo: {tipo_cancelamento}
▪️ Razão: {razao_cancelamento}

⏰ *Processamento:*
Sua solicitação será processada em até 24h.

ℹ️ *Importante:*
▪️ Faça backup dos seus dados antes do cancelamento
▪️ O serviço permanecerá ativo até a data final

☎️ *Dúvidas?* Entre em contato!

{assinatura}",
            'active' => true
        ],
        
        // 15. CLIENTE EDITADO
        [
            'name' => 'Dados Atualizados',
            'trigger_event' => 'client_edited',
            'template' => "⚙️ *Dados Atualizados*

Olá *{cliente}*,

Seus dados foram atualizados com sucesso!

⭕ *Alterações realizadas:*
{alteracoes}

⏰ *Atualizado em:* {data_alteracao}

⚠️ *Segurança:*
Caso não tenha sido você, entre em contato imediatamente.

☎️ *Contato:*
{url_whmcs}

{assinatura}",
            'active' => true
        ],
        
        // 16. COTAÇÃO CRIADA
        [
            'name' => 'Cotação Criada',
            'trigger_event' => 'quote_created',
            'template' => "✔️ *Nova Cotação Disponível*

Olá *{cliente}*,

Preparamos uma cotação personalizada para você!

⭕ *Detalhes:*
▪️ Cotação: *#{numero_cotacao}*
▪️ Assunto: *{subject_cotacao}*
▪️ Valor: *{valor_cotacao}*
▪️ Validade: *{validade_cotacao}*
▪️ Status: {status_cotacao}

◼️ *Itens incluídos:*
{itens_cotacao}

✔️ *Próximos passos:*
1. Revise os detalhes
2. Aprove a cotação
3. Inicie o serviço

ℹ️ Dúvidas? Responda este contato!

{assinatura}",
            'active' => true
        ],
        
        // 17. COTAÇÃO MODIFICADA
        [
            'name' => 'Cotação Atualizada',
            'trigger_event' => 'quote_modified',
            'template' => "♻️ *Cotação Atualizada*

Olá *{cliente}*,

Sua cotação foi atualizada!

⭕ *Detalhes:*
▪️ Cotação: *#{numero_cotacao}*
▪️ Assunto: *{subject_cotacao}*
▪️ Valor: *{valor_cotacao}*
▪️ Status: {status_cotacao}

⚙️ *Alterações realizadas:*
{alteracoes}

◼️ *Itens revisados:*
{itens_cotacao}

⏰ *Validade:* {validade_cotacao}

ℹ️ Precisa de mais ajustes? Estamos à disposição!

{assinatura}",
            'active' => true
        ],
        
        // 18. COTAÇÃO ACEITA
        [
            'name' => 'Cotação Aceita',
            'trigger_event' => 'quote_accepted',
            'template' => "⭐ *Cotação Aceita!*

Olá *{cliente}*,

✅ Ótima escolha! Sua cotação foi aceita.

⭕ *Detalhes:*
▪️ Cotação: *#{numero_cotacao}*
▪️ Assunto: *{subject_cotacao}*
▪️ Valor: *{valor_cotacao}*
▪️ Aceita em: *{data_aceitacao}*

▶️ *Próximos passos:*
1. Processaremos seu pedido
2. Ativaremos os serviços
3. Enviaremos confirmação

⏰ Tempo de ativação: 1-2 horas úteis

⭐ *Obrigado pela confiança!*

{assinatura}",
            'active' => true
        ],
        
        // 19. SUBSTITUIÇÃO DE EMAIL
        [
            'name' => 'Notificação WhatsApp',
            'trigger_event' => 'email_presend',
            'template' => "✉️ *Notificação Importante*

Olá *{cliente}*,

⭕ *Assunto:* {assunto}

▪️ *Serviço:* {tipo_servico}
▪️ *Domínio:* {dominio}
▪️ *Produto:* {nome_produto}

✔️ *Detalhes:*
{mensagem}

⚙️ *Informações de acesso:*
▪️ IP: {ip_dedicado}
▪️ Usuário: {usuario}
▪️ Senha: {senha}

ℹ️ Precisa de ajuda? Responda esta mensagem!

{assinatura}",
            'active' => true
        ],

        // 20. MENSAGEM DE TESTE
        [
            'name' => 'Mensagem de Teste',
            'trigger_event' => 'test_message',
            'template' => "⚙️ *Mensagem de Teste*

Olá *{cliente}*,

Esta é uma mensagem de teste do sistema WhatsApp.

✅ *Status:* Sistema funcionando perfeitamente!
⏰ *Data:* {data_atual}
⏰ *Hora:* {hora_atual}

⚙️ *Variáveis testadas:*
▪️ Provedor: {provedor}
▪️ Assinatura: {assinatura}
▪️ URL: {url_whmcs}

✔️ Teste concluído com sucesso!

{assinatura}",
            'active' => true
        ],
        // SERVIÇO ATIVADO - HOSPEDAGEM
    [
    'name' => 'Hospedagem Ativada',
    'trigger_event' => 'service_activated_hosting',
    'template' => "🌐 *Hospedagem Ativada com Sucesso!*

Olá *{cliente}*,

⭐ Sua hospedagem foi ativada e está pronta para uso!

📍 *Detalhes do Serviço:*
▪️ Plano: *{servico}*
▪️ Domínio: *{dominio}*
▪️ Ativado em: *{data_ativacao}*

🔐 *Dados de Acesso:*
▪️ Painel: cPanel
▪️ Servidor: {servidor}
▪️ IP: {ip_servidor}

✅ *Próximos Passos:*
1. Acesse o cPanel
2. Configure seus e-mails
3. Faça upload do seu site
4. Configure SSL gratuito

➡️ *Acessar painel:*
{url_whmcs}/clientarea.php?action=productdetails&id={servico_id}

📚 *Precisa de ajuda?*
Confira nossa base de conhecimento ou abra um ticket!

{assinatura}",
    'active' => true
],

// SERVIÇO ATIVADO - REVENDA
[
    'name' => 'Revenda Ativada',
    'trigger_event' => 'service_activated_reseller',
    'template' => "🏢 *Revenda de Hospedagem Ativada!*

Olá *{cliente}*,

⭐ Seu plano de revenda foi ativado com sucesso!

📍 *Detalhes do Serviço:*
▪️ Plano: *{servico}*
▪️ Domínio: *{dominio}*
▪️ Ativado em: *{data_ativacao}*

🔐 *Painéis de Controle:*
▪️ WHM: https://{servidor}:2087
▪️ cPanel: https://{servidor}:2083
▪️ Servidor: {servidor}

💼 *Recursos Inclusos:*
▪️ Contas cPanel ilimitadas
▪️ WHMCS gratuito (se aplicável)
▪️ SSL gratuito para todos os domínios
▪️ Suporte técnico prioritário

✅ *Comece Agora:*
1. Acesse o WHM
2. Crie suas primeiras contas
3. Configure pacotes de hospedagem
4. Integre com seu WHMCS

➡️ *Área do cliente:*
{url_whmcs}/clientarea.php?action=productdetails&id={servico_id}

📚 *Documentação para Revendedores:*
Acesse nossa wiki com tutoriais completos!

{assinatura}",
    'active' => true
],

// SERVIÇO ATIVADO - VPS/DEDICADO
[
    'name' => 'VPS/Dedicado Ativado',
    'trigger_event' => 'service_activated_vps',
    'template' => "🖥️ *Servidor Ativado e Pronto!*

Olá *{cliente}*,

⭐ Seu servidor foi provisionado e está online!

📍 *Detalhes do Servidor:*
▪️ Plano: *{servico}*
▪️ Hostname: *{dominio}*
▪️ Ativado em: *{data_ativacao}*

🔐 *Informações de Acesso:*
▪️ IP Principal: *{ip_servidor}*
▪️ Sistema: {sistema_operacional}
▪️ Painel: {painel_controle}

⚙️ *Especificações:*
▪️ CPU: {cpu_cores} cores
▪️ RAM: {memoria_ram} GB
▪️ Disco: {espaco_disco} GB
▪️ Banda: {banda_mensal}

⚠️ *IMPORTANTE - Segurança:*
1. Altere a senha root imediatamente
2. Configure firewall
3. Ative atualizações automáticas
4. Configure backups

➡️ *Acessar detalhes:*
{url_whmcs}/clientarea.php?action=productdetails&id={servico_id}

🛡️ *Suporte Técnico:*
Nossa equipe está disponível 24/7 para auxiliar!

{assinatura}",
            'active' => true
        ],
// ==========================================
// ADICIONAR ESTES 4 TEMPLATES NO ARRAY $defaultTemplates
// LOCALIZAR: Linha 958 (antes do fechamento do array ]);)
// ADICIONAR: Logo após o template 'VPS/Dedicado Ativado'
// ==========================================

        // 24. LEMBRETE DE FATURA — 1º
        [
            'name' => 'Lembrete de Fatura — 1º',
            'trigger_event' => 'invoice_reminder_1',
            'template' => "⏰ *1º Lembrete: Fatura Vencendo em Breve*

Olá *{cliente}*,

⚠️ Vencimento: *{dias_vencimento}, em (*{vencimento}*)

⭕ *Detalhes:*
▪️ Fatura: *#{numero_fatura}*
▪️ Valor: *{valor}*
▪️ Vencimento: *{vencimento}*

⚡ Abaixo o código *PIX copia e cola*:
{quebrar_mensagem}
{codigopix}
{quebrar_mensagem}
✔️ Vou enviar o *QRCode do Pix*:
{quebrar_mensagem}
{qr_code_url}
{quebrar_mensagem}
✔️ *Código de barras:*
{quebrar_mensagem}
{linhadigitavel}
{quebrar_mensagem}

➡️ *Acessar fatura:*
{link_fatura}

✅ Pague em dia e evite juros!

{assinatura}",
            'active' => true
        ],

        // 25. LEMBRETE DE FATURA — 2º
        [
            'name' => 'Lembrete de Fatura — 2º',
            'trigger_event' => 'invoice_reminder_2',
            'template' => "⚠️ *2º Lembrete: Fatura Vence Amanhã!*

Olá *{cliente}*,

⭕ *ATENÇÃO:* Sua fatura vence *amanhã* (*{vencimento}*)

⭕ *Detalhes:*
▪️ Fatura: *#{numero_fatura}*
▪️ Valor: *{valor}*
▪️ Status: Pendente

⚡ Abaixo o código *PIX copia e cola*:
{quebrar_mensagem}
{codigopix}
{quebrar_mensagem}
✔️ Vou enviar o *QRCode do Pix*:
{quebrar_mensagem}
{qr_code_url}
{quebrar_mensagem}
✔️ *Código de barras:*
{quebrar_mensagem}
{linhadigitavel}
{quebrar_mensagem}

➡️ *Ver fatura:*
{link_fatura}

⚫ *Evite:*
▪️ Suspensão do serviço
▪️ Juros e multas (2% + 1% ao mês)
▪️ Interrupção no atendimento

⏰ *Pague hoje e evite transtornos!*

{assinatura}",
            'active' => true
        ],

        // 26. LEMBRETE DE FATURA — 3º
        [
            'name' => 'Lembrete de Fatura — 3º',
            'trigger_event' => 'invoice_reminder_3',
            'template' => "⭕ *3º Lembrete: FATURA VENCIDA!*

Olá *{cliente}*,

❌ Sua fatura está *VENCIDA* desde *{vencimento}*

⭕ *Detalhes:*
▪️ Fatura: *#{numero_fatura}*
▪️ Valor original: *{valor}*
▪️ Dias em atraso: *{dias_vencimento}*
▪️ Status: *VENCIDA*

⚠️ *IMPORTANTE:*
Seu serviço pode ser suspenso a qualquer momento!

⚡ Abaixo o código *PIX copia e cola*:
{quebrar_mensagem}
{codigopix}
{quebrar_mensagem}
✔️ Vou enviar o *QRCode do Pix*:
{quebrar_mensagem}
{qr_code_url}
{quebrar_mensagem}
✔️ *Código de barras:*
{quebrar_mensagem}
{linhadigitavel}
{quebrar_mensagem}

➡️ *Acessar fatura:*
{link_fatura}

⚫ *Consequências do não pagamento:*
▪️ Suspensão imediata do serviço
▪️ Juros de 2% + 1% ao mês
▪️ Cancelamento após 15 dias
▪️ Perda de dados e configurações

☎️ *Dificuldades para pagar?*
Entre em contato conosco para negociar!

{assinatura}",
            'active' => true
        ],

        // 27. SERVIÇO ATIVADO - OUTROS
        [
            'name' => 'Outros Serviços Ativados',
            'trigger_event' => 'service_activated_other',
            'template' => "⭐ *Serviço Ativado com Sucesso!*

Olá *{cliente}*,

✅ Seu serviço foi ativado e está pronto para uso!

⭕ *Detalhes do Serviço:*
▪️ Serviço: *{servico}*
▪️ Domínio: *{dominio}*
▪️ Ativado em: *{data_ativacao}*

✔️ *Informações de Acesso:*
▪️ Servidor: {servidor}
▪️ IP: {ip_dedicado}

✅ *Próximos Passos:*
1. Acesse o painel de controle
2. Configure suas preferências
3. Comece a usar seu serviço!

➡️ *Área do cliente:*
{url_whmcs}/clientarea.php

ℹ️ *Precisa de ajuda?*
Nossa equipe está à disposição!

{assinatura}",
            'active' => true
        ],
        // 24. LEMBRETE DE VENCIMENTO (GENÉRICO)
[
    'name' => 'Lembrete de Vencimento',
    'trigger_event' => 'invoice_reminder',
    'template' => "⏰ *Lembrete: Fatura Vencendo!*

Olá *{cliente}*,

⚠️ Venvimento: *{vencimento}*

⭕ *Detalhes:*
▪️ Fatura: *#{numero_fatura}*
▪️ Valor: *{valor}*
▪️ Dias restantes: {dias_vencimento}

⚡ Abaixo o código *PIX copia e cola*:
{quebrar_mensagem}
{codigopix}
{quebrar_mensagem}
✔️ Vou enviar o *QRCode do Pix*:
{quebrar_mensagem}
{qr_code_url}
{quebrar_mensagem}
✔️ *Código de barras:*
{quebrar_mensagem}
{linhadigitavel}
{quebrar_mensagem}

➡️ *Ver fatura:*
{link_fatura}

⚫ *Evite:*
▪️ Suspensão do serviço
▪️ Juros e multas
▪️ Interrupção no atendimento

{assinatura}",
            'active' => true
        ],
    ];
    
    foreach ($defaultTemplates as $template) {
        // Verifica se template já existe
        $exists = Capsule::table('mod_zapcel_templates')
            ->where('trigger_event', $template['trigger_event'])
            ->exists();
        
        // Só insere se NÃO existir
        if (!$exists) {
            Capsule::table('mod_zapcel_templates')->insert($template);
        }
    }
}


/**
 * Desativação do módulo
 */
function zapcel_deactivate()
{
    // Preserva dados para possível reativação
    return [
        'status' => 'success',
        'description' => 'Zapcel desativado. Dados preservados para reativação futura.'
    ];
}

/**
 * Upgrade do módulo
 */
function zapcel_upgrade($vars)
{
    $currentVersion = $vars['version'];
    
    try {
        // Migração da versão 1.x para 2.x
        if (version_compare($currentVersion, '2.0.0', '<')) {
            zapcel_migrate_to_v2();
        }
        
        return [
            'status' => 'success',
            'description' => 'Zapcel atualizado para a versão ' . $vars['version']
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Erro na atualização: ' . $e->getMessage()
        ];
    }
}

/**
 * Migração para a versão 2.0
 */
function zapcel_migrate_to_v2()
{
    // Migração de dados antigos se necessário
    // Esta função será implementada conforme a necessidade
}

/**
 * Output do painel administrativo
 */
function zapcel_output($vars)
{
    // Verifica acesso administrativo
    if (!isset($_SESSION['adminid'])) {
        die('Acesso restrito a administradores.');
    }
    
    // Dispatcher para o painel administrativo
    $action = $_REQUEST['action'] ?? 'dashboard';
    
    // CORREÇÃO: Cria o dispatcher apenas uma vez
    // O require_once já foi feito no início do arquivo
    $dispatcher = new \WHMCS\Module\Addon\Zapcel\Admin\AdminDispatcher($vars);
    
    try {
        // Se for AJAX, o dispatch() já faz echo e exit
        // Não precisamos fazer echo aqui
        if ($action === 'ajax') {
            $dispatcher->dispatch($action);
        } else {
            echo $dispatcher->dispatch($action);
        }
    } catch (Exception $e) {
        if ($action === 'ajax') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } else {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
        }
    }
}

/**
 * Área do cliente
 */
function zapcel_clientarea($vars)
{
    $clientId = $_SESSION['uid'] ?? 0;

    // Carrega as traduções do módulo usando seu sistema existente
    $moduleLang = [];
    try {
        $langSetting = Capsule::table('tbladdonmodules')
            ->where('module', 'zapcel')
            ->where('setting', 'language')
            ->value('value') ?? 'portuguese';

        $langFile = $langSetting === 'english'
            ? __DIR__ . '/langs/en.php'
            : __DIR__ . '/langs/pt.php';

        if (file_exists($langFile)) {
            $moduleLang = include $langFile;
            if (!is_array($moduleLang)) { 
                $moduleLang = []; 
            }
        }
    } catch (\Throwable $e) {
        $moduleLang = [];
    }
    
    if (!$clientId) {
        return [
            'pagetitle' => 'Zapcel',
            'templatefile' => 'client/validacao',
            'requirelogin' => true,
            'vars' => [
                'MODULE_LANG' => $moduleLang
            ],
        ];
    }
    
    // Busca configuração
    $validacaoObrigatoria = Capsule::table('tbladdonmodules')
        ->where('module', 'zapcel')
        ->where('setting', 'zapcel_validation')
        ->value('value');
    
    // Se validação obrigatória está ativada
    if ($validacaoObrigatoria == "1") {
        
        // Busca validação do cliente
        $validacao = Capsule::table('mod_zapcel_validation')
            ->where('client_id', $clientId)
            ->first();
        
        // Se NÃO validado, mostra página de validação
        if (!$validacao || $validacao->validated != 1) {
            
            // Busca cliente
            $client = Capsule::table('tblclients')->where('id', $clientId)->first();
            
            // Cria registro se não existe
            if (!$validacao) {
                Capsule::table('mod_zapcel_validation')->insert([
                    'client_id' => $clientId,
                    'phone_number' => $client->phonenumber ?? '',
                    'verification_code' => '',
                    'validated' => 0,
                    'status' => 'pending',
                    'attempts' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                
                $validacao = Capsule::table('mod_zapcel_validation')->where('client_id', $clientId)->first();
            }
            
            // Processa reset - voltar para enviar novo código
            if (isset($_GET['reset']) && $_GET['reset'] == '1') {
                // Limpa o código de verificação no banco para forçar novo envio
                Capsule::table('mod_zapcel_validation')
                    ->where('client_id', $clientId)
                    ->update([
                        'verification_code' => '',
                        'status' => 'pending',
                        'attempts' => 0,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                
                // Redireciona para a mesma página sem o parâmetro reset
                header('Location: index.php?m=zapcel');
                exit();
            }

            // Processa POST - Enviar código
            if (isset($_POST['send_code'])) {
                require_once __DIR__ . '/api/NumberValidator.php';
                
                $settings = Capsule::table('tbladdonmodules')
                    ->where('module', 'zapcel')
                    ->pluck('value', 'setting')
                    ->toArray();
                
                $validator = new \WHMCS\Module\Addon\Zapcel\Api\NumberValidator($settings);

                // CORREÇÃO: pega o número do campo do formulário
                $rawPhoneNumber = $_POST['phonenumber'];
                $phoneNumber = $validator->validatePhoneFormat($rawPhoneNumber);

                // ATUALIZA O NÚMERO NA TABELA DE VALIDAÇÃO
                Capsule::table('mod_zapcel_validation')
                    ->where('client_id', $clientId)
                    ->update(['phone_number' => $phoneNumber]);
                    
                $result = $validator->initiateValidation($clientId, $phoneNumber);
                
                if ($result['success']) {
                    $vars['success_message'] = $result['message'];
                    $validacao = Capsule::table('mod_zapcel_validation')->where('client_id', $clientId)->first();
                } else {
                    $vars['error_message'] = $result['error'];
                }
            }

            // Processa POST - Validar código
            if (isset($_POST['validate_code'])) {
                require_once __DIR__ . '/api/NumberValidator.php';
                
                $settings = Capsule::table('tbladdonmodules')
                    ->where('module', 'zapcel')
                    ->pluck('value', 'setting')
                    ->toArray();
                
                $validator = new \WHMCS\Module\Addon\Zapcel\Api\NumberValidator($settings);
                $inputCode = $_POST['code'] ?? '';
                $result = $validator->verifyCode($clientId, $inputCode);
                
                if ($result['success']) {
                    
                    // ATUALIZA O NÚMERO NO BANCO APÓS VALIDAÇÃO BEM-SUCEDIDA
                    $phoneNumber = preg_replace('/^(\+?\d{2})(\d{2})(\d{5})(\d{4})$/', '+$1.$2 $3-$4', preg_replace('/\D+/', '', $_POST['phonenumber']));
                    Capsule::table('tblclients')
                        ->where('id', $clientId)
                        ->update(['phonenumber' => $phoneNumber]);
                    
                    // Validado! Redireciona para clientarea
                    header('Location: clientarea.php');
                    exit();
                } else {
                    $vars['error_message'] = $result['error'];
                }
            }

            // Verifica se código expirou (15 minutos)
            $code_expired = false;
            if ($validacao && $validacao->status == 'pending' && !empty($validacao->verification_code)) {
                if (strtotime($validacao->updated_at) < strtotime('-15 minutes')) {
                    $code_expired = true;
                }
            }

            // Recarrega o cliente diretamente da tabela principal (WHMCS)
            $client = Capsule::table('tblclients')->where('id', $clientId)->first();

            // Retorna página de validação
            return [
                'pagetitle' => 'Validação WhatsApp',
                'breadcrumb' => ['index.php?m=zapcel' => 'Validação WhatsApp'],
                'templatefile' => 'zapcel_validation',
                'requirelogin' => true,
                'vars' => [
                    'validation' => $validacao,
                    'client' => $client,
                    'error_message' => $vars['error_message'] ?? null,
                    'success_message' => $vars['success_message'] ?? null,
                    'code_expired' => $code_expired,
                    'MODULE_LANG' => $moduleLang 
                ],
            ];
            
            // Processa POST - Validar código
            if (isset($_POST['validate_code'])) {
                require_once __DIR__ . '/api/NumberValidator.php';
                
                $settings = Capsule::table('tbladdonmodules')
                    ->where('module', 'zapcel')
                    ->pluck('value', 'setting')
                    ->toArray();
                
                $validator = new \WHMCS\Module\Addon\Zapcel\Api\NumberValidator($settings);
                $inputCode = $_POST['code'] ?? '';
                $result = $validator->verifyCode($clientId, $inputCode);
                
                if ($result['success']) {

                    // ATUALIZA O NÚMERO NO BANCO APÓS VALIDAÇÃO BEM-SUCEDIDA
                    $phoneNumber = preg_replace('/^(\+?\d{2})(\d{2})(\d{5})(\d{4})$/', '+$1.$2 $3-$4', preg_replace('/\D+/', '', $_POST['phonenumber']));
                    Capsule::table('tblclients')
                        ->where('id', $clientId)
                        ->update(['phonenumber' => $phoneNumber]);

                    // Validado! Redireciona para clientarea
                    header('Location: clientarea.php');
                    exit();
                } else {
                    $vars['error_message'] = $result['error'];
                }
            }
            
            // Retorna página de validação
            return [
                'pagetitle' => 'Validação WhatsApp',
                'breadcrumb' => ['index.php?m=zapcel' => 'Validação WhatsApp'],
                'templatefile' => 'zapcel_validation',
                'requirelogin' => true,
                'vars' => [
                    'validation' => $validacao,
                    'client' => $client,
                    'MODULE_LANG' => $moduleLang 
                ],
            ];
        }
    }
    
    // Cliente validado ou validação não obrigatória
    // Mostra página normal do módulo
    return [
        'pagetitle' => 'Zapcel - Configurações WhatsApp',
        'breadcrumb' => ['index.php?m=zapcel' => 'WhatsApp'],
        'templatefile' => 'client/configuracoes',
        'requirelogin' => true,
        'vars' => [
            'client_id' => $clientId,
            'MODULE_LANG' => $moduleLang
        ],
    ];
}

/**
 * Obtém número de telefone do cliente
 */
function zapcel_get_client_phone($vars)
{
    $clientId = $_SESSION['uid'];
    
    // Tenta obter do campo personalizado se configurado
    if (!empty($vars['zapcel_phone_source']) && $vars['zapcel_phone_source'] !== 'phonenumber') {
        $customField = Capsule::table('tblcustomfields')
            ->where('fieldname', $vars['zapcel_phone_source'])
            ->first();
            
        if ($customField) {
            $customValue = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $customField->id)
                ->where('relid', $clientId)
                ->first();
                
            if ($customValue && !empty($customValue->value)) {
                return $customValue->value;
            }
        }
    }
    
    // Fallback para o campo padrão
    $client = Capsule::table('tblclients')
        ->where('id', $clientId)
        ->first();
        
    return $client->phonenumber ?? '';
}

// CARREGA OS HOOKS DO SISTEMA
// CRÍTICO: Sem isso, nenhuma mensagem automática é enviada!
require_once __DIR__ . '/hooks.php'; /// 742 paginas.