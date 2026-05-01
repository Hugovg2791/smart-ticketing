<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

/**
 * Script para inicializar un usuario administrador.
 * ADVERTENCIA: Este archivo debe eliminarse o protegerse en producción.
 * Solo puede ejecutarse desde consola (CLI). 
 */

if (php_sapi_name() !== 'cli') {
    die("Error: Este script solo puede ser ejecutado desde la línea de comandos (CLI).\n");
}

echo "Inicializando sistema de autenticación...\n";

// Datos del administrador por defecto
$adminName = 'Administrador Principal';
$adminEmail = 'admin@electrotech.com';
$adminPassword = 'password123'; // Cambiar en producción
$role = 'admin';

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar si el usuario ya existe
    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $adminEmail]);
    if ($stmt->fetch()) {
        echo "El usuario administrador ya existe en la base de datos con el email $adminEmail.\n";
        exit;
    }

    // Insertar el usuario con password cifrado
    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    $stmtInsert = $db->prepare('
        INSERT INTO users (name, email, password_hash, role) 
        VALUES (:name, :email, :password_hash, :role)
    ');
    
    $success = $stmtInsert->execute([
        'name' => $adminName,
        'email' => $adminEmail,
        'password_hash' => $passwordHash,
        'role' => $role
    ]);

    if ($success) {
        echo "Usuario administrador creado exitosamente:\n";
        echo " - Email: $adminEmail\n";
        echo " - Contraseña: $adminPassword\n";
        echo "Por favor, elimina este script después de su uso.\n";
    } else {
        echo "Hubo un problema intentando crear el administrador.\n";
    }

} catch (Exception $e) {
    echo "Error al conectar o insertar en la BBDD: " . $e->getMessage() . "\n";
}
