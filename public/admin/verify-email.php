<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

session_start();
$db = new Database();
$error = '';
$success = '';

$adminId = $_SESSION['verify_admin_id'] ?? null;
$email = $_SESSION['verify_email'] ?? '';

if (!$adminId) {
    header('Location: register.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = cleanInput($_POST['code']);
    
    if (empty($code)) {
        $error = 'Por favor, introduce el código de verificación.';
    } else {
        if ($db->verifyAdmin($adminId, $code)) {
            $success = '¡Cuenta verificada con éxito! Ya puedes iniciar sesión.';
            unset($_SESSION['verify_admin_id']);
            unset($_SESSION['verify_email']);
        } else {
            $error = 'Código de verificación incorrecto. Inténtalo de nuevo.';
        }
    }
}

// Reenviar código
if (isset($_GET['resend'])) {
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    if ($db->setAdminVerificationCode($adminId, $code)) {
        try {
            if (sendVerificationCodeEmail($email, $code)) {
                $success = 'Se ha reenviado un nuevo código a tu correo.';
            } else {
                $error = 'No se pudo reenviar el código.';
            }
        } catch (Exception $e) {
            $error = 'Error al reenviar: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Cuenta - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0A0E14; color: white; font-family: 'Outfit', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .bg-accent { position: absolute; width: 40vw; height: 40vw; background: radial-gradient(circle, rgba(218, 251, 113, 0.1) 0%, transparent 70%); border-radius: 50%; z-index: 0; pointer-events: none; }
        .glass-card { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .text-gradient { background: linear-gradient(to right, #DAFB71, #60A5FA); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        input { background: rgba(255, 255, 255, 0.05) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; color: white !important; font-size: 24px !important; letter-spacing: 0.5em !important; text-align: center !important; }
        input:focus { border-color: rgba(218, 251, 113, 0.5) !important; box-shadow: 0 0 15px rgba(218, 251, 113, 0.1) !important; }
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
            <p class="text-gray-500 mt-2 font-medium text-xs leading-relaxed px-4">Hemos enviado un código de 6 dígitos a <span class="text-white"><?php echo htmlspecialchars($email); ?></span>. Introdúcelo a continuación para activar tu cuenta.</p>
        </div>
        
        <?php if ($success): ?>
            <div class="text-center">
                <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 px-5 py-6 rounded-2xl text-sm mb-8">
                    <i class="fas fa-check-circle text-2xl mb-3"></i>
                    <p><?php echo $success; ?></p>
                </div>
                <a href="login.php" class="inline-flex items-center gap-2 px-8 py-3 bg-lime-400 text-black rounded-xl font-black text-sm hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">
                    Iniciar Sesión
                </a>
            </div>
        <?php else: ?>
            <form method="POST" class="space-y-6">
                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest text-center">Código de Verificación</label>
                    <input type="text" name="code" required maxlength="6" pattern="\d{6}"
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
                <a href="?resend=1" class="text-lime-400/70 hover:text-lime-400 transition text-xs font-bold uppercase tracking-widest">
                    Reenviar Código
                </a>
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
