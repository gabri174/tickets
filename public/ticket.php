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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-gray-light: #d9d9d9;
            --color-gray-dark: #363c40;
            --color-gray-medium: #babebf;
            --color-gray-muted: #848b8c;
            --color-black: #202426;
        }
        
        body { 
            background-color: var(--color-gray-light);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .ticket-container {
            background: white;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .ticket-header {
            background: var(--color-gray-dark);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .ticket-body {
            padding: 30px;
        }
        
        .ticket-code {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .status-valid {
            background-color: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-used {
            background-color: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        @media print {
            body { background: white; }
            .no-print { display: none; }
            .ticket-container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="ticket-container">
        <!-- Ticket Header -->
        <div class="ticket-header">
            <div class="flex justify-between items-center mb-4">
                <i class="fas fa-ticket-alt text-3xl"></i>
                <span class="status-<?php echo $ticket['status']; ?>">
                    <?php 
                    echo $ticket['status'] === 'valid' ? 'VÁLIDO' : 
                         ($ticket['status'] === 'used' ? 'UTILIZADO' : 'CANCELADO');
                    ?>
                </span>
            </div>
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($ticket['event_title']); ?></h1>
        </div>
        
        <!-- Ticket Body -->
        <div class="ticket-body">
            <!-- Event Details -->
            <div class="mb-6">
                <div class="flex items-center mb-3">
                    <i class="fas fa-calendar-alt text-gray-600 mr-3 w-5"></i>
                    <span><?php echo formatDate($ticket['date_event']); ?></span>
                </div>
                <div class="flex items-center mb-3">
                    <i class="fas fa-map-marker-alt text-gray-600 mr-3 w-5"></i>
                    <span><?php echo htmlspecialchars($ticket['location']); ?></span>
                </div>
                <div class="flex items-center mb-3">
                    <i class="fas fa-user text-gray-600 mr-3 w-5"></i>
                    <span><?php echo htmlspecialchars($ticket['attendee_name']); ?></span>
                </div>
            </div>
            
            <!-- QR Code -->
            <div class="text-center mb-6">
                <?php if ($ticket['qr_code_path'] && file_exists($ticket['qr_code_path'])): ?>
                    <img src="<?php echo SITE_URL . '/qrcodes/' . basename($ticket['qr_code_path']); ?>" 
                         alt="QR Code" 
                         class="mx-auto w-32 h-32">
                <?php else: ?>
                    <div class="w-32 h-32 bg-gray-200 mx-auto flex items-center justify-center">
                        <i class="fas fa-qrcode text-4xl text-gray-400"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Ticket Code -->
            <div class="bg-gray-100 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-600 mb-2">Código del Ticket:</p>
                <p class="ticket-code text-center"><?php echo htmlspecialchars($ticket['ticket_code']); ?></p>
            </div>
            
            <!-- Purchase Info -->
            <div class="text-sm text-gray-500 text-center">
                <p>Compra realizada: <?php echo formatDate($ticket['purchase_date']); ?></p>
            </div>
        </div>
        
        <!-- Print Button (no visible in print) -->
        <div class="no-print p-4 border-t">
            <button onclick="window.print()" class="w-full bg-gray-800 text-white py-2 rounded hover:bg-gray-700 transition">
                <i class="fas fa-print mr-2"></i>Imprimir Ticket
            </button>
        </div>
    </div>
    
    <!-- Mobile Actions (no visible in print) -->
    <div class="no-print fixed bottom-4 right-4 flex flex-col space-y-2">
        <button onclick="shareTicket()" class="bg-green-500 text-white p-3 rounded-full shadow-lg hover:bg-green-600 transition">
            <i class="fab fa-whatsapp text-xl"></i>
        </button>
        <button onclick="copyCode()" class="bg-blue-500 text-white p-3 rounded-full shadow-lg hover:bg-blue-600 transition">
            <i class="fas fa-copy text-xl"></i>
        </button>
    </div>
    
    <script>
        // Compartir ticket por WhatsApp
        function shareTicket() {
            const message = `🎫 Mi ticket para "${<?php echo json_encode($ticket['event_title']); ?>}"\n\n` +
                          `📅 ${<?php echo json_encode(formatDate($ticket['date_event'])); ?>}\n` +
                          `📍 ${<?php echo json_encode($ticket['location']); ?>}\n` +
                          `🎟️ Código: ${<?php echo json_encode($ticket['ticket_code']); ?>}\n\n` +
                          `¡Nos vemos allá! 🎉`;
            
            const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
        }
        
        // Copiar código al portapapeles
        function copyCode() {
            const code = '<?php echo $ticket['ticket_code']; ?>';
            navigator.clipboard.writeText(code).then(function() {
                showNotification('¡Código copiado!');
            });
        }
        
        // Mostrar notificación
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 2000);
        }
        
        // Prevenir zoom en móvil
        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        });
    </script>
</body>
</html>
