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
        $adminId = $db->registerAdmin($username, $password, $email, 'organizer');
        
        if ($adminId) {
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            if ($db->setAdminVerificationCode($adminId, $code)) {
                try {
                    if (sendVerificationCodeEmail($email, $code)) {
                        session_start();
                        $_SESSION['verify_admin_id'] = $adminId;
                        $_SESSION['verify_email'] = $email;
                        header('Location: verify-email.php');
                        exit();
                    } else {
                        $error = 'Usuario creado, pero no se pudo enviar el código de verificación.';
                    }
                } catch (Exception $e) {
                    $error = 'Error al enviar código: ' . $e->getMessage();
                }
            } else {
                $error = 'Error al generar el código de verificación.';
            }
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
    <title>Registro de Organizador - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #0A0E14;
            color: white;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .bg-accent {
            position: absolute;
            width: 40vw;
            height: 40vw;
            background: radial-gradient(circle, rgba(96, 165, 250, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .text-gradient {
            background: linear-gradient(to right, #DAFB71, #60A5FA);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        input {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }

        input:focus {
            border-color: rgba(96, 165, 250, 0.5) !important;
            box-shadow: 0 0 15px rgba(96, 165, 250, 0.1) !important;
        }
    </style>
</head>
<body>
    <div class="bg-accent top-[-10%] right-[-10%]"></div>
    <div class="bg-accent bottom-[-10%] left-[-10%]"></div>

    <div class="glass-card rounded-[2.5rem] p-10 w-full max-w-md my-12 relative z-10 animate-fade-in">
        <!-- Logo y Título -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-500 rounded-3xl mb-6 shadow-lg shadow-blue-500/20">
                <i class="fas fa-user-plus text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter">Únete como <span class="text-gradient">Organizador</span></h1>
            <p class="text-gray-500 mt-2 font-medium uppercase tracking-widest text-[10px]">Gestión Premium de Eventos</p>
        </div>
        
        <!-- Formulario -->
        <form method="POST" action="" class="space-y-5">
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Usuario</label>
                <div class="relative group">
                    <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                    <input type="text" name="username" required
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-blue-400/50 transition-all placeholder:text-gray-600"
                           placeholder="Tu nombre de usuario"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Email</label>
                <div class="relative group">
                    <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                    <input type="email" name="email" required
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-blue-400/50 transition-all placeholder:text-gray-600"
                           placeholder="tu@email.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>
            
            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Contraseña</label>
                <div class="relative group">
                    <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                    <input type="password" name="password" required minlength="6"
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-blue-400/50 transition-all placeholder:text-gray-600"
                           placeholder="Mínimo 6 caracteres">
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Confirmar Contraseña</label>
                <div class="relative group">
                    <i class="fas fa-shield-alt absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                    <input type="password" name="confirm_password" required minlength="6"
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-blue-400/50 transition-all placeholder:text-gray-600"
                           placeholder="Repite tu contraseña">
                </div>
            </div>
            
            <button type="submit" class="w-full bg-blue-500 text-white py-4 rounded-2xl font-black text-lg hover:shadow-[0_0_30px_rgba(59,130,246,0.3)] transition-all flex items-center justify-center gap-2 group mt-6">
                Crear Cuenta
                <i class="fas fa-check-circle group-hover:scale-110 transition-transform"></i>
            </button>
        </form>
        
        <!-- Enlace a Login -->
        <div class="mt-10 text-center border-t border-white/5 pt-8">
            <p class="text-xs text-gray-500 mb-4">¿Ya tienes una cuenta activa?</p>
            <a href="login.php" class="inline-flex items-center gap-2 px-6 py-2 rounded-full bg-white/5 border border-white/10 text-xs font-bold text-white hover:bg-white/10 transition">
                <i class="fas fa-sign-in-alt text-[10px]"></i>
                Iniciar sesión aquí
            </a>
        </div>
        
        <div class="mt-8 text-center">
            <a href="../" class="inline-flex items-center gap-2 text-gray-500 hover:text-white transition text-xs font-bold uppercase tracking-widest">
                <i class="fas fa-arrow-left text-[10px]"></i>
                Volver al sitio
            </a>
        </div>
    </div>

    <script>
        // Simple entry animation
        document.querySelector('.animate-fade-in').style.opacity = '0';
        document.querySelector('.animate-fade-in').style.transform = 'translateY(20px)';
        
        window.addEventListener('load', () => {
            const el = document.querySelector('.animate-fade-in');
            el.style.transition = 'all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        });
    </script>
</body>
</html>
