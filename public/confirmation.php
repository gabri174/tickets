<?php
require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow, noarchive', true);

$purchaseResult = $_SESSION['purchase_result'] ?? null;

unset($_SESSION['purchase_success']);
unset($_SESSION['purchase_result']);
unset($_SESSION['email_error']);
unset($_SESSION['smtp_log']);
unset($_SESSION['debug_email']);
unset($_SESSION['pending_purchase']);

if (!$purchaseResult || empty($purchaseResult['event_id'])) {
    http_response_code(400);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Compra no disponible</title>
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
            }
            .glass-card {
                background: rgba(255, 255, 255, 0.03);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.08);
            }
        </style>
    </head>
    <body>
        <div class="max-w-xl mx-auto px-4 text-center">
            <div class="glass-card rounded-[2.5rem] p-10">
                <div class="w-20 h-20 mx-auto mb-6 bg-red-500/10 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-400 text-3xl"></i>
                </div>
                <h1 class="text-3xl font-black mb-4">Compra no disponible</h1>
                <p class="text-gray-400 mb-8">
                    No hay datos válidos de compra en tu sesión. Por seguridad, vuelve al inicio y accede otra vez desde el flujo de compra.
                </p>
                <a href="../"
                   class="inline-flex items-center gap-2 px-8 py-4 bg-lime-400 text-black rounded-2xl font-black hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all">
                    <i class="fas fa-home"></i>
                    Volver al inicio
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$eventId = (int) ($purchaseResult['event_id'] ?? 0);
$tickets = is_array($purchaseResult['tickets'] ?? null) ? $purchaseResult['tickets'] : [];
$totalPrice = (float) ($purchaseResult['total_price'] ?? 0);
$email = (string) ($purchaseResult['email'] ?? '');
$pdfPath = (string) ($purchaseResult['pdf_path'] ?? '');

$db = new Database();
$event = $db->getEventById($eventId);

if (!$event) {
    $event = [
        'title' => 'Evento',
        'date_event' => date('Y-m-d H:i:s'),
        'location' => 'Ubicación no disponible',
        'admin_id' => 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Compra Confirmada! - <?php echo htmlspecialchars((string) $event['title'], ENT_QUOTES, 'UTF-8'); ?></title>
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
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .text-gradient {
            background: linear-gradient(to right, #DAFB71, #60A5FA);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .checkmark {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #DAFB71;
            stroke-miterlimit: 10;
            box-shadow: inset 0px 0px 0px #DAFB71;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }

        .checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #DAFB71;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }

        @keyframes stroke {
            100% { stroke-dashoffset: 0; }
        }

        @keyframes scale {
            0%, 100% { transform: none; }
            50% { transform: scale3d(1.1, 1.1, 1); }
        }

        @keyframes fill {
            100% { box-shadow: inset 0px 0px 0px 50px rgba(218, 251, 113, 0.1); }
        }
    </style>
</head>
<body>
    <div class="max-w-2xl mx-auto px-4 text-center">
        <div class="mb-8">
            <svg class="checkmark mx-auto" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" aria-hidden="true">
                <circle class="checkmark__circle" cx="50" cy="50" r="40" />
                <path class="checkmark__check" fill="none" d="M28 48 L42 62 L72 30" />
            </svg>
        </div>

        <h1 class="text-4xl md:text-5xl font-black mb-4">
            ¡Compra <span class="text-gradient">Confirmada!</span>
        </h1>

        <p class="text-xl text-gray-400 mb-8">
            Gracias por tu compra. Hemos enviado los tickets a tu correo electrónico.
        </p>

        <div class="glass-card rounded-[2.5rem] p-8 mb-8">
            <h2 class="text-2xl font-bold mb-6"><?php echo htmlspecialchars((string) $event['title'], ENT_QUOTES, 'UTF-8'); ?></h2>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="text-left">
                    <p class="text-xs text-gray-500 uppercase font-bold mb-1">Fecha</p>
                    <p class="font-bold"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $event['date_event'])), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="text-left">
                    <p class="text-xs text-gray-500 uppercase font-bold mb-1">Hora</p>
                    <p class="font-bold"><?php echo htmlspecialchars(date('H:i', strtotime((string) $event['date_event'])), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="text-left col-span-2">
                    <p class="text-xs text-gray-500 uppercase font-bold mb-1">Ubicación</p>
                    <p class="font-bold"><?php echo htmlspecialchars((string) $event['location'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>

            <div class="border-t border-white/10 pt-6">
                <p class="text-xs text-gray-500 uppercase font-bold mb-4">Tickets Comprados</p>
                <div class="space-y-3">
                    <?php foreach ($tickets as $ticket): ?>
                        <?php
                            $ticketName = (string) ($ticket['name'] ?? '');
                            $ticketEmail = (string) ($ticket['email'] ?? '');
                            $ticketCode = (string) ($ticket['code'] ?? '');
                        ?>
                        <div class="glass-card rounded-xl p-4 flex items-center justify-between">
                            <div class="text-left">
                                <p class="font-bold"><?php echo htmlspecialchars($ticketName, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($ticketEmail, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500">Código</p>
                                <p class="font-mono text-sm text-lime-400"><?php echo htmlspecialchars(substr($ticketCode, -8), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($tickets)): ?>
                        <div class="glass-card rounded-xl p-4">
                            <p class="text-gray-400 text-sm">La compra se registró, pero no hay tickets disponibles para mostrar en esta vista.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="border-t border-white/10 pt-6 mt-6">
                <div class="flex items-center justify-between">
                    <p class="text-lg font-bold">Total Pagado</p>
                    <p class="text-3xl font-black text-lime-400"><?php echo htmlspecialchars(number_format($totalPrice, 2), ENT_QUOTES, 'UTF-8'); ?>€</p>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-2xl p-6 mb-8">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-lime-400/10 rounded-full flex items-center justify-center">
                    <i class="fas fa-envelope text-lime-400"></i>
                </div>
                <div class="text-left">
                    <p class="text-sm font-bold">Hemos enviado los tickets a</p>
                    <p class="text-lime-400"><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <?php if (!empty($event['admin_id'])): ?>
                <a href="store.php?id=<?php echo (int) $event['admin_id']; ?>"
                   class="px-8 py-4 bg-lime-400 text-black rounded-2xl font-black hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-calendar-alt"></i>
                    Ver más eventos
                </a>
            <?php endif; ?>

            <a href="../"
               class="px-8 py-4 bg-white/5 border border-white/10 rounded-2xl font-bold hover:bg-white/10 transition-all flex items-center justify-center gap-2">
                <i class="fas fa-home"></i>
                Inicio
            </a>
        </div>

        <?php if ($pdfPath !== ''): ?>
            <div class="mt-8">
                <a href="<?php echo htmlspecialchars($pdfPath, ENT_QUOTES, 'UTF-8'); ?>"
                   download
                   class="inline-flex items-center gap-2 text-lime-400 hover:text-lime-300 transition font-bold">
                    <i class="fas fa-download"></i>
                    Descargar tickets en PDF
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
