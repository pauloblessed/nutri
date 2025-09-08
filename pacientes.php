<?php
session_start();

date_default_timezone_set($_SESSION['user']['timezone'] ?? 'America/Sao_Paulo');

if (!isset($_SESSION['user'])) {
    echo "<p class='alert alert-danger'>Usuário não autenticado. Faça login.</p>";
    exit;
}

$user_id = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
if (!$user_id || $user_id <= 0) {
    echo "<p class='alert alert-danger'>ID de usuário inválido.</p>";
    exit;
}

$SUPABASE_URL = 'https://vjpxlkyfjtrhdsyrxsko.supabase.co/rest/v1/pacientes';
$SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZqcHhsa3lmanRyaGRzeXJ4c2tvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDQ4OTUwMDEsImV4cCI6MjA2MDQ3MTAwMX0.HkMPdlcpT75u75z6j5BbBwYW8QLzQGKBJxyHb-St7k0';

$msg = '';

function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += $cpf[$i] * (($t + 1) - $i);
        }
        $soma = ((10 * $soma) % 11) % 10;
        if ($cpf[$t] != $soma) return false;
    }
    return true;
}

function validarTelefone($tel) {
    if (empty($tel)) return true;
    $tel = preg_replace('/[^\d]/', '', $tel);
    return strlen($tel) === 10 || strlen($tel) === 11;
}

