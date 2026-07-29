<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

// Nunca mostrar errores en login público
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$db = new Database();
$error = '';
$loginValue = '';

if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Rate limit adicional por sesión/IP a nivel aplicación
if (!function_exists('clientFingerprint')) {
    function clientFingerprint(): string {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ip . '|' . $ua);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, intenta de nuevo.';
    } else {
        $login = cleanInput($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $loginValue = $login;

        if ($login === '' || $password === '') {
            $error = 'Por favor completa todos los campos.';
        } else {
            $fingerprint = clientFingerprint();

            if (function_exists('checkRateLimit') && !checkRateLimit('admin_login_' . $fingerprint, 12, 300)) {
                $error = 'Demasiados intentos. Espera unos minutos antes de volver a intentarlo.';
            }

            if ($error === '') {
                $attempts = $db->getLoginAttempts($login);

                if ($attempts && (int) ($attempts['login_attempts'] ?? 0) >= 5) {
                    $lastAttempt = strtotime($attempts['last_login_attempt'] ?? '');
                    $lockoutTime = 15 * 60;

                    if ($lastAttempt && (time() - $lastAttempt) < $lockoutTime) {
                        $remaining = (int) ceil(($lockoutTime - (time() - $lastAttempt)) / 60);
                        $error = "Demasiados intentos fallidos. Intenta de nuevo en $remaining minutos.";
                    } else {
                        $db->resetLoginAttempts($login);
                    }
                }
            }

            if ($error === '') {
                $admin = $db->validateAdmin($login, $password);

                if ($admin) {
                    $db->resetLoginAttempts($login);

                    session_regenerate_id(true);

                    $_SESSION['admin_id'] = (int) $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'] ?? '';
                    $_SESSION['admin_email'] = $admin['email'] ?? '';
                    $_SESSION['admin_role'] = $admin['role'] ?? '';
                    $_SESSION['admin_photo'] = $admin['profile_photo'] ?? null;
                    $_SESSION['admin_last_activity'] = time();
                    $_SESSION['admin_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $_SESSION['admin_ip_hash'] = hash('sha256', $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

                    if (empty($admin['is_verified'])) {
                        $_SESSION['verify_admin_id'] = (int) $admin['id'];
                        $_SESSION['verify_email'] = $admin['email'] ?? '';
                        header('Location: verify-email.php');
                        exit();
                    }

                    header('Location: dashboard.php');
                    exit();
                } else {
                    $db->incrementLoginAttempts($login);
                    $error = 'Usuario o contraseña incorrectos.';
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
    <title>Admin Login - <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="robots" content="noindex, nofollow, noarchive">
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
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-lime-400 rounded-3xl mb-6 shadow-lg shadow-lime-400/20">
                <i class="fas fa-ticket-alt text-black text-3xl"></i>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter">Panel <span class="text-gradient">Admin</span></h1>
            <p class="text-gray-500 mt-2 font-medium uppercase tracking-widest text-[10px]">Acceso restringido</p>
        </div>

        <form method="POST" action="" class="space-y-6" autocomplete="off">
            <?php echo csrf_field(); ?>

            <?php if ($error !== ''): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">
                    Usuario
                </label>
                <div class="relative group">
                    <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-lime-400 transition-colors"></i>
                    <input
                        type="text"
                        name="username"
                        required
                        maxlength="190"
                        class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-600"
                        placeholder="Usuario o email"
                        value="<?php echo htmlspecialchars($loginValue, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">
                    Contraseña
                </label>
                <div class="relative group">
                    <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-lime-400 transition-colors"></i>
                    <input
                        type="password"
                        name="password"
                        required
                        class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-600"
                        placeholder="••••••••"
                    >
                </div>
            </div>

            <div class="flex items-center justify-between px-1">
                <span class="text-xs text-gray-500">Solo personal autorizado</span>
                <a href="forgot-password.php" class="text-xs text-lime-400/70 hover:text-lime-400 transition">¿Olvidaste tu clave?</a>
            </div>

            <button type="submit" class="w-full bg-lime-400 text-black py-4 rounded-2xl font-black text-lg hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all flex items-center justify-center gap-2 group mt-4">
                Entrar
                <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
            </button>
        </form>

        <div class="mt-10 text-center border-t border-white/5 pt-8">
            <p class="text-xs text-gray-500 mb-4">¿Deseas organizar un evento?</p>
            <a href="register.php" class="inline-flex items-center gap-2 px-6 py-2 rounded-full bg-white/5 border border-white/10 text-xs font-bold text-white hover:bg-white/10 transition">
                <i class="fas fa-user-plus text-[10px]"></i>
                Crear cuenta de organizador
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
        const card = document.querySelector('.animate-fade-in');
        if (card) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            window.addEventListener('load', () => {
                card.style.transition = 'all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            });
        }

        let formSubmitted = false;
        const form = document.querySelector('form');

        if (form) {
            form.addEventListener('submit', function(e) {
                if (formSubmitted) {
                    e.preventDefault();
                    return false;
                }

                formSubmitted = true;

                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verificando...';
                }
            });
        }
    </script>
</body>
</html>
