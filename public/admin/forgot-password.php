<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

$db = new Database();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email']);
    
    if (empty($email)) {
        $error = 'Por favor, introduce tu email';
    } else {
        $admin = $db->getAdminByEmail($email);
        if ($admin) {
            $token = bin2hex(random_bytes(32));
            if ($db->createPasswordReset($email, $token)) {
                try {
                    if (sendResetPasswordEmail($email, $token)) {
                        $message = 'Se ha enviado un enlace de recuperación a tu correo electrónico.';
                    } else {
                        $error = 'No se pudo enviar el correo de recuperación.';
                    }
                } catch (Exception $e) {
                    $error = 'Error al enviar: ' . $e->getMessage();
                }
            } else {
                $error = 'Error al generar el token de recuperación.';
            }
        } else {
            // No revelamos si el email existe o no por seguridad, 
            // pero en un entorno admin podemos ser un poco más específicos o fingir éxito.
            $message = 'Si el correo existe, recibirás un enlace de recuperación.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - <?php echo SITE_NAME; ?></title>
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

    <div class="glass-card rounded-[2.5rem] p-10 w-full max-w-md relative z-10">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-lime-400 rounded-3xl mb-6 shadow-lg shadow-lime-400/20">
                <i class="fas fa-key text-black text-3xl"></i>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter">Recuperar <span class="text-gradient">Contraseña</span></h1>
            <p class="text-gray-500 mt-2 font-medium text-xs leading-relaxed px-4">Introduce tu correo electrónico y te enviaremos un enlace para restablecer tu clave.</p>
        </div>
        
        <form method="POST" class="space-y-6">
            <?php if ($message): ?>
                <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-5 py-4 rounded-2xl text-sm flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="space-y-2">
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Correo Electrónico</label>
                <div class="relative group">
                    <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-lime-400 transition-colors"></i>
                    <input type="email" name="email" required
                           class="w-full pl-12 pr-4 py-4 rounded-2xl outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-600"
                           placeholder="tu@email.com">
                </div>
            </div>
            
            <button type="submit" class="w-full bg-lime-400 text-black py-4 rounded-2xl font-black text-lg hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all flex items-center justify-center gap-2 group mt-4">
                Enviar Enlace
                <i class="fas fa-paper-plane group-hover:translate-x-1 transition-transform"></i>
            </button>
        </form>
        
        <div class="mt-10 text-center border-t border-white/5 pt-8">
            <a href="login.php" class="inline-flex items-center gap-2 text-gray-500 hover:text-white transition text-xs font-bold uppercase tracking-widest">
                <i class="fas fa-arrow-left text-[10px]"></i>
                Volver al Login
            </a>
        </div>
    </div>
</body>
</html>
