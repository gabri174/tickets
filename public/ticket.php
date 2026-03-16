<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

$db = new Database();

// Obtener ticket por código
if (!isset($_GET['code']) || empty($_GET['code'])) {
    header('HTTP/1.0 404 Not Found');
    echo '<h1>Ticket no encontrado</h1>';
    exit();
}

$ticketCode = cleanInput($_GET['code']);
$ticket = $db->getTicketByCode($ticketCode);

if (!$ticket) {
    header('HTTP/1.0 404 Not Found');
    echo '<h1>Ticket no encontrado</h1>';
    exit();
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
            background: linear-gradient(180deg, #0A0E14 0%, #171E26 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
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
    </style>
</head>
<body>
    <div class="app-container min-h-screen flex flex-col">
        <!-- Header (Non-printable) -->
        <header class="flex justify-between items-center mb-12 pt-6 no-print">
            <div class="flex items-center gap-2">
                <i class="fas fa-ticket-alt text-2xl text-lime-400"></i>
                <h1 class="text-2xl font-bold tracking-tighter text-white">TICKETAPP</h1>
            </div>
            <nav class="hidden md:flex gap-6">
                <a href="index.php" class="text-sm font-medium text-gray-400 hover:text-lime-400 transition">Inicio</a>
                <a href="#" class="text-sm font-medium text-gray-400 hover:text-white transition">Ayuda</a>
            </nav>
            <a href="index.php" class="glass-pill w-12 h-12 flex items-center justify-center text-lg hover:bg-white/10 transition text-white">
                <i class="fas fa-times"></i>
            </a>
        </header>

        <div class="flex-1 flex flex-col items-center justify-center py-10">
            <div class="w-full max-w-[420px]">
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
                        <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">ACCESO</p>
                        <p class="text-sm font-bold text-gray-800">General</p>
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
        </div>
        
        <p class="text-[10px] text-gray-500 text-center mt-6 uppercase tracking-widest">Presenta este QR en la entrada del evento</p>
    </div>
    
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
