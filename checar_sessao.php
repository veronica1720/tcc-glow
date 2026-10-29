<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');


if (isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id'    => $_SESSION['usuario_id'],
            'nome'  => $_SESSION['usuario_nome'],
            'email' => $_SESSION['usuario_email'],
            'tipo'  => $_SESSION['usuario_tipo']
        ]
    ]);
} else {
    echo json_encode(['authenticated' => false]);
}
?>