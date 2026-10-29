<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config/database.php';

function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return false;
    }
    
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos_obrigatorios = ['nome', 'email', 'senha', 'cpf', 'data_nascimento'];
    
    foreach ($campos_obrigatorios as $campo) {
        if (!isset($data[$campo]) || trim($data[$campo]) === '') {
            echo json_encode(['success' => false, 'message' => "O campo {$campo} é obrigatório."]);
            exit;
        }
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
        exit;
    }
    
    if (!validarCPF($data['cpf'])) {
        echo json_encode(['success' => false, 'message' => 'CPF inválido.']);
        exit;
    }
    
    if (strlen($data['senha']) < 6) {
        echo json_encode(['success' => false, 'message' => 'A senha deve ter no mínimo 6 caracteres.']);
        exit;
    }
    
    $cpf_limpo = preg_replace('/[^0-9]/', '', $data['cpf']);
    
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // 1. Verifica se o e-mail já existe
        $stmt = $conn->prepare("SELECT id_usuario FROM usuario WHERE email = :email");
        $stmt->execute([':email' => $data['email']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado.']);
            exit;
        }
        
        // 2. Verifica se o CPF já existe
        $stmt = $conn->prepare("SELECT id_usuario FROM usuario WHERE cpf = :cpf");
        $stmt->execute([':cpf' => $cpf_limpo]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Este CPF já está cadastrado.']);
            exit;
        }
        
        // Hash da senha
        $senha_hash = password_hash($data['senha'], PASSWORD_DEFAULT);
        
        // 3. Insere na nova tabela de usuario
        $sql = "INSERT INTO usuario (nome, email, senha, cpf, data_nascimento, tipo_usuario) 
                VALUES (:nome, :email, :senha, :cpf, :data_nascimento, 'cliente')";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':nome'            => $data['nome'],
            ':email'           => $data['email'],
            ':senha'           => $senha_hash,
            ':cpf'             => $cpf_limpo,
            ':data_nascimento' => $data['data_nascimento']
        ]);
        
        $usuario_id = $conn->lastInsertId();
        
        // Define as variáveis da sessão
        $_SESSION['usuario_id']    = $usuario_id;
        $_SESSION['usuario_nome']  = $data['nome'];
        $_SESSION['usuario_email'] = $data['email'];
        $_SESSION['usuario_tipo']  = 'cliente';
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cadastro realizado com sucesso!',
            'user' => [
                'id'    => $usuario_id,
                'nome'  => $data['nome'],
                'email' => $data['email'],
                'tipo'  => 'cliente'
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Erro no cadastro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Não foi possível concluir a operação no banco de dados. Confira a estrutura da tabela usuario.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
}
?>