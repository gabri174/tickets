<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

$db = new Database();
$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor completa todos los campos';
    } else {
        $admin = $db->validateAdmin($username, $password);
        
        if ($admin) {
            session_start();
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Tickets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-gray-light: #d9d9d9;
            --color-gray-dark: #363c40;
            --color-gray-medium: #babebf;
            --color-gray-muted: #848b8c;
            --color-black: #202426;
        }
        
        body { 
            background-color: var(--color-gray-light);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .btn-primary { 
            background-color: var(--color-gray-dark); 
            transition: background-color 0.3s ease;
        }
        
        .btn-primary:hover { 
            background-color: var(--color-black); 
        }
    </style>
</head>
<body>
    <div class="login-container rounded-lg p-8 w-full max-w-md">
        <!-- Logo y Título -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-800 rounded-full mb-4">
                <i class="fas fa-user-shield text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Panel de Administración</h1>
            <p class="text-gray-600 mt-2">Tickets System</p>
        </div>
        
        <!-- Formulario de Login -->
        <form method="POST" action="">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mb-6">
                <label class="block text-gray-700 font-semibold mb-2">
                    <i class="fas fa-user mr-2"></i>Usuario
                </label>
                <input type="text" name="username" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800 transition"
                       placeholder="Ingresa tu usuario"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 font-semibold mb-2">
                    <i class="fas fa-lock mr-2"></i>Contraseña
                </label>
                <input type="password" name="password" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800 transition"
                       placeholder="Ingresa tu contraseña">
            </div>
            
            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" class="mr-2">
                    <span class="text-sm text-gray-600">Recordar sesión</span>
                </label>
            </div>
            
            <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
            </button>
        </form>
        
        <!-- Enlace a Registro -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 mb-2">¿Eres organizador y no tienes cuenta?</p>
            <a href="register.php" class="text-blue-600 font-semibold hover:text-blue-800 transition">
                <i class="fas fa-user-plus mr-1"></i>Regístrate aquí
            </a>
        </div>
        
        <!-- Información de ayuda -->
        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
            <h4 class="font-semibold text-gray-700 mb-2">¿Necesitas ayuda?</h4>
            <p class="text-sm text-gray-600 mb-2">
                Contacta al administrador del sistema para obtener acceso.
            </p>
            <p class="text-xs text-gray-500">
                Usuario por defecto: <code>admin</code><br>
                Contraseña por defecto: <code>admin123</code>
            </p>
        </div>
        
        <div class="mt-6 text-center">
            <a href="../" class="text-sm text-gray-600 hover:text-gray-800 transition">
                <i class="fas fa-arrow-left mr-1"></i>Volver al sitio
            </a>
        </div>
    </div>
    
    <script>
        // Enfocar en el primer campo
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.querySelector('input[name="username"]');
            if (usernameField && !usernameField.value) {
                usernameField.focus();
            } else {
                const passwordField = document.querySelector('input[name="password"]');
                if (passwordField) {
                    passwordField.focus();
                }
            }
        });
        
        // Prevenir envío múltiple
        let formSubmitted = false;
        document.querySelector('form').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
            
            // Deshabilitar botón
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Iniciando sesión...';
        });
    </script>
</body>
</html>
