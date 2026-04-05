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
    // CSRF Check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad (CSRF). Por favor, intenta de nuevo.';
    } else {
        $login = cleanInput($_POST['username']); // Puede ser username o email
        $password = $_POST['password'];
        
        if (empty($login) || empty($password)) {
            $error = 'Por favor completa todos los campos';
        } else {
            // Check rate limiting
            $attempts = $db->getLoginAttempts($login);
            if ($attempts && $attempts['login_attempts'] >= 5) {
                $lastAttempt = strtotime($attempts['last_login_attempt']);
                $lockoutTime = 15 * 60; // 15 minutes
                if (time() - $lastAttempt < $lockoutTime) {
                    $remaining = ceil(($lockoutTime - (time() - $lastAttempt)) / 60);
                    $error = "Demasiados intentos fallidos. Por seguridad, tu cuenta ha sido bloqueada temporalmente. Intenta de nuevo en $remaining minutos.";
                } else {
                    // Lockout period passed, reset attempts
                    $db->resetLoginAttempts($login);
                    $attempts['login_attempts'] = 0;
                }
            }

            if (empty($error)) {
                $admin = $db->validateAdmin($login, $password);
                
                if ($admin) {
                    // Reset attempts on successful login
                    $db->resetLoginAttempts($login);
                    
                    if (!$admin['is_verified']) {
                        $_SESSION['verify_admin_id'] = $admin['id'];
                        $_SESSION['verify_email'] = $admin['email'];
                        header('Location: verify-email.php');
                        exit();
                    }
                    
                    // Login exitoso
                    session_regenerate_id(true);
                    
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_photo'] = $admin['profile_photo'];
                    
                    header('Location: index.php');
                    exit();
                } else {
                    // Increment attempts on failed login
                    $db->incrementLoginAttempts($login);
                    $error = 'Usuario o contraseña incorrectos';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo SITE_NAME; ?></title>
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
            overflow: hidden;
            position: relative;
        }

        .bg-accent {
            position: absolute;
            width: 40vw;
            height: 40vw;
            background: radial-gradient(circle, rgba(218, 251, 113, 0.1) 0%, transparent 70%);
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
            border-color: rgba(218, 251, 113, 0.5) !important;
            box-shadow: 0 0 15px rgba(218, 251, 113, 0.1) !important;
        }
    </style>
</head>
<body>
    <div class="bg-accent top-[-10%] left-[-10%]"></div>
    <div class="bg-accent bottom-[-10%] right-[-10%]"></div>

    <div class="glass-card rounded-[2.5rem] p-10 w-full max-w-md relative z-10 animate-fade-in">
        <!-- Logo y Título -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-lime-400 rounded-3xl mb-6 shadow-lg shadow-lime-400/20">
                <i class="fas fa-ticket-alt text-black text-3xl"></i>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter">Panel <span class="text-gradient">Admin</span></h1>
            <p class="text-gray-500 mt-2 font-medium uppercase tracking-widest text-[10px]">Acceso Restringido</p>
        </div>
        
        <!-- Formulario de Login -->
        <form method="POST" action="" class="space-y-6">
            <?php echo csrf_field(); ?>
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">
                    Usuario
                </label>
                <div class="relative group">
                    <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-lime-400 transition-colors"></i>
                    <input type="text" name="username" required
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-600"
                           placeholder="Usuario o Email"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
            </div>
            
            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">
                    Contraseña
                </label>
                <div class="relative group">
                    <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-lime-400 transition-colors"></i>
                    <input type="password" name="password" required
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-600"
                           placeholder="••••••••">
                </div>
            </div>
            
            <div class="flex items-center justify-between px-1">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" class="w-4 h-4 rounded border-gray-700 bg-gray-800 text-lime-400 focus:ring-lime-400/20">
                    <span class="text-xs text-gray-500 group-hover:text-gray-400 transition">Recordar</span>
                </label>
                <a href="forgot-password.php" class="text-xs text-lime-400/70 hover:text-lime-400 transition">¿Olvidaste tu clave?</a>
            </div>
            
            <button type="submit" class="w-full bg-lime-400 text-black py-4 rounded-2xl font-black text-lg hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all flex items-center justify-center gap-2 group mt-4">
                Entrar
                <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
            </button>
        </form>
        
        <!-- Enlace a Registro -->
        <div class="mt-10 text-center border-t border-white/5 pt-8">
            <p class="text-xs text-gray-500 mb-4">¿Deseas organizar un evento?</p>
            <a href="register.php" class="inline-flex items-center gap-2 px-6 py-2 rounded-full bg-white/5 border border-white/10 text-xs font-bold text-white hover:bg-white/10 transition">
                <i class="fas fa-user-plus text-[10px]"></i>
                Crear cuenta de Organizador
            </a>
        </div>
        
        <div class="mt-8 text-center pt-2">
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

        // Form handling
        let formSubmitted = false;
        document.querySelector('form').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
            
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verificando...';
        });
    </script>
</body>
</html>
