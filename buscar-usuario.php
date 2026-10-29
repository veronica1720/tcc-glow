<?php
header('Content-Type: application/json');

require_once 'config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT id_usuario, nome, email, tipo_usuario, data_cadastro, cpf, cep, cidade, bairro, numero_casa, rua FROM usuario WHERE id_usuario = :id");
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        echo json_encode(['success' => true, 'user' => $usuario]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar usuário: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar dados do usuário.']);
}
?>