<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

$db = new Database();
$error = '';
$success = '';

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username']);
    $email = cleanInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor completa todos los campos';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del email es inválido';
    } else {
        // Intentar registrar (el rol por defecto será 'organizer')
        $registered = $db->registerAdmin($username, $password, $email, 'organizer');
        
        if ($registered) {
            $success = 'Registro exitoso. Ahora puedes iniciar sesión como organizador.';
        } else {
            $error = 'El nombre de usuario o email ya existe. Intenta con otro.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Organizador - Tickets</title>
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
    <div class="login-container rounded-lg p-8 w-full max-w-md my-8">
        <!-- Logo y Título -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-800 rounded-full mb-4">
                <i class="fas fa-user-plus text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Registro de Organizador</h1>
            <p class="text-gray-600 mt-2">Crea cuenta para gestionar tus eventos</p>
        </div>
        
        <!-- Formulario -->
        <form method="POST" action="">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">
                    <i class="fas fa-user mr-2"></i>Usuario
                </label>
                <input type="text" name="username" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800 transition"
                       placeholder="Ingresa tu usuario"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">
                    <i class="fas fa-envelope mr-2"></i>Email
                </label>
                <input type="email" name="email" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800 transition"
                       placeholder="Ingresa tu email"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">
                    <i class="fas fa-lock mr-2"></i>Contraseña
                </label>
                <input type="password" name="password" required minlength="6"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800 transition"
                       placeholder="Ingresa tu contraseña">
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 font-semibold mb-2">
                    <i class="fas fa-lock mr-2"></i>Confirmar Contraseña
                </label>
                <input type="password" name="confirm_password" required minlength="6"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800 transition"
                       placeholder="Confirma tu contraseña">
            </div>
            
            <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                <i class="fas fa-user-plus mr-2"></i>Registrarse
            </button>
        </form>
        
        <!-- Enlace a Login -->
        <div class="mt-8 text-center">
            <p class="text-sm text-gray-600 mb-4">¿Ya tienes una cuenta?</p>
            <a href="login.php" class="text-blue-600 font-semibold hover:text-blue-800 transition">
                <i class="fas fa-sign-in-alt mr-1"></i>Iniciar sesión aquí
            </a>
        </div>
        
        <!-- Footer -->
        <div class="mt-6 text-center">
            <a href="../" class="text-sm text-gray-600 hover:text-gray-800 transition">
                <i class="fas fa-arrow-left mr-1"></i>Volver al sitio
            </a>
        </div>
    </div>
</body>
</html>
