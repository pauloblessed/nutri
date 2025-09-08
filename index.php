<?php
session_start();

// Redireciona se já estiver logado
if (isset($_SESSION['user'])) {
    header('Location: frame.php');
    exit;
}

// Conexão Supabase
$SUPABASE_URL = 'https://vjpxlkyfjtrhdsyrxsko.supabase.co/rest/v1/users';
$SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZqcHhsa3lmanRyaGRzeXJ4c2tvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDQ4OTUwMDEsImV4cCI6MjA2MDQ3MTAwMX0.HkMPdlcpT75u75z6j5BbBwYW8QLzQGKBJxyHb-St7k0';

// Função para validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf); // Remove caracteres não numéricos
    if (strlen($cpf) !== 11) {
        return false;
    }
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += $cpf[$i] * (($t + 1) - $i);
        }
        $soma = ((10 * $soma) % 11) % 10;
        if ($cpf[$t] != $soma) {
            return false;
        }
    }
    return true;
}

// Função para validar CRN
function validarCRN($crn) {
    if (empty($crn)) {
        return true; // CRN é opcional
    }
    return preg_match('/^CRN-[1-9][0]?[ ]?[0-9]{1,5}$/', trim($crn));
}

// Processar login
$mensagem_login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $login = isset($_POST['login']) ? trim($_POST['login']) : '';
    $senha = isset($_POST['senha']) ? trim($_POST['senha']) : '';

    if (!$login || !$senha) {
        $mensagem_login = '<div class="alert alert-danger">Preencha todos os campos.</div>';
    } else {
        // Buscar usuário pelo email
        $url = "$SUPABASE_URL?email=eq." . urlencode($login);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY",
            "Content-Type: application/json"
        ]);

        $resposta = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $usuarios = json_decode($resposta, true) ?? [];

        if ($http_code === 200 && count($usuarios) > 0 && password_verify($senha, $usuarios[0]['senha'])) {
            // Login bem-sucedido - Configura fuso horário
            $_SESSION['user'] = [
                'id' => $usuarios[0]['id'],
                'nome' => $usuarios[0]['nome'],
                'nivel' => $usuarios[0]['nivel'],
                'timezone' => $usuarios[0]['timezone'] ?? 'America/Sao_Paulo'
            ];
            
            date_default_timezone_set($_SESSION['user']['timezone']);
            header('Location: frame.php');
            exit;
        } else {
            $mensagem_login = '<div class="alert alert-danger">Login ou senha incorretos.</div>';
        }
    }
}

// Processar cadastro
$mensagem_cadastro = '';
$cadastro_sucesso = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cadastro') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $cidade_cad = trim($_POST['cidade_cad'] ?? '');
    $registro_classe = trim($_POST['registro_classe'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $timezone = trim($_POST['timezone'] ?? 'America/Sao_Paulo');
    $origem = $_SERVER['HTTP_HOST'] . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');

    // Validação de campos obrigatórios
    if (!$nome || !$email || !$senha || !$cidade_cad) {
        $mensagem_cadastro = '<div class="alert alert-danger">Preencha todos os campos obrigatórios: Nome, Email, Senha e Cidade.</div>';
    } elseif (!validarCPF($cpf) && !empty($cpf)) {
        $mensagem_cadastro = '<div class="alert alert-danger">CPF inválido.</div>';
    } elseif (!validarCRN($registro_classe)) {
        $mensagem_cadastro = '<div class="alert alert-danger">CRN inválido. Use o formato CRN-X NNNNN (ex.: CRN-3 12345).</div>';
    } else {
        // Verificar se o email já existe
        $url = "$SUPABASE_URL?email=eq." . urlencode($email);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY",
            "Content-Type: application/json"
        ]);
        $resposta = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $usuarios = json_decode($resposta, true) ?? [];

        if ($http_code === 200 && count($usuarios) > 0) {
            $mensagem_cadastro = '<div class="alert alert-danger">Este email já está cadastrado.</div>';
        } else {
            // Dados do novo usuário
            $dados = [
                'nome' => $nome,
                'email' => $email,
                'senha' => password_hash($senha, PASSWORD_DEFAULT), // Hash da senha
                'cidade_cad' => $cidade_cad,
                'registro_classe' => $registro_classe ?: null,
                'cpf' => $cpf ?: null,
                'nivel' => 'Usuário',
                'timezone' => $timezone,
                'origem' => $origem,
                'created_at' => date('c')
            ];

            // Enviar dados para o Supabase
            $ch = curl_init($SUPABASE_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $SUPABASE_KEY",
                "Authorization: Bearer $SUPABASE_KEY",
                "Content-Type: application/json",
                "Prefer: return=representation"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
            $resposta = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 201) {
                $mensagem_login = '<div class="alert alert-success">Cadastro realizado com sucesso! Você já pode fazer login.</div>';
                $cadastro_sucesso = true; // Flag para fechar o modal e limpar o formulário
            } else {
                $mensagem_cadastro = '<div class="alert alert-danger">Erro ao realizar cadastro. Tente novamente.</div>';
            }
        }
    }
}