function formatarData($data) {
    if (empty($data)) return '-';
    return date('d-m-Y', strtotime($data));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['editar']) || isset($_POST['inserir']))) {
    $paciente_id = isset($_POST['paciente_id']) && $_POST['paciente_id'] !== '' ? (int)$_POST['paciente_id'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : null;
    $cpf = isset($_POST['cpf']) ? preg_replace('/\D/', '', trim($_POST['cpf'])) : null;
    $email = isset($_POST['email']) ? trim($_POST['email']) : null;
    $tel = isset($_POST['tel']) ? preg_replace('/\D/', '', trim($_POST['tel'])) : null;
    $sexo = isset($_POST['sexo']) ? trim($_POST['sexo']) : null;
    $data_nascimento = isset($_POST['data_nascimento']) ? trim($_POST['data_nascimento']) : null;
    $cidade_nascimento = isset($_POST['cidade_nascimento']) ? trim($_POST['cidade_nascimento']) : null;
    $objetivo_primario = isset($_POST['objetivo_primario']) ? trim($_POST['objetivo_primario']) : null;
    $objetivo_secundario = isset($_POST['objetivo_secundario']) ? trim($_POST['objetivo_secundario']) : null;
    $obs = isset($_POST['obs']) ? trim($_POST['obs']) : null;

    if (!$nome) {
        $msg = "<p class='alert alert-danger'>Nome é obrigatório.</p>";
    } elseif (!empty($cpf) && !validarCPF($cpf)) {
        $msg = "<p class='alert alert-danger'>CPF inválido.</p>";
    } elseif (!validarTelefone($tel)) {
        $msg = "<p class='alert alert-danger'>Telefone inválido. Use 10 dígitos (fixo) ou 11 dígitos (celular).</p>";
    } else {
        $dados = array_filter([
            'user_id' => $user_id,
            'nome' => $nome,
            'cpf' => $cpf,
            'email' => $email,
            'tel' => $tel,
            'sexo' => $sexo,
            'data_nascimento' => $data_nascimento,
            'cidade_nascimento' => $cidade_nascimento,
            'objetivo_primario' => $objetivo_primario,
            'objetivo_secundario' => $objetivo_secundario,
            'obs' => $obs,
            'cadastro_em' => date('c')
        ], function($value) { return !is_null($value); });

        if ($paciente_id && $paciente_id > 0) {
            $url = "$SUPABASE_URL?id=eq.$paciente_id&user_id=eq.$user_id";
            $options = [
                'http' => [
                    'header'  => "Content-type: application/json\r\napikey: $SUPABASE_KEY\r\nAuthorization: Bearer $SUPABASE_KEY\r\nPrefer: return=minimal",
                    'method'  => 'PATCH',
                    'content' => json_encode($dados),
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $http_response_header = $http_response_header ?? [];

            if (strpos($http_response_header[0] ?? '', '200') !== false || strpos($http_response_header[0] ?? '', '204') !== false) {
                $msg = "<p class='alert alert-success'>Paciente editado com sucesso!</p>";
            } else {
                $msg = "<p class='alert alert-danger'>Erro ao editar paciente: " . htmlspecialchars($result ?: 'Sem detalhes') . "</p>";
            }
        } else {
            if ($email) {
                $url = "$SUPABASE_URL?email=eq." . urlencode($email) . "&user_id=eq.$user_id";
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

                $pacientes = json_decode($resposta, true) ?? [];
                if ($http_code === 200 && count($pacientes) > 0) {
                    $msg = "<p class='alert alert-danger'>Este email já está cadastrado para este nutricionista.</p>";
                }
            }

            if (!isset($msg)) {
                $url = $SUPABASE_URL;
                $options = [
                    'http' => [
                        'header'  => "Content-type: application/json\r\napikey: $SUPABASE_KEY\r\nAuthorization: Bearer $SUPABASE_KEY\r\nPrefer: return=minimal",
                        'method'  => 'POST',
                        'content' => json_encode($dados),
                        'ignore_errors' => true
                    ]
                ];
                $context = stream_context_create($options);
                $result = file_get_contents($url, false, $context);
                $http_response_header = $http_response_header ?? [];

                if (strpos($http_response_header[0] ?? '', '201') !== false) {
                    $msg = "<p class='alert alert-success'>Paciente inserido com sucesso!</p>";
                } else {
                    $msg = "<p class='alert alert-danger'>Erro ao inserir paciente: " . htmlspecialchars($result ?: 'Sem detalhes') . "</p>";
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deletar'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    if (!$id || $id <= 0) {
        $msg = "<p class='alert alert-danger'>ID de paciente inválido.</p>";
    } else {
        $deleteUrl = "$SUPABASE_URL?id=eq.$id&user_id=eq.$user_id";
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\napikey: $SUPABASE_KEY\r\nAuthorization: Bearer $SUPABASE_KEY\r\nPrefer: return=minimal",
                'method'  => 'DELETE',
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($options);
        $result = file_get_contents($deleteUrl, false, $context);
        $http_response_header = $http_response_header ?? [];

        if (strpos($http_response_header[0] ?? '', '204') !== false || strpos($http_response_header[0] ?? '', '200') !== false) {
            $msg = "<p class='alert alert-success'>Paciente excluído com sucesso!</p>";
        } else {
            $msg = "<p class='alert alert-danger'>Erro ao excluir paciente: " . htmlspecialchars($result ?: 'Sem detalhes') . "</p>";
        }
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($limit, [10, 25, 50]) ? $limit : 10;
$offset = ($page - 1) * $limit;
$total_pacientes = 0;

$url = "$SUPABASE_URL?user_id=eq.$user_id&order=cadastro_em.desc";
if ($search) {
    $search_encoded = urlencode("%$search%");
    $url .= "&or=(nome.ilike.$search_encoded,cpf.ilike.$search_encoded,email.ilike.$search_encoded,tel.ilike.$search_encoded)";
}
$url .= "&limit=$limit&offset=$offset";

$count_url = "$SUPABASE_URL?user_id=eq.$user_id&select=count";
if ($search) {
    $count_url .= "&or=(nome.ilike.$search_encoded,cpf.ilike.$search_encoded,email.ilike.$search_encoded,tel.ilike.$search_encoded)";
}
$ch = curl_init($count_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $SUPABASE_KEY",
    "Authorization: Bearer $SUPABASE_KEY",
    "Content-Type: application/json",
    "Range-Unit: items",
    "Range: 0-0"
]);
$count_response = curl_exec($ch);
$http_code_count = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($http_code_count === 200) {
    $count_data = json_decode($count_response, true);
    $total_pacientes = $count_data[0]['count'] ?? 0;
}
curl_close($ch);

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

$pacientes = json_decode($resposta, true) ?? [];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pacientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        .required::after {
            content: " *";
            color: red;
        }
        .invalid-feedback {
            display: none;
        }
        .is-invalid ~ .invalid-feedback {
            display: block;
        }
        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        .search-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        .spinner {
            display: none;
            position: absolute;
            right: 10px;
            width: 20px;
            height: 20px;
            border: 2px solid #ccc;
            border-top-color: #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .modal-content {
            border-radius: 8px;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .modal-body {
            max-height: 600px;
            overflow-y: auto;
        }
        .detail-label {
            font-weight: bold;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Gerenciar Pacientes</h2>
        <?php if (isset($msg)): ?>
            <?= $msg ?>
        <?php endif; ?>
        <?php if ($http_code !== 200): ?>
            <p class="alert alert-danger">Erro ao conectar com o banco de dados (código: <?= $http_code ?>).</p>
        <?php else: ?>
            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#pacienteModal" onclick="abrirModalNovo()">Novo</button>
            <?php if (count($pacientes) > 0): ?>
                <h3 class="mt-4">Lista de Pacientes</h3>
                <div class="mb-3 d-flex gap-3 search-container">
                    <input type="text" class="form-control" id="searchInput" placeholder="Buscar por Nome, CPF, Email ou Telefone" value="<?= htmlspecialchars(urldecode($search ?? '')) ?>">
                    <div class="spinner" id="searchSpinner"></div>
                    <select id="limitSelect" class="form-select" style="width: auto;">
                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10 por página</option>
                        <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25 por página</option>
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 por página</option>
                    </select>
                </div>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Detalhes</th>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaPacientes">
                        <?php foreach ($pacientes as $paciente): ?>
                            <?php $paciente_sanitizado = array_map('htmlspecialchars', $paciente); ?>
                            <tr data-id="<?= $paciente['id'] ?>">
                                <td>
                                    <button class="btn btn-sm btn-info btn-detalhes-paciente" 
                                            data-paciente='<?= json_encode($paciente_sanitizado) ?>'>Detalhes</button>
                                    <button class="btn btn-sm btn-primary btn-consulta-paciente">Consulta</button>
                                </td>
                                <td><?= htmlspecialchars($paciente['nome'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($paciente['cpf'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($paciente['email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($paciente['tel'] ?? '-') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning btn-editar-paciente" 
                                            data-paciente='<?= json_encode($paciente_sanitizado) ?>'>Editar</button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $paciente['id'] ?>">
                                        <button type="submit" name="deletar" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Tem certeza que deseja excluir este paciente?')">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="pagination-controls">
                    <button class="btn btn-secondary" id="prevPage" <?= $page <= 1 ? 'disabled' : '' ?>>Anterior</button>
                    <span>Página <?= $page ?> de <?= ceil($total_pacientes / $limit) ?></span>
                    <button class="btn btn-secondary" id="nextPage" <?= $page >= ceil($total_pacientes / $limit) ? 'disabled' : '' ?>>Próximo</button>
                </div>
            <?php else: ?>
                <p class="mt-4 text-muted" id="tabelaPacientes">Nenhum paciente registrado.</p>
            <?php endif; ?>

            <div class="modal fade" id="pacienteModal" tabindex="-1" aria-labelledby="pacienteModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="pacienteModalLabel">Detalhes do Paciente</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="modalBody"></div>
                        <div class="modal-footer" id="modalFooter"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validarCPF(cpf) {
            cpf = cpf.replace(/[^\d]/g, '');
            if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
            let soma = 0, resto;
            for (let i = 1; i <= 9; i++) soma += parseInt(cpf[i-1]) * (11 - i);
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            if (resto !== parseInt(cpf[9])) return false;
            soma = 0;
            for (let i = 1; i <= 10; i++) soma += parseInt(cpf[i-1]) * (12 - i);
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            return resto === parseInt(cpf[10]);
        }

        function validarTelefone(tel) {
            if (!tel) return true;
            tel = tel.replace(/[^\d]/g, '');
            return tel.length === 10 || tel.length === 11;
        }

        function formatarData(data) {
            if (!data) return '-';
            const [ano, mes, dia] = data.split('-');
            return `${dia}-${mes}-${ano}`;
        }

        function abrirModalDetalhes(paciente) {
            $('#pacienteModalLabel').text('Detalhes do Paciente');
            $('#modalBody').html(`
                <div class="row">
                    <div class="col-md-6 mb-2"><span class="detail-label">Nome:</span> ${paciente.nome || '-'}</div>
                    <div class="col-md-6 mb-2"><span class="detail-label">CPF:</span> ${paciente.cpf || '-'}</div>
                    <div class="col-md-6 mb-2"><span class="detail-label">E-mail:</span> ${paciente.email || '-'}</div>
                    <div class="col-md-6 mb-2"><span class="detail-label">Telefone:</span> ${paciente.tel || '-'}</div>
                    <div class="col-md-6 mb-2"><span class="detail-label">Sexo:</span> ${paciente.sexo || '-'}</div>
                    <div class="col-md-6 mb-2"><span class="detail-label">Data de Nascimento:</span> ${formatarData(paciente.data_nascimento) || '-'}</div>
                    <div class="col-md-6 mb-2"><span class="detail-label">Cidade de Nascimento:</span> ${paciente.cidade_nascimento || '-'}</div>
                    <div class="col-md-6 mb-2"><span class="detail-label">Objetivo Primário:</span> ${paciente.objetivo_primario || '-'}</div>
                    <div class="col-md-6 mb-2"><span class="detail-label">Objetivo Secundário:</span> ${paciente.objetivo_secundario || '-'}</div>
                    <div class="col-md-12 mb-2"><span class="detail-label">Observações:</span> ${paciente.obs || '-'}</div>
                    <div class="col-md-12 mb-2"><span class="detail-label">Data de Cadastro:</span> ${paciente.cadastro_em ? new Date(paciente.cadastro_em).toLocaleString('pt-BR') : '-'}</div>
                </div>
            `);
            $('#modalFooter').html(`
                <button type="button" class="btn btn-primary btn-consulta-paciente">Consulta</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            `);
            $('#pacienteModal').modal('show');
        }

        function abrirModalFormulario(paciente = {}, isEditar = false) {
            $('#pacienteModalLabel').text(isEditar ? 'Editar Paciente' : 'Novo Paciente');
            $('#modalBody').html(`
                <form id="formPacientes" method="POST" action="pacientes.php">
                    <input type="hidden" name="paciente_id" id="paciente_id" value="${paciente.id || ''}">
                    <p class="mb-3">Campos obrigatórios <span style="color: red;">*</span></p>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome" class="form-label required">Nome:</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="${paciente.nome || ''}" required>
                        </div>
                        <div class="form-group">
                            <label for="cpf" class="form-label">CPF:</label>
                            <input type="text" class="form-control" id="cpf" name="cpf" value="${paciente.cpf || ''}" placeholder="Ex.: 123.456.789-00">
                            <div class="invalid-feedback">CPF inválido.</div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email" class="form-label">E-mail:</label>
                            <input type="email" class="form-control" id="email" name="email" value="${paciente.email || ''}">
                            <div class="invalid-feedback">Este email já está cadastrado.</div>
                        </div>
                        <div class="form-group">
                            <label for="tel" class="form-label">Telefone:</label>
                            <input type="text" class="form-control" id="tel" name="tel" value="${paciente.tel || ''}" placeholder="Ex.: (11) 91234-5678">
                            <div class="invalid-feedback">Telefone inválido. Use 10 dígitos (fixo) ou 11 dígitos (celular).</div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sexo" class="form-label">Sexo:</label>
                            <select class="form-control" id="sexo" name="sexo">
                                <option value="">Selecione</option>
                                <option value="Masculino" ${paciente.sexo === 'Masculino' ? 'selected' : ''}>Masculino</option>
                                <option value="Feminino" ${paciente.sexo === 'Feminino' ? 'selected' : ''}>Feminino</option>
                                <option value="Outro" ${paciente.sexo === 'Outro' ? 'selected' : ''}>Outro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="data_nascimento" class="form-label">Data de Nascimento:</label>
                            <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" value="${paciente.data_nascimento || ''}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cidade_nascimento" class="form-label">Cidade de Nascimento:</label>
                            <input type="text" class="form-control" id="cidade_nascimento" name="cidade_nascimento" value="${paciente.cidade_nascimento || ''}">
                        </div>
                        <div class="form-group">
                            <label for="objetivo_primario" class="form-label">Objetivo Primário:</label>
                            <input type="text" class="form-control" id="objetivo_primario" name="objetivo_primario" value="${paciente.objetivo_primario || ''}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="objetivo_secundario" class="form-label">Objetivo Secundário:</label>
                            <input type="text" class="form-control" id="objetivo_secundario" name="objetivo_secundario" value="${paciente.objetivo_secundario || ''}">
                        </div>
                        <div class="form-group">
                            <label for="obs" class="form-label">Observações:</label>
                            <textarea class="form-control" id="obs" name="obs" rows="2">${paciente.obs || ''}</textarea>
                        </div>
                    </div>
                    <input type="hidden" name="${isEditar ? 'editar' : 'inserir'}" value="1">
                </form>
            `);
            $('#modalFooter').html(`
                <button type="submit" form="formPacientes" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-primary btn-consulta-paciente">Consulta</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            `);

            $('#cpf').mask('000.000.000-00');
            $('#tel').mask('(00) 0000-0000', {
                onKeyPress: function(val, e, field, options) {
                    var digits = val.replace(/\D/g, '').length;
                    field.mask(digits > 10 ? '(00) 00000-0000' : '(00) 0000-0000', options);
                }
            });

            $('#cpf').on('blur', function() {
                const cpf = this.value;
                if (cpf && !validarCPF(cpf)) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });

            $('#tel').on('blur', function() {
                const tel = this.value;
                if (tel && !validarTelefone(tel)) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });

            $('#email').on('blur', async function() {
                const email = this.value;
                if (!email) return;
                
                const pacienteId = $('#paciente_id').val();
                if (pacienteId) return;
                
                try {
                    const response = await fetch(`<?=$SUPABASE_URL?>?email=eq.${encodeURIComponent(email)}&user_id=eq.<?=$user_id?>`, {
                        headers: {
                            'apikey': '<?=$SUPABASE_KEY?>',
                            'Authorization': 'Bearer <?=$SUPABASE_KEY?>',
                            'Content-Type': 'application/json'
                        }
                    });
                    
                    if (!response.ok) throw new Error('Erro na verificação');
                    
                    const pacientes = await response.json();
                    if (pacientes.length > 0) {
                        this.classList.add('is-invalid');
                        $(this).next('.invalid-feedback').text('Este email já está cadastrado.');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                } catch (error) {
                    console.error('Erro ao verificar email:', error);
                }
            });

            $('#formPacientes').on('submit', function(e) {
                const cpf = $('#cpf').val();
                const tel = $('#tel').val();
                const nome = $('#nome').val();
                
                if (!nome) {
                    $('#nome').focus();
                    e.preventDefault();
                    return false;
                }
                
                if (cpf && !validarCPF(cpf)) {
                    $('#cpf').addClass('is-invalid').focus();
                    e.preventDefault();
                    return false;
                }
                
                if (tel && !validarTelefone(tel)) {
                    $('#tel').addClass('is-invalid').focus();
                    e.preventDefault();
                    return false;
                }
                
                if ($('#email').hasClass('is-invalid')) {
                    $('#email').focus();
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });

            $('#pacienteModal').modal('show');
        }

        function abrirModalNovo() {
            abrirModalFormulario({}, false);
        }

        $(document).on('click', '.btn-detalhes-paciente', function() {
            var paciente = JSON.parse($(this).attr('data-paciente'));
            abrirModalDetalhes(paciente);
        });

        $(document).on('click', '.btn-editar-paciente', function() {
            var paciente = JSON.parse($(this).attr('data-paciente'));
            abrirModalFormulario(paciente, true);
        });

        let searchTimeout;
        $('#searchInput').on('input', function() {
            clearTimeout(searchTimeout);
            $('#searchSpinner').show();
            searchTimeout = setTimeout(() => {
                updateSearch();
            }, 500);
        });

        $('#limitSelect').on('change', function() {
            $('#searchSpinner').show();
            updateSearch();
        });

        $('#prevPage').on('click', function() {
            const page = parseInt('<?= $page ?>') - 1;
            if (page >= 1) {
                $('#searchSpinner').show();
                updateSearch(page);
            }
        });

        $('#nextPage').on('click', function() {
            const page = parseInt('<?= $page ?>') + 1;
            const totalPages = Math.ceil(<?= $total_pacientes ?> / parseInt('<?= $limit ?>'));
            if (page <= totalPages) {
                $('#searchSpinner').show();
                updateSearch(page);
            }
        });

        function updateSearch(page = 1) {
            const search = $('#searchInput').val().trim();
            const limit = $('#limitSelect').val();
            const url = new URL(window.location.href);
            url.searchParams.set('search', search);
            url.searchParams.set('limit', limit);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }

        $(window).on('load', function() {
            $('#searchSpinner').hide();
        });
    </script>
</body>
</html>