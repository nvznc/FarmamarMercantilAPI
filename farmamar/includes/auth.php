<?php
session_start();

function checkSession() {
    $timeout = 1800; // 30 minutos en segundos

    // Verifica si el usuario está logueado
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
        header("Location: ../login.php?error=not_logged_in");
        exit;
    }

    // Verifica timeout
    if (isset($_SESSION['last_activity'])) {
        $duration = time() - $_SESSION['last_activity'];
        if ($duration > $timeout) {
            session_unset();
            session_destroy();
            header("Location: ../login.php?error=expired");
            exit;
        }
    }
    
    $_SESSION['last_activity'] = time(); // Actualiza el timestamp
}