<?php
header('Content-Type: application/json');

require_once 'config/database.php';

if (isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['usuario_id'],
            'nome' => $_SESSION['usuario_nome'],
            'email' => $_SESSION['usuario_email'],
            'tipo' => $_SESSION['usuario_tipo']
        ]
    ]);
} else {
    echo json_encode(['authenticated' => false]);
}
?>