<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$db = new Database();
$error = '';
$success = '';

$adminId = (int) ($_SESSION['verify_admin_id'] ?? 0);
$email = trim((string) ($_SESSION['verify_email'] ?? ''));

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($adminId <= 0 || $email === '') {
    header('Location: register.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor, intenta de nuevo.';
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (isset($_POST['resend'])) {
            if (function_exists('checkRateLimit') && !checkRateLimit('verify_resend_' . $adminId . '_' . $ip, 3, 900)) {
                $error = 'Has solicitado demasiados reenvíos. Espera unos minutos antes de intentarlo otra vez.';
            } else {
                try {
                    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                    if ($db->setAdminVerificationCode($adminId, $code) && sendVerificationCodeEmail($email, $code)) {
                        $success = 'Se ha reenviado un nuevo código a tu correo.';
                    } else {
                        $error = 'No se pudo reenviar el código.';
                    }
                } catch (Throwable $e) {
                    error_log('admin/verify-email resend error: ' . $e->getMessage());
                    $error = 'Se produjo un error al reenviar el código.';
                }
            }
        } else {
            if (function_exists('checkRateLimit') && !checkRateLimit('verify_code_' . $adminId . '_' . $ip, 10, 900)) {
                $error = 'Demasiados intentos de verificación. Espera unos minutos antes de volver a intentarlo.';
            } else {
                $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));

                if (!preg_match('/^\d{6}$/', $code)) {
                    $error = 'Introduce un código válido de 6 dígitos.';
                } else {
                    try {
                        if ($db->verifyAdmin($adminId, $code)) {
                            $success = '¡Cuenta verificada con éxito! Ya puedes iniciar sesión.';
                            unset($_SESSION['verify_admin_id'], $_SESSION['verify_email']);
                        } else {
                            $error = 'Código de verificación incorrecto o caducado.';
                        }
                    } catch (Throwable $e) {
                        error_log('admin/verify-email verify error: ' . $e->getMessage());
                        $error = 'Se produjo un error al verificar la cuenta.';
                    }
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
    <title>Verificar Cuenta - <?php echo h(SITE_NAME); ?></title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{background-color:#0A0E14;color:white;font-family:'Outfit',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
        .bg-accent{position:absolute;width:40vw;height:40vw;background:radial-gradient(circle, rgba(218,251,113,.1) 0%, transparent 70%);border-radius:50%;z-index:0;pointer-events:none}
        .glass-card{background:rgba(255,255,255,.03);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);box-shadow:0 25px 50px -12px rgba(0,0,0,.5)}
        .text-gradient{background:linear-gradient(to right,#DAFB71,#60A5FA);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        input{background:rgba(255,255,255,.05)!important;border:1px solid rgba(255,255,255,.1)!important;color:white!important;font-size:24px!important;letter-spacing:.5em!important;text-align:center!important}
        input:focus{border-color:rgba(218,251,113,.5)!important;box-shadow:0 0 15px rgba(218,251,113,.1)!important}
    </style>
</head>
<body>
    <div class="bg-accent top-[-10%] left-[-10%]"></div>
    <div class="bg-accent bottom-[-10%] right-[-10%]"></div>

    <div class="glass-card rounded-[2.5rem] p-10 w-full max-w-md relative z-10">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-lime-400 rounded-3xl mb-6 shadow-lg shadow-lime-400/20">
                <i class="fas fa-shield-alt text-black text-3xl"></i>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter">Verifica tu <span class="text-gradient">Cuenta</span></h1>
            <p class="text-gray-500 mt-2 font-medium text-xs leading-relaxed px-4">
                Hemos enviado un código de 6 dígitos a <span class="text-white"><?php echo h($email); ?></span>.
                Introdúcelo a continuación para activar tu cuenta.
            </p>
        </div>

        <?php if ($success !== '' && !isset($_SESSION['verify_admin_id'])): ?>
            <div class="text-center">
                <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 px-5 py-6 rounded-2xl text-sm mb-8">
                    <i class="fas fa-check-circle text-2xl mb-3"></i>
                    <p><?php echo h($success); ?></p>
                </div>
                <a href="login.php" class="inline-flex items-center gap-2 px-8 py-3 bg-lime-400 text-black rounded-xl font-black text-sm hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">
                    Iniciar Sesión
                </a>
            </div>
        <?php else: ?>
            <?php if ($success !== ''): ?>
                <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3 mb-6">
                    <i class="fas fa-check-circle"></i>
                    <?php echo h($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6" autocomplete="off">
                <?php echo csrf_field(); ?>

                <?php if ($error !== ''): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest text-center">Código de Verificación</label>
                    <input type="text" name="code" required maxlength="6" inputmode="numeric" pattern="\d{6}"
                           class="w-full py-5 rounded-2xl outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-700"
                           placeholder="000000" autofocus>
                </div>

                <button type="submit" class="w-full bg-lime-400 text-black py-4 rounded-2xl font-black text-lg hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all flex items-center justify-center gap-2 group mt-4">
                    Verificar Cuenta
                    <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>

            <div class="mt-8 text-center">
                <p class="text-xs text-gray-500 mb-2">¿No has recibido el código?</p>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="resend" value="1" class="text-lime-400/70 hover:text-lime-400 transition text-xs font-bold uppercase tracking-widest bg-transparent border-none cursor-pointer">
                        Reenviar Código
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div class="mt-10 text-center border-t border-white/5 pt-8">
            <a href="register.php" class="inline-flex items-center gap-2 text-gray-500 hover:text-white transition text-xs font-bold uppercase tracking-widest">
                <i class="fas fa-arrow-left text-[10px]"></i>
                Volver al Registro
            </a>
        </div>
    </div>
</body>
</html>
