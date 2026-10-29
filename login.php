<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($data['email']) || empty($data['senha'])) {
        echo json_encode(['success' => false, 'message' => 'E-mail e senha são obrigatórios.']);
        exit;
    }
    
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT id_usuario, nome, email, senha, tipo_usuario FROM usuario WHERE email = :email");
        $stmt->execute([':email' => $data['email']]);
        
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            echo json_encode(['success' => false, 'message' => 'E-mail ou senha incorretos.']);
            exit;
        }
        
        if (!password_verify($data['senha'], $usuario['senha'])) {
            echo json_encode(['success' => false, 'message' => 'E-mail ou senha incorretos.']);
            exit;
        }
        
        $_SESSION['usuario_id']    = $usuario['id_usuario'];
        $_SESSION['usuario_nome']  = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_tipo']  = $usuario['tipo_usuario'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Login realizado com sucesso!',
            'user' => [
                'id'    => $usuario['id_usuario'],
                'nome'  => $usuario['nome'],
                'email' => $usuario['email'],
                'tipo'  => $usuario['tipo_usuario']
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Erro no login: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Não foi possível concluir a operação no banco de dados. Confira a estrutura da tabela usuario.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
}
?>