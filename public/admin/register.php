<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$db = new Database();
$error = '';
$success = '';
$username = '';
$email = '';

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function safeText(string $value, int $max = 190): string {
    $value = trim(cleanInput($value));
    return mb_substr($value, 0, $max);
}

function safeEmail(string $value): string {
    return mb_strtolower(trim(cleanInput($value)));
}

function validUsername(string $username): bool {
    return (bool) preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username);
}

function strongEnoughPassword(string $password): bool {
    return mb_strlen($password) >= 12 && mb_strlen($password) <= 128;
}

if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, intenta de nuevo.';
    } else {
        if (function_exists('checkRateLimit') && !checkRateLimit('admin_register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 8, 600)) {
            $error = 'Demasiados intentos de registro. Espera unos minutos antes de volver a intentarlo.';
        } else {
            $username = safeText($_POST['username'] ?? '', 50);
            $email = safeEmail($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
                $error = 'Por favor completa todos los campos.';
            } elseif (!validUsername($username)) {
                $error = 'El usuario debe tener entre 3 y 50 caracteres y solo puede contener letras, números, punto, guion y guion bajo.';
            } elseif (!validateEmail($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'El formato del email es inválido.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Las contraseñas no coinciden.';
            } elseif (!strongEnoughPassword($password)) {
                $error = 'La contraseña debe tener al menos 12 caracteres.';
            } else {
                try {
                    $registerResult = $db->registerAdmin($username, $password, $email, 'organizer');

                    if ($registerResult === 'exists') {
                        $error = 'El nombre de usuario o el email ya están en uso.';
                    } elseif ($registerResult) {
                        $adminId = (int) $registerResult;
                        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                        if ($db->setAdminVerificationCode($adminId, $code)) {
                            if (sendVerificationCodeEmail($email, $code)) {
                                $_SESSION['verify_admin_id'] = $adminId;
                                $_SESSION['verify_email'] = $email;
                                header('Location: verify-email.php');
                                exit();
                            } else {
                                $error = 'La cuenta fue creada, pero no se pudo enviar el código de verificación.';
                            }
                        } else {
                            $error = 'No se pudo generar el código de verificación.';
                        }
                    } else {
                        $error = 'No se pudo completar el registro.';
                    }
                } catch (Throwable $e) {
                    error_log('admin/register error: ' . $e->getMessage());
                    $error = 'Se produjo un error al procesar el registro.';
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
    <title>Registro de Organizador - <?php echo h(SITE_NAME); ?></title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{
            background-color:#0A0E14;
            color:white;
            font-family:'Outfit',sans-serif;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            position:relative;
        }
        .bg-accent{
            position:absolute;
            width:40vw;
            height:40vw;
            background:radial-gradient(circle, rgba(96,165,250,0.1) 0%, transparent 70%);
            border-radius:50%;
            z-index:0;
            pointer-events:none;
        }
        .glass-card{
            background:rgba(255,255,255,0.03);
            backdrop-filter:blur(20px);
            border:1px solid rgba(255,255,255,0.08);
            box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .text-gradient{
            background:linear-gradient(to right,#DAFB71,#60A5FA);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
        }
        input{
            background:rgba(255,255,255,0.05)!important;
            border:1px solid rgba(255,255,255,0.1)!important;
            color:white!important;
        }
        input:focus{
            border-color:rgba(96,165,250,0.5)!important;
            box-shadow:0 0 15px rgba(96,165,250,0.1)!important;
        }
    </style>
</head>
<body>
    <div class="bg-accent top-[-10%] right-[-10%]"></div>
    <div class="bg-accent bottom-[-10%] left-[-10%]"></div>

    <div class="glass-card rounded-[2.5rem] p-10 w-full max-w-md my-12 relative z-10 animate-fade-in">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-500 rounded-3xl mb-6 shadow-lg shadow-blue-500/20">
                <i class="fas fa-user-plus text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter">Únete como <span class="text-gradient">Organizador</span></h1>
            <p class="text-gray-500 mt-2 font-medium uppercase tracking-widest text-[10px]">Gestión Premium de Eventos</p>
        </div>

        <form method="POST" action="" class="space-y-5" autocomplete="off">
            <?php echo csrf_field(); ?>

            <?php if ($error !== ''): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo h($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                    <i class="fas fa-check-circle"></i>
                    <?php echo h($success); ?>
                </div>
            <?php endif; ?>

            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Usuario</label>
                <div class="relative group">
                    <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                    <input type="text" name="username" required maxlength="50"
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-blue-400/50 transition-all placeholder:text-gray-600"
                           placeholder="Tu nombre de usuario"
                           value="<?php echo h($username); ?>">
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Email</label>
                <div class="relative group">
                    <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                    <input type="email" name="email" required maxlength="190"
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-blue-400/50 transition-all placeholder:text-gray-600"
                           placeholder="tu@email.com"
                           value="<?php echo h($email); ?>">
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Contraseña</label>
                <div class="relative group">
                    <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                    <input type="password" name="password" required minlength="12" maxlength="128"
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-blue-400/50 transition-all placeholder:text-gray-600"
                           placeholder="Mínimo 12 caracteres">
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Confirmar Contraseña</label>
                <div class="relative group">
                    <i class="fas fa-shield-alt absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-blue-400 transition-colors"></i>
                    <input type="password" name="confirm_password" required minlength="12" maxlength="128"
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-blue-400/50 transition-all placeholder:text-gray-600"
                           placeholder="Repite tu contraseña">
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-500 text-white py-4 rounded-2xl font-black text-lg hover:shadow-[0_0_30px_rgba(59,130,246,0.3)] transition-all flex items-center justify-center gap-2 group mt-6">
                Crear Cuenta
                <i class="fas fa-check-circle group-hover:scale-110 transition-transform"></i>
            </button>
        </form>

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
    </script>
</body>
</html>
