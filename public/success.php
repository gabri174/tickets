<?php
session_start();
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

// Verificar si hay datos de compra
if (!isset($_SESSION['purchase_success'])) {
    header('Location: index.php');
    exit();
}

$purchase = $_SESSION['purchase_success'];
$emailError = $_SESSION['email_error'] ?? null;
$debugMode = isset($_GET['debug']);

if ($debugMode) {
    $_SESSION['debug_email'] = true;
}

// Limpiar sesión después de mostrar
if (!$debugMode) {
    unset($_SESSION['purchase_success']);
    unset($_SESSION['email_error']);
    unset($_SESSION['smtp_log']);
    unset($_SESSION['debug_email']);
}

$db = new Database();
$eventData = $db->getEventById($purchase['event_id'] ?? 0);
$imgUrl = ($eventData && $eventData['image_url']) ? SITE_URL . '/' . $eventData['image_url'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Compra Exitosa! - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: var(--bg-dark); }
        .success-checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #DAFB71;
            stroke-miterlimit: 10;
            margin: 10% auto;
            box-shadow: inset 0px 0px 0px #DAFB71;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="flex justify-between items-center mb-12 pt-6">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 glass-pill flex items-center justify-center text-lime-400 bg-lime-400/10 border-lime-400/20">
                    <i class="fas fa-check text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">¡Compra Exitosa!</h2>
                    <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Confirmación de Reserva</p>
                </div>
            </div>
            <nav class="hidden md:flex gap-6">
                <a href="index.php" class="text-sm font-medium hover:text-lime-400 transition">Inicio</a>
                <a href="#" class="text-sm font-medium text-gray-400 hover:text-white transition">Mis Tickets</a>
            </nav>
            <a href="index.php" class="btn-modern bg-lime-400 text-black px-8 py-2 text-sm">Hecho</a>
        </header>

        <!-- Notification -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold mb-2">¡Todo listo!</h1>
            <p class="text-gray-400 text-sm">Tus tickets han sido enviados a <span class="text-white font-medium"><?php echo htmlspecialchars($purchase['email']); ?></span></p>
        </div>

        <?php if ($emailError): ?>
            <div class="glass-card mb-8 p-4 border-red-500/20 bg-red-500/5 text-red-400 text-xs text-center">
                <i class="fas fa-exclamation-triangle mr-1"></i> <?php echo htmlspecialchars($emailError); ?>
            </div>
        <?php endif; ?>

        <!-- Tickets Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8 mb-12">
            <?php foreach ($purchase['tickets'] as $index => $ticket): ?>
                <div class="ticket-main-card shadow-2xl animate-ticket">
                    <!-- Top Section: Image -->
                    <?php if ($imgUrl): ?>
                        <img src="<?php echo $imgUrl; ?>" class="ticket-image" alt="">
                    <?php else: ?>
                        <div class="ticket-image bg-gray-200 flex items-center justify-center text-gray-400">
                             <i class="fas fa-ticket-alt text-5xl"></i>
                        </div>
                    <?php endif; ?>

                    <div class="ticket-content">
                        <h3 class="text-2xl font-bold text-center mb-6"><?php echo htmlspecialchars($purchase['event_title']); ?></h3>
                        
                        <div class="grid grid-cols-2 gap-y-6 gap-x-4 mb-2">
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">FECHA</p>
                                <p class="text-sm font-bold text-gray-800"><?php echo formatDate($eventData['date_event'] ?? date('Y-m-d'), 'd M, Y'); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">HORA</p>
                                <p class="text-sm font-bold text-gray-800">19:30 PM</p> <!-- Mocking time if not in schema -->
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">LUGAR</p>
                                <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($eventData['location'] ?? 'Auditorio Principal'); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">ASIENTO</p>
                                <p class="text-sm font-bold text-gray-800">General</p>
                            </div>
                        </div>

                        <div class="ticket-divider"></div>

                        <div class="flex flex-col items-center">
                            <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-4">NOMBRE ASISTENTE</p>
                            <p class="text-lg font-bold text-gray-900 mb-6"><?php echo htmlspecialchars($ticket['name']); ?></p>
                            
                            <!-- QR Visual -->
                            <div class="p-2 border-2 border-dashed border-gray-200 rounded-2xl mb-4">
                                <?php 
                                $qrWebPath = SITE_URL . '/qrcodes/' . basename($ticket['qr_path']);
                                ?>
                                <img src="<?php echo $qrWebPath; ?>" alt="QR" class="w-32 h-32 grayscale contrast-125">
                            </div>
                            <p class="text-xs font-mono text-gray-400 tracking-tighter uppercase"><?php echo $ticket['code']; ?></p>
                        </div>
                    </div>

                    <!-- Action Floating Bar -->
                    <div class="flex gap-2 justify-center pb-6">
                        <button onclick="shareIndividualTicket('<?php echo $ticket['code']; ?>', '<?php echo htmlspecialchars($purchase['event_title']); ?>')" 
                                class="glass-pill px-4 py-2 text-xs font-bold text-gray-600 bg-gray-100 border-none hover:bg-lime-400 hover:text-black transition">
                            <i class="fab fa-whatsapp mr-1"></i> Enviar
                        </button>
                        <a href="ticket.php?code=<?php echo $ticket['code']; ?>" target="_blank"
                           class="glass-pill px-4 py-2 text-xs font-bold text-gray-600 bg-gray-100 border-none hover:bg-blue-500 hover:text-white transition">
                            <i class="fas fa-external-link-alt mr-1"></i> Ver
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Global Actions -->
        <div class="fixed bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-black to-transparent z-40">
            <div class="max-w-[450px] mx-auto flex gap-4">
                 <a href="index.php" class="btn-modern bg-gray-800 text-white flex-1 py-4">
                    <i class="fas fa-home"></i> Inicio
                </a>
                <button onclick="shareOnWhatsApp()" class="btn-modern btn-lime flex-[2] py-4">
                    <i class="fab fa-whatsapp mr-2 text-xl"></i> Compartir Todo
                </button>
            </div>
        </div>
        
        <div class="h-24"></div> <!-- Footer spacer -->
    </div>

    <script>
        function shareOnWhatsApp() {
            const eventTitle = <?php echo json_encode($purchase['event_title']); ?>;
            const phone = <?php echo json_encode(preg_replace('/[^0-9]/', '', $purchase['phone'] ?? '')); ?>;
            const message = `🎉 ¡Hola! Aquí tienes tus entradas para "${eventTitle}"!\n\n` +
                          `Puedes ver tus tickets aquí:\n<?php echo SITE_URL; ?>/index.php\n\n` +
                          `¡Gracias por tu compra! 🎪`;
            window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
        }

        function shareIndividualTicket(code, eventTitle) {
            const ticketUrl = `<?php echo SITE_URL; ?>/ticket.php?code=${code}`;
            const phone = <?php echo json_encode(preg_replace('/[^0-9]/', '', $purchase['phone'] ?? '')); ?>;
            const message = `🎫 Aquí tienes tu entrada para "${eventTitle}"\n\n` +
                          `Código: ${code}\n` +
                          `Presenta el QR al llegar:\n${ticketUrl}`;
            window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
        }
    </script>

    <style>
        .animate-ticket {
            animation: bounceIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes bounceIn {
            from { opacity: 0; transform: scale(0.9) translateY(40px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</body>
</html>
