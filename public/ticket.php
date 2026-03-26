<?php
session_start();
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

$db = new Database();
$isAdmin = isset($_SESSION['admin_id']);
$validationSuccess = false;
$errorMsg = '';

// Obtener ticket por código
if (!isset($_GET['code']) || empty($_GET['code'])) {
    header('HTTP/1.0 404 Not Found');
    echo '<h1>Ticket no encontrado</h1>';
    exit();
}

$ticketCode = cleanInput($_GET['code']);

// Procesar Validación (Solo para Admins)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate' && $isAdmin) {
    $currentTicket = $db->getTicketByCode($ticketCode);
    if ($currentTicket && $currentTicket['status'] === 'valid') {
        if ($db->updateTicketStatus($currentTicket['id'], 'used')) {
            $validationSuccess = true;
        } else {
            $errorMsg = 'Error al actualizar el estado del ticket.';
        }
    } else {
        $errorMsg = 'El ticket no es válido o ya ha sido utilizado.';
    }
}

$ticket = $db->getTicketByCode($ticketCode);

if (!$ticket) {
    header('HTTP/1.0 404 Not Found');
    echo '<h1>Ticket no encontrado</h1>';
    exit();
}

// Asegurar que image_url existe para evitar warnings (aunque ya se añadió a la query)
if (!isset($ticket['image_url'])) {
    $ticket['image_url'] = null;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - <?php echo htmlspecialchars($ticket['event_title']); ?></title>
    
    <!-- OpenGraph Meta Tags for Rich Sharing -->
    <meta property="og:title" content="🎫 Ticket: <?php echo htmlspecialchars($ticket['event_title']); ?>">
    <meta property="og:description" content="Fecha: <?php echo formatDate($ticket['date_event']); ?> en <?php echo htmlspecialchars($ticket['location']); ?>. ¡Presenta este ticket en la entrada!">
    <?php if ($ticket['image_url']): ?>
        <meta property="og:image" content="<?php echo SITE_URL . '/' . htmlspecialchars($ticket['image_url']); ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?php echo SITE_URL . '/ticket.php?code=' . $ticket['ticket_code']; ?>">
    <meta property="og:type" content="website">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: #0A0E14;
            background: linear-gradient(180deg, #0A0E14 0%, #171E26 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
        }
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 10;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .status-valid { background: #DAFB71; color: #000; }
        .status-used { background: #EE3D5A; color: #fff; }
        .status-cancelled { background: #666; color: #fff; }
        
        .ticket-container {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
        }

        @media (max-width: 640px) {
            .ticket-container {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Main Header / Navbar -->
    <header class="sticky top-0 z-50 bg-[#0A0E14]/80 backdrop-blur-xl border-b border-white/5 w-full">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <a href="index.php" class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-lime-400 rounded-xl flex items-center justify-center">
                            <i class="fas fa-ticket-alt text-black text-xl"></i>
                        </div>
                        <span class="text-2xl font-black tracking-tighter text-white">TICKETAPP</span>
                    </a>
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
                <button class="md:hidden text-gray-400 hover:text-white" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Drawer -->
        <div id="mobileMenu" class="fixed inset-0 z-[60] hidden md:hidden">
            <!-- Overlay -->
            <div class="absolute inset-0 bg-[#0A0E14]/95 backdrop-blur-2xl" onclick="toggleMobileMenu()"></div>
            
            <!-- Menu Content -->
            <nav class="relative h-full flex flex-col items-center justify-center gap-8 p-8">
                <button class="absolute top-8 right-8 text-gray-400 hover:text-white text-2xl" onclick="toggleMobileMenu()">
                    <i class="fas fa-times"></i>
                </button>
                
                <a href="index.php" class="text-3xl font-bold text-white hover:text-lime-400 transition" onclick="toggleMobileMenu()">Inicio</a>
                <a href="about.php" class="text-3xl font-bold text-white hover:text-lime-400 transition" onclick="toggleMobileMenu()">Nosotros</a>
                <a href="contact.php" class="text-3xl font-bold text-white hover:text-lime-400 transition" onclick="toggleMobileMenu()">Contacto</a>
                
                <div class="w-full h-px bg-white/10 my-4"></div>
                
                <a href="admin/" class="flex items-center gap-3 text-2xl font-bold text-gray-300 hover:text-white transition" onclick="toggleMobileMenu()">
                    <i class="fas fa-user-shield text-xl text-lime-400"></i>
                    Administración
                </a>
            </nav>
        </div>
    </header>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
            document.body.style.overflow = menu.classList.contains('hidden') ? 'auto' : 'hidden';
        }
    </script>

<?php if ($isAdmin): ?>
    <!-- Admin Toolbar -->
    <div class="sticky top-20 left-0 right-0 z-40 px-4 py-4 no-print flex justify-center bg-[#0A0E14]/50 backdrop-blur-md border-b border-white/5">
        <div class="bg-gray-900/90 backdrop-blur-xl border border-white/10 px-6 py-3 rounded-2xl shadow-2xl flex items-center gap-6">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-lime-400 animate-pulse"></div>
                <span class="text-[10px] font-black uppercase tracking-widest text-lime-400">Modo Administrador</span>
            </div>
            <div class="h-4 w-px bg-white/10"></div>
            <?php if ($ticket['status'] === 'valid'): ?>
                <form method="POST" class="m-0">
                    <input type="hidden" name="action" value="validate">
                    <button type="submit" class="bg-lime-400 text-black px-4 py-1.5 rounded-xl text-xs font-bold hover:scale-105 transition-all shadow-lg shadow-lime-400/20">
                        <i class="fas fa-check-circle mr-1"></i> Validar Entrada
                    </button>
                </form>
            <?php else: ?>
                <span class="text-xs font-bold text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i> Ticket ya procesado
                </span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

    <div class="w-full flex-1 flex flex-col items-center justify-center py-12 px-4">
        <div class="ticket-container">
            <?php if ($validationSuccess): ?>
                <div class="mb-6 animate-bounce">
                    <div class="bg-lime-400 text-black px-6 py-4 rounded-2xl flex items-center justify-center gap-3 shadow-2xl shadow-lime-400/30">
                        <i class="fas fa-check-double text-2xl"></i>
                        <span class="font-black uppercase tracking-tight text-lg">¡Ticket Validado!</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="mb-6">
                    <div class="bg-red-500 text-white px-6 py-4 rounded-2xl flex items-center justify-center gap-3 shadow-2xl">
                        <i class="fas fa-exclamation-triangle text-2xl"></i>
                        <span class="font-black uppercase tracking-tight text-lg"><?php echo $errorMsg; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ticket Card Container -->
            <div class="bg-white/5 border border-white/10 p-1.5 rounded-[2.5rem] shadow-2xl backdrop-blur-2xl">
                <div class="ticket-main-card shadow-2xl relative">
            <!-- Status Badge -->
            <div class="status-badge status-<?php echo $ticket['status']; ?>">
                <?php 
                echo $ticket['status'] === 'valid' ? 'VÁLIDO' : 
                     ($ticket['status'] === 'used' ? 'UTILIZADO' : 'CANCELADO');
                ?>
            </div>

            <!-- Image Section -->
            <?php if ($ticket['image_url']): ?>
                <img src="<?php echo SITE_URL . '/' . $ticket['image_url']; ?>" class="ticket-image" alt="">
            <?php else: ?>
                <div class="ticket-image bg-gray-200 flex items-center justify-center text-gray-400">
                     <i class="fas fa-ticket-alt text-5xl"></i>
                </div>
            <?php endif; ?>

            <div class="ticket-content">
                <h3 class="text-2xl font-bold text-center mb-6 leading-tight"><?php echo htmlspecialchars($ticket['event_title']); ?></h3>
                
                <div class="grid grid-cols-2 gap-y-6 gap-x-4 mb-2">
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">FECHA</p>
                        <p class="text-sm font-bold text-gray-800"><?php echo formatDate($ticket['date_event'], 'd M, Y'); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">HORA</p>
                        <p class="text-sm font-bold text-gray-800">19:30 PM</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">LUGAR</p>
                        <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($ticket['location']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">TIPO</p>
                        <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($ticket['ticket_type_name'] ?? 'General'); ?></p>
                    </div>
                </div>

                <div class="ticket-divider"></div>

                <div class="flex flex-col items-center">
                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-4">ASISTENTE</p>
                    <p class="text-lg font-bold text-gray-900 mb-6"><?php echo htmlspecialchars($ticket['attendee_name']); ?></p>
                    
                    <!-- QR Visual -->
                    <div class="p-2 border-2 border-dashed border-gray-200 rounded-2xl mb-4 bg-white">
                        <?php 
                        $qrWebPath = SITE_URL . '/qrcodes/' . basename($ticket['qr_code_path']);
                        ?>
                        <img src="<?php echo $qrWebPath; ?>" alt="QR" class="w-40 h-40 contrast-125">
                    </div>
                    <p class="text-xs font-mono text-gray-400 tracking-[0.2em] uppercase"><?php echo $ticket['ticket_code']; ?></p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col gap-2 p-6 pt-0 no-print">
                <button onclick="shareTicket()" class="btn-modern btn-lime w-full text-sm py-3">
                    <i class="fab fa-whatsapp mr-2"></i> Compartir por WhatsApp
                </button>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="btn-modern bg-gray-200 text-gray-800 flex-1 text-xs py-3">
                        <i class="fas fa-print mr-2"></i> Imprimir
                    </button>
                    <a href="index.php" class="btn-modern bg-gray-800 text-white flex-1 text-xs py-3 text-center">
                        <i class="fas fa-home mr-2"></i> Inicio
                    </a>
                </div>
            </div>
                </div> <!-- End ticket-main-card container -->
            </div>
            
            <p class="text-[10px] text-gray-500 text-center mt-12 uppercase tracking-widest font-bold no-print">
                <i class="fas fa-info-circle mr-1 text-lime-400/50"></i>
                Presenta este QR en la entrada del evento
            </p>
        </div>
        
        <div class="h-12 no-print"></div>
    </div>

    <!-- Footer (Non-printable) -->
    <footer class="bg-[#0A0E14] border-t border-white/5 py-12 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="flex items-center justify-center gap-2 mb-6">
                <i class="fas fa-ticket-alt text-lime-400"></i>
                <span class="text-xl font-bold text-white tracking-tighter text-white">TICKETAPP</span>
            </div>
            <p class="text-gray-500 text-sm mb-8">Tu entrada segura y digital.</p>
            <div class="pt-8 border-t border-white/5 text-gray-600 text-[10px] uppercase tracking-widest font-bold">
                &copy; <?php echo date('Y'); ?> TicketApp. Todos los derechos reservados.
            </div>
        </div>
    </footer>
    
    <script>
        function shareTicket() {
            const message = `🎫 Mi ticket para "${<?php echo json_encode($ticket['event_title']); ?>}"\n\n` +
                          `📅 ${<?php echo json_encode(formatDate($ticket['date_event'])); ?>}\n` +
                          `📍 ${<?php echo json_encode($ticket['location']); ?>}\n` +
                          `🎟️ Código: ${<?php echo json_encode($ticket['ticket_code']); ?>}\n\n` +
                          `Ver ticket: ${window.location.href}`;
            window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
        }
    </script>
</body>
</html>
