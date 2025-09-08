<?php
session_start();

// Logout handler
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Configura o fuso horário do usuário (com fallback para São Paulo)
date_default_timezone_set($_SESSION['user']['timezone'] ?? 'America/Sao_Paulo');

// Buscar pacientes handler
if (isset($_GET['action']) && $_GET['action'] === 'buscar_pacientes') {
    header('Content-Type: application/json');
    
    $user_id = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    if (!$user_id || $user_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de usuário inválido']);
        exit;
    }

    $SUPABASE_URL = 'https://vjpxlkyfjtrhdsyrxsko.supabase.co/rest/v1/pacientes';
    $SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZqcHhsa3lmanRyaGRzeXJ4c2tvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDQ4OTUwMDEsImV4cCI6MjA2MDQ3MTAwMX0.HkMPdlcpT75u75z6j5BbBwYW8QLzQGKBJxyHb-St7k0';
    
    $termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
    if (strlen($termo) < 2) {
        echo json_encode([]);
        exit;
    }

    $termo_escapado = urlencode("%$termo%");
    $query = "user_id=eq.$user_id&or=(nome.ilike.$termo_escapado,email.ilike.$termo_escapado,cpf.ilike.$termo_escapado,tel.ilike.$termo_escapado)";
    $url = "$SUPABASE_URL?$query";

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

    if ($http_code !== 200) {
        http_response_code($http_code);
        echo json_encode(['error' => 'Erro ao consultar pacientes']);
        exit;
    }

    $pacientes = json_decode($resposta, true) ?? [];
    echo json_encode($pacientes);
    exit;
}

