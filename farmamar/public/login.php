<?php
// 1. Mostrar errores para saber qué falla exactamente
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_input = $_POST['usuario'] ?? '';
    $clave_input   = $_POST['clave'] ?? '';

    try {
        // Quitamos el WHERE ACTIVO pero mantenemos el CodUsua para identificar al usuario
        $sql = "SELECT CodUsua, Descrip, 
                RTRIM(LTRIM(CONCAT(
                    SUBSTRING(SDATA3, 175, 1), SUBSTRING(SDATA1, 33, 1), 
                    SUBSTRING(SDATA2, 90, 1), SUBSTRING(SDATA3, 14, 1), 
                    SUBSTRING(SDATA1, 207, 1), SUBSTRING(SDATA3, 111, 1), 
                    SUBSTRING(SDATA3, 145, 1), SUBSTRING(SDATA2, 180, 1), 
                    SUBSTRING(SDATA2, 9, 1), SUBSTRING(SDATA3, 53, 1)
                ))) AS CLAVE,
                IFNULL(CodVend, '') as CodVend 
                FROM SSUSRS 
                WHERE CodUsua = :usuario";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['usuario' => $usuario_input]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug: Descomenta la siguiente línea si quieres ver qué clave está generando el SQL
        // die("Clave en DB: " . $user['CLAVE'] . " | Ingresada: " . $clave_input);

        if ($user && trim($user['CLAVE']) === trim($clave_input)) {
            $_SESSION['user_id'] = $user['CodUsua'];
            $_SESSION['user_name'] = $user['Descrip'];
            $_SESSION['last_activity'] = time();
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } catch (PDOException $e) {
        $error = "Error en la consulta: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Login Farmamar</title></head>
<body>
    <h2>Iniciar Sesión</h2>
    <form method="POST">
        <input type="text" name="usuario" placeholder="Código de Usuario" required>
        <input type="password" name="clave" placeholder="Contraseña" required>
        <button type="submit">Entrar</button>
    </form>
    <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
</body>
</html>