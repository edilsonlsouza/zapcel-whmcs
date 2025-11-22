<style>
.zapcel-validation-container {
    max-width: 600px;
    margin: 40px auto;
    padding: 20px;
}

.validation-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 40px;
}

.validation-header {
    text-align: center;
    margin-bottom: 30px;
}

.validation-header i {
    font-size: 64px;
    color: #25D366;
    margin-bottom: 20px;
}

.validation-header h2 {
    color: #333;
    font-size: 28px;
    margin-bottom: 10px;
}

.validation-header p {
    color: #666;
    font-size: 16px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #555;
    font-weight: 600;
    font-size: 14px;
}

.selected-flag {
    font-size: 22px; !important;
    transform: scale(1.3) !important;
    padding-left: 30px !important;
}

.form-control-modern {
    text-align: center !important;
    font-size: 32px;
    letter-spacing: 6px !important;
    font-weight: 600 !important;
    padding: 20px !important;
    width: 100% !important;
    border: 2px solid #e0e0e0 !important;
    border-radius: 8px !important;
    margin-bottom: 15px !important;
    height: 60px !important;
    padding-left: 110px !important;
    transition: all 0.3s;
    box-sizing: border-box;
}

.form-control-modern:focus {
    outline: none;
    border-color: #25D366;
    box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.1);
}

.btn-whatsapp {
    width: 100%;
    padding: 14px;
    background: #25D366;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-whatsapp:hover {
    background: #128C7E;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
}

.btn-secondary {
    width: 100%;
    padding: 12px;
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 10px;
}

.btn-secondary:hover {
    background: #5a6268;
}

.input_codigo_validacao {
    text-align: center !important;
    font-size: 32px !important;
    letter-spacing: 12px !important;
    font-weight: 600 !important;
    padding: 20px !important;
    width: 100% !important;
    border: 2px solid #e0e0e0 !important;
    border-radius: 8px !important;
    margin-bottom: 15px !important;
    height: 60px !important;
}

.input_codigo_validacao:focus {
    outline: none;
    border-color: #25D366;
    box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.1);
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.info-text {
    color: #999;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 5px;
}
</style>

<div class="zapcel-validation-container">
    <div class="validation-card">
        
        <div class="validation-header">
            <i class="fab fa-whatsapp"></i>
            <h2>{$MODULE_LANG.whatsapp_validation}</h2>
            <p>{$MODULE_LANG.whatsapp_validation_subtitle}</p>
        </div>

        {if $error_message}
        <div class="alert alert-danger">
            <strong>{$MODULE_LANG.error}:</strong> {$error_message}
        </div>
        {/if}

        {if $success_message}
        <div class="alert alert-success">
            <strong>{$MODULE_LANG.success}:</strong> {$success_message}
        </div>
        {/if}

        {if $code_expired || !$validation || $validation->status != 'pending' || empty($validation->verification_code)}
            
            <!-- FORMULÁRIO DE ENVIO DE CÓDIGO -->
            <form method="POST" action="index.php?m=zapcel">
                <div class="form-group">
                    <label for="phonenumber">
                        <i class="fas fa-phone"></i> {$MODULE_LANG.phone_number_label}
                    </label>
                    <input type="tel" 
                           id="phonenumber" 
                           name="phonenumber"
                           class="form-control-modern" 
                           placeholder="81 99999-9999"
                           value="{$client->phonenumber}" maxlength="13">
                    <small class="info-text">
                        <i class="fas fa-info-circle"></i> 
                        {$MODULE_LANG.phone_number_help}
                    </small>
                </div>
                

                <button class="btn-whatsapp" type="submit" name="send_code">
                    <i class="fab fa-whatsapp"></i>
                    {$MODULE_LANG.send_code_button}
                </button>
                
            </form>
            
        {else}
            
            <!-- FORMULÁRIO DE VALIDAÇÃO DE CÓDIGO -->
            <form method="POST" action="index.php?m=zapcel">
                <!-- CAMPO HIDDEN COM O NÚMERO -->
                <input type="hidden" name="phonenumber" value="{$validation->phone_number}">
                <div class="form-group">
                    <label for="code">
                        <i class="fas fa-key"></i> {$MODULE_LANG.verification_code_label}
                    </label>
                    <input type="text" 
                           id="code" 
                           name="code"
                           class="input_codigo_validacao" 
                           placeholder="000000"
                           maxlength="6"
                           pattern="[0-9]{ldelim}6{rdelim}"
                           required
                           autofocus>
                    <small class="info-text">
                        <i class="fas fa-info-circle"></i> 
                        {$MODULE_LANG.verification_code_help}
                    </small>
                    <small class="info-text" style="color: #ff6b6b; font-weight: 600; margin-top: 10px;">
                        <i class="fas fa-clock"></i> 
                        {$MODULE_LANG.code_expires_in_15_minutes}
                    </small>
                </div>

                <button class="btn-whatsapp" type="submit" name="validate_code">
                    <i class="fas fa-check"></i>
                    {$MODULE_LANG.validate_code_button}
                </button>

                <!-- BOTÃO PARA VOLTAR E RECOMEÇAR -->
                <button class="btn-secondary" type="button" onclick="window.location.href='index.php?m=zapcel&reset=1'">
                    <i class="fas fa-redo"></i>
                    {$MODULE_LANG.back_resend_button}
                </button>

            </form>
            
        {/if}

    </div>
</div>

<script>
// Auto-focus no input de código
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('code');
    if (codeInput) {
        codeInput.focus();
    }
});
</script>