// Lista de fusos horários brasileiros
$timezones = [
    'America/Sao_Paulo' => 'São Paulo (UTC-3)',
    'America/Manaus' => 'Manaus (UTC-4)',
    'America/Cuiaba' => 'Cuiabá (UTC-4)',
    'America/Porto_Velho' => 'Porto Velho (UTC-4)',
    'America/Rio_Branco' => 'Rio Branco (UTC-5)',
    'America/Recife' => 'Recife (UTC-3)',
    'America/Fortaleza' => 'Fortaleza (UTC-3)',
    'America/Belem' => 'Belém (UTC-3)',
    'America/Noronha' => 'Fernando de Noronha (UTC-2)'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Nutricional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .slogan {
            text-align: center;
            margin-bottom: 20px;
        }
        .slogan h1 {
            font-size: 2.5rem;
            font-weight: bold;
            color: #8B0000; /* Vermelho escuro */
        }
        .slogan p {
            font-size: 1.2rem;
            color: #6c757d;
        }
        .form-label { font-weight: bold; }
        .required::after { content: " *"; color: red; }
        .invalid-feedback { display: none; }
        .is-invalid ~ .invalid-feedback { display: block; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="slogan">
            <h1>SISTEMA GRATUITO PARA NUTRICIONISTAS</h1>
            <p>Desenvolvido por programadores, estudantes de nutrição e professores</p>
        </div>
        <h2 class="text-center mb-4">Login</h2>
        <?= $mensagem_login ?>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
                <label for="login" class="form-label">Email:</label>
                <input type="email" class="form-control" id="login" name="login" required>
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Senha:</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
            <div class="text-center mt-3">
                <p>ou</p>
                <a href="login_google.php" class="btn btn-danger w-100">Entrar com Google</a>
                <p class="mt-3">Não tem uma conta? <a href="#" data-bs-toggle="modal" data-bs-target="#cadastroModal">Cadastrar</a></p>
            </div>
        </form>
    </div>

    <!-- Modal de Cadastro -->
    <div class="modal fade" id="cadastroModal" tabindex="-1" aria-labelledby="cadastroModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cadastroModalLabel">Cadastro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Campos obrigatórios <span style="color: red;">*</span></p>
                    <?= $mensagem_cadastro ?>
                    <form method="POST" id="cadastroForm">
                        <input type="hidden" name="action" value="cadastro">
                        <div class="mb-3">
                            <label for="nome" class="form-label required">Nome:</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label required">Email:</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            <div class="invalid-feedback">Este email já está cadastrado.</div>
                        </div>
                        <div class="mb-3">
                            <label for="senha" class="form-label required">Senha:</label>
                            <input type="password" class="form-control" id="senha" name="senha" required>
                        </div>
                        <div class="mb-3">
                            <label for="cidade_cad" class="form-label required">Cidade:</label>
                            <input type="text" class="form-control" id="cidade_cad" name="cidade_cad" value="<?= htmlspecialchars($_POST['cidade_cad'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="registro_classe" class="form-label">CRN (Registro de Classe):</label>
                            <input type="text" class="form-control" id="registro_classe" name="registro_classe" value="<?= htmlspecialchars($_POST['registro_classe'] ?? '') ?>" placeholder="Ex.: CRN-3 12345">
                            <div class="invalid-feedback">CRN inválido. Use o formato CRN-X NNNNN (ex.: CRN-3 12345).</div>
                        </div>
                        <div class="mb-3">
                            <label for="cpf" class="form-label">CPF:</label>
                            <input type="text" class="form-control" id="cpf" name="cpf" value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>" placeholder="Ex.: 123.456.789-00">
                            <div class="invalid-feedback">CPF inválido.</div>
                        </div>
                        <div class="mb-3">
                            <label for="timezone" class="form-label">Fuso Horário:</label>
                            <select class="form-control" id="timezone" name="timezone">
                                <?php foreach ($timezones as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($value === 'America/Sao_Paulo') ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Cadastrar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Função para validar CPF no cliente
        function validarCPF(cpf) {
            cpf = cpf.replace(/[^\d]/g, '');
            if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
                return false;
            }
            let soma = 0, resto;
            for (let i = 1; i <= 9; i++) {
                soma += parseInt(cpf[i-1]) * (11 - i);
            }
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            if (resto !== parseInt(cpf[9])) return false;
            soma = 0;
            for (let i = 1; i <= 10; i++) {
                soma += parseInt(cpf[i-1]) * (12 - i);
            }
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            return resto === parseInt(cpf[10]);
        }

        // Função para validar CRN no cliente
        function validarCRN(crn) {
            if (!crn) return true; // CRN é opcional
            return /^CRN-[1-9][0]?[ ]?[0-9]{1,5}$/.test(crn);
        }

        // Validação de e-mail no cliente
        document.getElementById('email').addEventListener('blur', async function() {
            const email = this.value;
            const feedback = this.nextElementSibling;
            if (email) {
                try {
                    const response = await fetch(`<?=$SUPABASE_URL?>?email=eq.${encodeURIComponent(email)}`, {
                        headers: {
                            'apikey': '<?=$SUPABASE_KEY?>',
                            'Authorization': 'Bearer <?=$SUPABASE_KEY?>',
                            'Content-Type': 'application/json'
                        }
                    });
                    const usuarios = await response.json();
                    if (usuarios.length > 0) {
                        this.classList.add('is-invalid');
                        feedback.textContent = 'Este email já está cadastrado.';
                    } else {
                        this.classList.remove('is-invalid');
                        feedback.textContent = 'Este email já está cadastrado.'; // Mantém mensagem padrão, mas escondida
                    }
                } catch (error) {
                    console.error('Erro ao verificar email:', error);
                }
            } else {
                this.classList.remove('is-invalid');
            }
        });

        // Validação no cliente para CPF e CRN
        document.getElementById('cpf').addEventListener('blur', function() {
            const cpf = this.value;
            const feedback = this.nextElementSibling;
            if (cpf && !validarCPF(cpf)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });

        document.getElementById('registro_classe').addEventListener('blur', function() {
            const crn = this.value;
            const feedback = this.nextElementSibling;
            if (crn && !validarCRN(crn)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });

        // Limpar formulário e fechar modal após cadastro bem-sucedido
        <?php if ($cadastro_sucesso): ?>
            document.getElementById('cadastroForm').reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('cadastroModal'));
            modal.hide();
        <?php endif; ?>
    </script>
</body>
</html>