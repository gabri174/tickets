<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$db = new Database();
$message = '';
$error = '';
$success = false;

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function validResetToken(string $token): bool {
    return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
}

function strongEnoughPassword(string $password): bool {
    $len = mb_strlen($password);
    return $len >= 12 && $len <= 128;
}

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !validResetToken($token)) {
    header('Location: login.php');
    exit();
}

try {
    $resetRequest = $db->getPasswordReset($token);
} catch (Throwable $e) {
    error_log('admin/reset-password lookup error: ' . $e->getMessage());
    $resetRequest = false;
}

if (!$resetRequest) {
    $error = 'El enlace de recuperación es inválido o ha expirado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRequest) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, intenta de nuevo.';
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (function_exists('checkRateLimit') && !checkRateLimit('reset_password_' . $ip, 8, 900)) {
            $error = 'Demasiados intentos. Espera unos minutos antes de volver a intentarlo.';
        } else {
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (!strongEnoughPassword($password)) {
                $error = 'La contraseña debe tener al menos 12 caracteres.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                try {
                    if ($db->updateAdminPasswordByEmail($resetRequest['email'], $password)) {
                        $db->deletePasswordReset($resetRequest['email']);
                        $message = 'Tu contraseña ha sido restablecida correctamente. Ya puedes iniciar sesión.';
                        $success = true;
                    } else {
                        $error = 'No se pudo restablecer la contraseña.';
                    }
                } catch (Throwable $e) {
                    error_log('admin/reset-password update error: ' . $e->getMessage());
                    $error = 'Se produjo un error al restablecer la contraseña.';
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
    <title>Establecer Nueva Contraseña - <?php echo h(SITE_NAME); ?></title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{background-color:#0A0E14;color:white;font-family:'Outfit',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
        .bg-accent{position:absolute;width:40vw;height:40vw;background:radial-gradient(circle, rgba(218,251,113,0.1) 0%, transparent 70%);border-radius:50%;z-index:0;pointer-events:none}
        .glass-card{background:rgba(255,255,255,0.03);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.08);box-shadow:0 25px 50px -12px rgba(0,0,0,0.5)}
        .text-gradient{background:linear-gradient(to right,#DAFB71,#60A5FA);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        input{background:rgba(255,255,255,0.05)!important;border:1px solid rgba(255,255,255,0.1)!important;color:white!important}
        input:focus{border-color:rgba(218,251,113,0.5)!important;box-shadow:0 0 15px rgba(218,251,113,0.1)!important}
    </style>
</head>
<body>
    <div class="bg-accent top-[-10%] left-[-10%]"></div>
    <div class="bg-accent bottom-[-10%] right-[-10%]"></div>

    <div class="glass-card rounded-[2.5rem] p-10 w-full max-w-md relative z-10">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-lime-400 rounded-3xl mb-6 shadow-lg shadow-lime-400/20">
                <i class="fas fa-lock-open text-black text-3xl"></i>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter">Nueva <span class="text-gradient">Contraseña</span></h1>
            <p class="text-gray-500 mt-2 font-medium text-xs leading-relaxed px-4">
                Crea una contraseña segura para tu cuenta de administrador.
            </p>
        </div>

        <?php if ($success): ?>
            <div class="text-center">
                <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 px-5 py-6 rounded-2xl text-sm mb-8">
                    <i class="fas fa-check-circle text-2xl mb-3"></i>
                    <p><?php echo h($message); ?></p>
                </div>
                <a href="login.php" class="inline-flex items-center gap-2 px-8 py-3 bg-lime-400 text-black rounded-xl font-black text-sm hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">
                    Iniciar Sesión
                </a>
            </div>
        <?php else: ?>
            <form method="POST" class="space-y-6" autocomplete="off">
                <?php echo csrf_field(); ?>

                <?php if ($error !== ''): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Nueva Contraseña</label>
                    <div class="relative group">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-lime-400 transition-colors"></i>
                        <input type="password" name="password" required minlength="12" maxlength="128"
                               class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-lime-400/50 transition-all"
                               placeholder="Mínimo 12 caracteres">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Confirmar Contraseña</label>
                    <div class="relative group">
                        <i class="fas fa-shield-alt absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-lime-400 transition-colors"></i>
                        <input type="password" name="confirm_password" required minlength="12" maxlength="128"
                               class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-lime-400/50 transition-all"
                               placeholder="Repite la contraseña">
                    </div>
                </div>

                <button type="submit" class="w-full bg-lime-400 text-black py-4 rounded-2xl font-black text-lg hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all flex items-center justify-center gap-2 group mt-4">
                    Restablecer Clave
                    <i class="fas fa-check group-hover:scale-110 transition-transform"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