// Atualizar sessão do paciente
if (isset($_POST['action']) && $_POST['action'] === 'atualizar_sessao_paciente') {
    $_SESSION['paciente'] = [
        'id' => $_POST['paciente_id'],
        'nome' => $_POST['nome'],
        'sexo' => $_POST['sexo'],
        'data_nascimento' => $_POST['data_nascimento']
    ];
    echo json_encode(['success' => true]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Sistema Nutricional</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            padding-top: 70px;
            background-color: #f8f9fa;
        }
        #conteudo-frame {
            width: 100%;
            height: calc(100vh - 120px);
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 8px 8px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        .paciente-highlight {
            font-weight: bold;
            color: #0d6efd;
        }
        .list-group {
            width: 100%;
            max-width: 400px;
        }
        .navbar {
            background: linear-gradient(90deg, #343a40, #495057);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar .btn-icon {
            color: white;
            background: none;
            border: none;
            font-size: 1.2rem;
            padding: 0.5rem;
            margin: 0 0.2rem;
            transition: color 0.2s;
        }
        .navbar .btn-icon:hover {
            color: #0d6efd;
        }
        .navbar .btn-logout {
            color: #dc3545;
        }
        .navbar .btn-logout:hover {
            color: #b02a37;
        }
        .tab-bar {
            background-color: #e9ecef;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            border-bottom: 1px solid #dee2e6;
        }
        .tab {
            padding: 10px 20px;
            margin: 0 2px -1px 2px;
            color: white;
            text-align: center;
            text-decoration: none;
            border: 1px solid #dee2e6;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            transition: opacity 0.2s;
            cursor: pointer;
            position: relative;
            z-index: 1;
        }
        .tab:hover {
            opacity: 0.8;
        }
        .tab-dietas { background-color: #0d6efd; }
        .tab-exames { background-color: #28a745; }
        .tab-bioimpedancia { background-color: #6f42c1; }
        .tab-balanca { background-color: #fd7e14; }
        .tab-habitos { background-color: #17a2b8; }
        .tab-alimentacao { background-color: #dc3545; }
        .tab-saude { background-color: #ffc107; }
        .tab-peso { background-color: #20c997; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <span class="navbar-brand">Nutricionista: <?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
            
            <div class="d-flex align-items-center me-3">
                <button class="btn btn-icon me-2" onclick="carregarPagina('pacientes.php')" title="Home">
                    <i class="fas fa-home"></i>
                </button>
                <?php if (isset($_SESSION['user']['nivel']) && $_SESSION['user']['nivel'] === 'Admin'): ?>
                    <button class="btn btn-icon me-2" onclick="carregarPagina('financeiro.php')" title="Financeiro">
                        <i class="fas fa-dollar-sign"></i>
                    </button>
                <?php endif; ?>
                <button class="btn btn-icon me-2" onclick="carregarPagina('usuarios.php')" title="Usuários">
                    <i class="fas fa-user"></i>
                </button>
                <button class="btn btn-icon me-2" title="Configurações">
                    <i class="fas fa-cog"></i>
                </button>
                <button class="btn btn-icon me-2" title="Chat">
                    <i class="fas fa-comment"></i>
                </button>
                <a href="?action=logout" class="btn btn-icon btn-logout me-3" title="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                <span class="text-white">Paciente Escolhido: <span id="pacienteSelecionado" class="paciente-highlight">
                    <?= isset($_SESSION['paciente']['nome']) ? htmlspecialchars($_SESSION['paciente']['nome']) : 'Nenhum' ?>
                </span></span>
            </div>

            <form class="d-flex ms-auto" role="search" id="buscaPacienteForm">
                <input class="form-control me-2" type="search" id="buscaPaciente" placeholder="Buscar paciente..." aria-label="Search">
                <input type="hidden" id="paciente_id_selecionado" name="paciente_id" value="<?= isset($_SESSION['paciente']['id']) ? $_SESSION['paciente']['id'] : '' ?>">
            </form>
        </div>
    </nav>

    <div class="container-fluid">
        <?php if (isset($_SESSION['paciente']['id'])): ?>
            <div class="tab-bar">
                <a class="tab tab-dietas" onclick="carregarPagina('criar_dietas.php')">Criar Dietas</a>
                <a class="tab tab-exames" onclick="carregarPagina('exames.php')">Exames</a>
                <a class="tab tab-bioimpedancia" onclick="carregarPagina('bioimpedancia.php')">Bioimpedancia</a>
                <a class="tab tab-balanca" onclick="carregarPagina('balancaBioOcr.php')">Balança Bio</a>
                <a class="tab tab-habitos" onclick="carregarPagina('habitos.php')">Hábitos</a>
                <a class="tab tab-alimentacao" onclick="carregarPagina('alimentacao.php')">Alimentação</a>
                <a class="tab tab-saude" onclick="carregarPagina('saude.php')">Saúde</a>
                <a class="tab tab-peso" onclick="carregarPagina('pesoemedidas.php')">Peso e Medidas</a>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-12 pt-3">
                <iframe id="conteudo-frame" src="pacientes.php"></iframe>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let pacientes = [];

        $(document).ready(function() {
            $('#buscaPaciente').on('input', function() {
                let termo = $(this).val().toLowerCase();
                $('.list-group').remove();

                if (termo.length >= 2) {
                    $.ajax({
                        url: '?action=buscar_pacientes',
                        method: 'GET',
                        data: { termo: termo },
                        dataType: 'json',
                        success: function(resposta) {
                            console.log('Resposta da busca:', resposta);
                            pacientes = resposta;
                            let lista = pacientes.filter(p => 
                                (p.nome?.toLowerCase() || '').includes(termo) ||
                                (p.email?.toLowerCase() || '').includes(termo) ||
                                (p.cpf?.toLowerCase() || '').includes(termo) ||
                                (p.tel?.toLowerCase() || '').includes(termo)
                            );
                            if (lista.length > 0) {
                                mostrarSugestoes(lista);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro na busca:', { status, error, responseText: xhr.responseText, statusCode: xhr.status });
                            alert('Erro ao buscar pacientes.');
                        }
                    });
                }
            });
        });

        function mostrarSugestoes(lista) {
            let html = '<ul class="list-group position-absolute" style="z-index:1000; max-height:200px; overflow-y:auto;">';
            lista.forEach(p => {
                html += `<li class="list-group-item list-group-item-action" onclick="selecionarPaciente(${p.id}, '${p.nome.replace(/'/g, "\\'")}', '${p.sexo?.replace(/'/g, "\\'") || ''}', '${p.data_nascimento || ''}')">${p.nome} - ${p.email}</li>`;
            });
            html += '</ul>';
            $('#buscaPaciente').after(html);
        }

        function selecionarPaciente(id, nome, sexo, data_nascimento) {
            $('#paciente_id_selecionado').val(id);
            $('#pacienteSelecionado').text(nome);
            $('.list-group').remove();
            $('#buscaPaciente').val('');

            $.ajax({
                url: '?action=atualizar_sessao_paciente',
                method: 'POST',
                data: {
                    action: 'atualizar_sessao_paciente',
                    paciente_id: id,
                    nome: nome,
                    sexo: sexo,
                    data_nascimento: data_nascimento
                },
                success: function(response) {
                    console.log('Sessão atualizada:', response);
                    window.location.reload();
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao atualizar sessão:', status, error);
                }
            });
        }

        function carregarPagina(pagina) {
            if (pagina !== 'pacientes.php' && pagina !== 'usuarios.php') {
                const pacienteId = $('#paciente_id_selecionado').val();
                if (!pacienteId) {
                    alert('Selecione um paciente primeiro!');
                    return;
                }
                $('#conteudo-frame').attr('src', `${pagina}?paciente_id=${pacienteId}`);
            } else {
                $('#conteudo-frame').attr('src', pagina);
            }
        }
    </script>
</body>
</html>