<?php
$host = "localhost";
$user = "atlasori_saintdb";
$pass = "Ay3y39y6!";
$db   = "atlasori_farmamardb";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}