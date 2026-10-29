<?php
// ==================== PÁGINA SECRETA DE DENÚNCIA ====================
session_start();

// GATILHO FRONTIER ACADEMICO
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'index.html') !== false) {
    $_SESSION['modo_seguro'] = true;
}

// Incluir conexão com banco
if (file_exists('../backend/config/database.php')) {
    require_once '../backend/config/database.php';
}

// Verificar permissão de acesso (COMENTADO PARA FORÇAR A PÁGINA A ABRIR)
// if (!isset($_SESSION['modo_seguro']) || $_SESSION['modo_seguro'] !== true) {
//     header('Location: ../index.html');
//     exit();
// }

// PHPMailer manual: arquivos oficiais da pasta src, sem Composer.
$phpmailerDir = __DIR__ . '/../PHPMailer/src/';
$configEmail = __DIR__ . '/../backend/config/email.php';
$arquivosMailer = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
$phpmailerDisponivel = true;
foreach ($arquivosMailer as $arquivoMailer) {
    if (!is_file($phpmailerDir . $arquivoMailer)) {
        $phpmailerDisponivel = false;
        break;
    }
}
if ($phpmailerDisponivel) {
    foreach ($arquivosMailer as $arquivoMailer) {
        require_once $phpmailerDir . $arquivoMailer;
    }
}
function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$dados = array_fill_keys(['nome','telefone','cep','rua','bairro','cidade','uf','numero','referencia','sem_cep','tipo_situacao','nivel_urgencia','descricao'], '');
if (isset($_SESSION['usuario_id']) && class_exists('Database')) {
    try {
        $db = Database::getInstance()->getConnection();
        $q = $db->prepare("SELECT nome,cep,rua,bairro,cidade,numero_casa FROM usuario WHERE id_usuario=:id LIMIT 1");
        $q->execute([':id'=>$_SESSION['usuario_id']]);
        if ($u=$q->fetch(PDO::FETCH_ASSOC)) {
            foreach (['nome','cep','rua','bairro','cidade'] as $k) $dados[$k]=$u[$k] ?? '';
            $dados['numero']=$u['numero_casa'] ?? '';
        }
    } catch (Throwable $e) { error_log('Erro de cadastro: '.$e->getMessage()); }
}
// Categorias exibidas no formulário e validadas também no servidor.
$tiposSituacao = [
    'fisica' => 'Violência Física / Agressão',
    'psicologica' => 'Violência Psicológica / Ameaças',
    'sexual' => 'Violência Sexual / Abuso',
    'patrimonial' => 'Violência Patrimonial / Financeira',
    'tentativa_feminicidio' => 'Tentativa de Feminicídio',
    'perigo_imediato' => 'Estou em Perigo Imediato',
    'outros' => 'Outras Situações de Violência',
];
$niveisUrgencia = [
    'imediato' => 'Sim, preciso de ajuda imediatamente.',
    'recente' => 'Não, mas a violência aconteceu recentemente.',
    'anterior' => 'Não, quero relatar uma situação anterior.',
];
$mensagem_status = $status_tipo = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['enviar_denuncia'])) {
    foreach ($dados as $k=>$v) $dados[$k]=trim((string)($_POST[$k] ?? ''));
    $cep=preg_replace('/\D/','',$dados['cep']);
    $semCep=$dados['sem_cep']==='1';
    $local=implode(', ',array_filter(array_map('trim',[
        $dados['rua'],$dados['numero'],$dados['bairro'],$dados['cidade'],$dados['uf'],$dados['referencia']
    ]),static fn($v)=>$v!==''));
    if ($dados['nome']==='' || $dados['descricao']==='' || !array_key_exists($dados['tipo_situacao'],$tiposSituacao) || !array_key_exists($dados['nivel_urgencia'],$niveisUrgencia)) {
        $mensagem_status='Informe nome, tipo de situação, urgência e descrição.';
    } elseif (!$semCep && $cep!=='' && strlen($cep)!==8) {
        $mensagem_status='Confira o CEP informado.';
    } elseif ($semCep && $local==='') {
        $mensagem_status='Informe ao menos uma localização ou ponto de referência.';
    } elseif (!$phpmailerDisponivel || !is_file($configEmail)) {
        $mensagem_status='Envio por e-mail ainda não configurado. Avise a equipe.';
    } else {
        try {
            $smtp=require $configEmail;
            if (!is_array($smtp) || empty($smtp['host']) || empty($smtp['porta']) || empty($smtp['usuario']) || empty($smtp['senha_app']) || $smtp['senha_app'] === 'INSIRA_AQUI_SENHA_DE_APLICATIVO') {
                throw new RuntimeException('Configuração SMTP incompleta.');
            }
            $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host=$smtp['host'];
            $mail->SMTPAuth=true;
            $mail->Username=$smtp['usuario'];
            $mail->Password=$smtp['senha_app'];
            $mail->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port=(int)$smtp['porta'];
            $mail->CharSet='UTF-8';
            $mail->setFrom($smtp['usuario'],'Her Glow - Teste Academico');
            $mail->addAddress('etecalecrim2008@gmail.com');
            $mail->isHTML(false);
            $mail->Subject='Her Glow | Nova solicitacao de teste';
            $mail->Body="PROTÓTIPO ACADÊMICO - NÃO ENCAMINHADO ÀS AUTORIDADES\n\n"
                ."Nome: {$dados['nome']}\nTelefone seguro: {$dados['telefone']}\n"
                ."CEP: ".($semCep?'Não informado':$cep)."\nEndereço informado: $local\n"
                ."Situação: ".$tiposSituacao[$dados['tipo_situacao']]."\n"
                ."Urgência: ".$niveisUrgencia[$dados['nivel_urgencia']]."\n"
                ."Descrição: {$dados['descricao']}\n"
                ."Data: ".date('d/m/Y H:i:s');
            $mail->send();
            $mensagem_status='Solicitação encaminhada ao e-mail de teste do grupo. Não aciona serviços de emergência.';
            $status_tipo='sucesso';
            foreach ($dados as $k=>$v) $dados[$k]='';
        } catch (Throwable $e) {
            error_log('Erro SMTP: '.$e->getMessage());
            $mensagem_status='Não foi possível encaminhar a solicitação. Tente novamente ou utilize os canais oficiais.';
        }
    }
    if ($status_tipo!=='sucesso') $status_tipo='erro';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">

    <title>Her Glow - Central de Atendimento</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%E2%9A%A0%EF%B8%8F%3C/text%3E%3C/svg%3E">

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Montserrat:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
    >

    <style>

        /* ==================== RESET E CONFIGURAÇÕES SOMBRIAS ==================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #121212 0%, #1a1a1a 100%);
            color: #e0e0e0;
            min-height: 100vh;
            padding: 40px 20px;
        }

        h1,
        h2,
        h3 {
            font-family: 'Playfair Display', serif;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* ==================== CARD PRINCIPAL ==================== */

        .card {
            background: #1e1e1e;
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            overflow: hidden;
            border: 1px solid #333333;
        }

        .card-header {
            background: linear-gradient(135deg, #2a2a2a 0%, #151515 100%);
            padding: 30px;
            text-align: center;
            color: #ffffff;
            border-bottom: 2px solid #b71c1c;
        }

        .card-header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .card-header p {
            font-size: 0.85rem;
            color: #aaaaaa;
        }

        .card-body {
            padding: 30px;
        }

        /* ==================== FORMULÁRIO ==================== */

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #dddddd;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 1.5px solid #444444;
            border-radius: 16px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s;
            outline: none;
            background: #252525;
            color: #ffffff;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #b71c1c;
            box-shadow: 0 0 0 3px rgba(183, 28, 28, 0.3);
        }

        .form-group input:disabled {
            background: #1a1a1a;
            color: #777777;
            border-color: #222222;
        }

        .form-hint {
            font-size: 0.75rem;
            color: #888888;
            margin-top: 6px;
            font-style: italic;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        select option {
            background: #252525;
            color: #ffffff;
        }

        /* ==================== BOTÕES E ALERTAS ==================== */

        .btn-submit {
            width: 100%;
            background: #b71c1c;
            color: #ffffff;
            border: none;
            padding: 16px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(183, 28, 28, 0.4);
        }

        .btn-submit:hover {
            background: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(211, 47, 47, 0.6);
        }

        .status-message {
            padding: 14px 18px;
            border-radius: 16px;
            margin-bottom: 24px;
            font-size: 0.85rem;
            text-align: center;
            font-weight: 600;
        }

        .status-message.sucesso {
            background: #1b5e20;
            color: #e8f5e9;
            border-left: 4px solid #4caf50;
        }

        .status-message.erro {
            background: #b71c1c;
            color: #ffebee;
            border-left: 4px solid #f44336;
        }

        /* ==================== LINHAS DE APOIO DIRETAS ==================== */

        .emergencia {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 20px;
            margin-top: 30px;
            border: 1px solid #333333;
        }

        .emergencia h3 {
            font-size: 1.1rem;
            color: #ff5252;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .emergencia-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .contato-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #252525;
            border-radius: 12px;
            border: 1px solid #444444;
            transition: all 0.2s;
        }

        .contato-item:hover {
            transform: translateX(5px);
            background: #2d2d2d;
            border-color: #ff5252;
        }

        .contato-item i {
            width: 35px;
            height: 35px;
            background: #333333;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ff5252;
            font-size: 1rem;
        }

        .contato-info {
            flex: 1;
        }

        .contato-info strong {
            display: block;
            font-size: 0.8rem;
            color: #aaaaaa;
        }

        .contato-info span {
            font-size: 1rem;
            font-weight: 700;
            color: #ffffff;
        }

        /* ==================== SAÍDA RÁPIDA FLUTUANTE ==================== */

        .btn-exit-rapido {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #b71c1c;
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn-exit-rapido:hover {
            background: #e53935;
            transform: scale(1.05);
        }

        @media (max-width: 600px) {

            body {
                padding: 20px 10px;
            }

            .card-header {
                padding: 20px;
            }

            .card-body {
                padding: 20px;
            }

            .emergencia-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <div class="card-header">

            <h1>Central de Atendimento ao Cliente</h1>

            <p>
                Sua segurança e satisfação são nossas prioridades.
                Conte com nosso suporte especializado.
            </p>

        </div>

        <div class="card-body">

            <?php if (!empty($mensagem_status)): ?>

                <div class="status-message <?php echo $status_tipo; ?>">
                    <?php echo $mensagem_status; ?>
                </div>

            <?php endif; ?>

            <form id="formDenuncia" method="POST" action="">

                <div class="form-group">

                    <label for="nome">Nome Completo</label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?php echo esc($dados['nome']); ?>"
                        required
                        placeholder="Digite seu nome completo"
                    >

                </div>

                <div class="form-group">

                    <label for="telefone">Telefone para Contato Preferencial</label>

                    <input
                        type="tel"
                        id="telefone"
                        name="telefone"
                        value="<?php echo esc($dados['telefone']); ?>"
                        placeholder="(00) 00000-0000"
                    >

                    <div class="form-hint">
                        Informe um número seguro onde possamos falar diretamente com você.
                    </div>

                </div>

                <div class="form-group">
                    <label for="cep">CEP de referência</label>
                    <input id="cep" name="cep" type="text" inputmode="numeric" maxlength="9"
                        placeholder="00000-000" value="<?php echo esc($dados['cep']); ?>">
                    <div class="form-hint" id="cepStatus" aria-live="polite">Digite o CEP para preencher o endereço automaticamente.</div>
                    <label style="margin-top:12px;text-transform:none;letter-spacing:0">
                        <input type="checkbox" id="sem_cep" name="sem_cep" value="1" style="width:auto;margin-right:8px"
                            <?php echo $dados['sem_cep']==='1'?'checked':''; ?>>
                        Não sei o CEP / preciso informar outro local
                    </label>
                </div>
                <div class="form-group">
                    <label for="rua">Rua</label>
                    <input id="rua" name="rua" type="text" placeholder="Preenchimento automático ou manual"
                        value="<?php echo esc($dados['rua']); ?>">
                </div>
                <div class="form-group">
                    <label for="bairro">Bairro</label>
                    <input id="bairro" name="bairro" type="text" placeholder="Bairro"
                        value="<?php echo esc($dados['bairro']); ?>">
                </div>
                <div class="form-group">
                    <label for="cidade">Cidade</label>
                    <input id="cidade" name="cidade" type="text" placeholder="Cidade"
                        value="<?php echo esc($dados['cidade']); ?>">
                </div>
                <div class="form-group">
                    <label for="uf">UF</label>
                    <input id="uf" name="uf" type="text" placeholder="SP"
                        value="<?php echo esc($dados['uf']); ?>">
                </div>
                <div class="form-group">
                    <label for="numero">Número (opcional)</label>
                    <input id="numero" name="numero" type="text" placeholder="Número"
                        value="<?php echo esc($dados['numero']); ?>">
                </div>
                <div class="form-group">
                    <label for="referencia">Ponto de referência (opcional)</label>
                    <input id="referencia" name="referencia" type="text" placeholder="Localização aproximada ou complemento"
                        value="<?php echo esc($dados['referencia']); ?>">
                </div>
                <div class="form-group">
                    <label for="tipo_situacao">Qual situação deseja relatar?</label>
                    <select id="tipo_situacao" name="tipo_situacao" required>
                        <option value="" disabled <?php echo $dados['tipo_situacao']===''?'selected':''; ?>>Selecione uma situação...</option>
                        <?php foreach ($tiposSituacao as $valor => $rotulo): ?>
                            <option value="<?php echo esc($valor); ?>" <?php echo $dados['tipo_situacao']===$valor?'selected':''; ?>><?php echo esc($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nivel_urgencia">Você está em perigo neste momento?</label>
                    <select id="nivel_urgencia" name="nivel_urgencia" required>
                        <option value="" disabled <?php echo $dados['nivel_urgencia']===''?'selected':''; ?>>Selecione uma opção...</option>
                        <?php foreach ($niveisUrgencia as $valor => $rotulo): ?>
                            <option value="<?php echo esc($valor); ?>" <?php echo $dados['nivel_urgencia']===$valor?'selected':''; ?>><?php echo esc($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">Em perigo imediato, ligue 190. Este protótipo não aciona a polícia nem serviços de emergência.</div>
                </div>

                <div class="form-group">

                    <label for="descricao">
                        Mensagem / Descrição Breve dos Detalhes
                    </label>

                    <textarea
                        id="descricao"
                        name="descricao"
                        required
                        placeholder="Conte, em poucas palavras, o que aconteceu."
                    ><?php echo esc($dados['descricao']); ?></textarea>

                </div>

                <button
                    type="submit"
                    name="enviar_denuncia"
                    class="btn-submit"
                >
                    Enviar Solicitação
                </button>

            </form>

            <div class="emergencia">

                <h3>
                    <i class="fas fa-phone-alt"></i>
                    Linhas de Apoio Direto
                </h3>

                <div class="emergencia-grid">

                    <div class="contato-item">

                        <i class="fas fa-headset"></i>

                        <div class="contato-info">

                            <strong>Central de Alerta</strong>

                            <span>Ligue 180</span>

                        </div>

                    </div>

                    <div class="contato-item">

                        <i class="fas fa-shield-alt"></i>

                        <div class="contato-info">

                            <strong>Polícia Militar</strong>

                            <span>Ligue 190</span>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<button class="btn-exit-rapido" id="btnSairRapido">

    <i class="fas fa-running"></i>
    Sair Rápido

</button>

<script>

    // ==================== SAÍDA RÁPIDA ====================
    // Volta para a página inicial da loja.
    // Não fecha a aba e não utiliza window.close().

    const btnSair = document.getElementById('btnSairRapido');

    if (btnSair) {

        btnSair.addEventListener('click', function() {

            window.location.href = '../index.html';

        });

    }


    // Busca automática ViaCEP; os campos permanecem editáveis.
    const cepInput=document.getElementById('cep');
    const cepStatus=document.getElementById('cepStatus');
    const semCep=document.getElementById('sem_cep');
    let buscaAtual=0, espera;
    semCep.addEventListener('change',()=>{
        buscaAtual++; clearTimeout(espera);
        cepInput.disabled=semCep.checked;
        cepStatus.textContent=semCep.checked?'Informe o local manualmente.':'Digite o CEP para preencher automaticamente.';
    });
    cepInput.disabled=semCep.checked;
    cepInput.addEventListener('input',()=>{
        clearTimeout(espera);
        const id=++buscaAtual;
        const digitos=cepInput.value.replace(/\D/g,'').slice(0,8);
        cepInput.value=digitos.length>5?digitos.slice(0,5)+'-'+digitos.slice(5):digitos;
        if(digitos.length!==8) {cepStatus.textContent='Informe os oito números do CEP.';return;}
        cepStatus.textContent='Consultando CEP...';
        espera=setTimeout(async()=>{
            try {
                const r=await fetch('https://viacep.com.br/ws/'+digitos+'/json/');
                if(!r.ok) throw Error('Consulta indisponível');
                const d=await r.json();
                if(id!==buscaAtual || semCep.checked)return;
                if(d.erro){cepStatus.textContent='CEP não encontrado. Confira ou preencha manualmente.';return;}
                for(const [k,v] of Object.entries({rua:d.logradouro,bairro:d.bairro,cidade:d.localidade,uf:d.uf}))
                    document.getElementById(k).value=v||'';
                cepStatus.textContent='Endereço localizado. Confira os dados e ajuste se necessário.';
            } catch(e) {if(id===buscaAtual)cepStatus.textContent='Consulta indisponível. Preencha manualmente.';}
        },350);
    });

    // ==================== VALIDAÇÃO DO FORMULÁRIO ====================

    const form = document.getElementById('formDenuncia');

    if (form) {

        form.addEventListener('submit', function(e) {
            if (semCep.checked && !['rua','bairro','cidade','referencia'].some(k=>document.getElementById(k).value.trim())) {
                e.preventDefault(); alert('Informe uma localização ou ponto de referência.'); return false;
            }

            const nome = document.getElementById('nome').value.trim();

            const descricao = document.getElementById('descricao').value.trim();

            const tipo = document.getElementById('tipo_situacao').value;
            const urgencia = document.getElementById('nivel_urgencia').value;


            if (!nome) {

                e.preventDefault();

                alert('Por favor, informe seu nome.');

                return false;

            }


            if (!descricao) {

                e.preventDefault();

                alert('Por favor, descreva brevemente a situação.');

                return false;

            }


            if (!tipo) {

                e.preventDefault();

                alert('Por favor, selecione o tipo de situação.');

                return false;

            }


            if (!urgencia) {
                e.preventDefault();
                alert('Por favor, informe se você está em perigo neste momento.');
                return false;
            }

            if (!confirm('Este é um protótipo acadêmico. Os dados serão enviados ao e-mail de teste do grupo, não às autoridades. Deseja continuar?')) {
                e.preventDefault(); return false;
            }
            return true;

        });

    }

</script>

</body>

</html>