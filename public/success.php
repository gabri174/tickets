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
    <!-- Main Header / Navbar -->
    <header class="sticky top-0 z-50 bg-[#0A0E14]/80 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-lime-400 rounded-xl flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-black text-xl"></i>
                    </div>
                    <span class="text-2xl font-black tracking-tighter text-white">TICKETAPP</span>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-8">
                    <a href="index.php" class="text-sm font-semibold text-gray-400 hover:text-white transition">Inicio</a>
                    <a href="about.php" class="text-sm font-semibold text-gray-400 hover:text-white transition">Nosotros</a>
                    <a href="contact.php" class="text-sm font-semibold text-gray-400 hover:text-white transition">Contacto</a>
                    <div class="w-px h-6 bg-white/10 mx-2"></div>
                    <a href="admin/" class="flex items-center gap-2 text-sm font-semibold text-gray-300 hover:text-white transition px-4 py-2 rounded-full bg-white/5 border border-white/10">
                        <i class="fas fa-user-shield text-xs"></i>
                        Administración
                    </a>
                </nav>

                <!-- Mobile Menu Button -->
                <button class="md:hidden text-gray-400 hover:text-white">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Success Status -->
        <div class="flex items-center gap-6 mb-12 py-8 bg-lime-400/5 border border-lime-400/10 rounded-3xl px-8">
            <div class="h-16 w-16 bg-lime-400 rounded-2xl flex items-center justify-center text-black shadow-lg shadow-lime-400/20 flex-shrink-0">
                <i class="fas fa-check text-3xl"></i>
            </div>
            <div>
                <h2 class="text-3xl font-black text-white mb-1">¡Compra Completada!</h2>
                <p class="text-gray-400 font-medium">Tus tickets están listos y han sido enviados por correo.</p>
            </div>
        </div>

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

        <div class="h-24"></div>
    </div>

    <!-- Footer -->
    <footer class="bg-[#0A0E14] border-t border-white/5 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="flex items-center justify-center gap-2 mb-6">
                <i class="fas fa-ticket-alt text-lime-400"></i>
                <span class="text-xl font-bold text-white tracking-tighter">TICKETAPP</span>
            </div>
            <p class="text-gray-500 text-sm mb-8">Gracias por confiar en nosotros para tus eventos.</p>
            <div class="pt-8 border-t border-white/5 text-gray-600 text-[10px] uppercase tracking-widest font-bold">
                &copy; <?php echo date('Y'); ?> TicketApp. Todos los derechos reservados.
            </div>
        </div>
    </footer>

    <script>
        function shareOnWhatsApp() {
            const eventTitle = <?php echo json_encode($purchase['event_title']); ?>;
            const phone = <?php echo json_encode(preg_replace('/[^0-9]/', '', $purchase['phone'] ?? '')); ?>;
            const firstTicket = <?php echo json_encode($purchase['tickets'][0]['code'] ?? ''); ?>;
            const ticketUrl = firstTicket ? `<?php echo SITE_URL; ?>/ticket.php?code=${firstTicket}` : `<?php echo SITE_URL; ?>`;
            
            const message = `🎉 ¡Hola! Aquí tienes tus entradas para "${eventTitle}"!\n\n` +
                          `Puedes ver tu entrada principal aquí:\n${ticketUrl}\n\n` +
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
