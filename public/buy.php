<?php
session_start();
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

$db = new Database();

// Obtener evento
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$eventId = cleanInput($_GET['id']);
$event = $db->getEventById($eventId);

if (!$event) {
    header('Location: index.php');
    exit();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanInput($_POST['name']);
    $email = cleanInput($_POST['email']);
    $phone = cleanInput($_POST['phone']);
    $quantity = (int) cleanInput($_POST['quantity']);
    
    // Validaciones
    $errors = [];
    
    if (empty($name)) $errors[] = 'El nombre es requerido';
    if (empty($email) || !validateEmail($email)) $errors[] = 'Email inválido';
    if (empty($phone)) $errors[] = 'El teléfono es requerido';
    if ($quantity < 1 || $quantity > 5) $errors[] = 'Cantidad inválida (máximo 5 tickets)';
    if ($quantity > $event['available_tickets']) $errors[] = 'No hay suficientes tickets disponibles';
    
    if (empty($errors)) {
        // Lógica de Pago
        $paymentSuccess = true;
        
        if ($event['price'] > 0) {
            // Aquí iría la integración de pasarela de pago (Stripe, PayPal, etc.)
            // Por ahora asuminos éxito ya que no hay una pasarela configurada.
            // Si el precio es > 0, se ejecutaría la lógica de cobro.
            $paymentSuccess = true; 
        } else {
            // Si el precio es 0, saltamos directamente a la generación de tickets
            $paymentSuccess = true;
        }

        if ($paymentSuccess) {
            // Generar tickets
            $tickets = [];
        $totalPrice = $event['price'] * $quantity;
        
        try {
            $pdo->beginTransaction();
            
            for ($i = 0; $i < $quantity; $i++) {
                $ticketCode = generateTicketCode();
                $qrFilename = $ticketCode;
                $qrData = SITE_URL . "/ticket.php?code=" . $ticketCode;
                $qrPath = generateQRCode($qrData, $qrFilename);
                
                $db->createTicket($eventId, $ticketCode, $name, $email, $phone, $qrPath);
                $tickets[] = [
                    'code' => $ticketCode,
                    'qr_path' => $qrPath
                ];
            }
            
            // Actualizar tickets disponibles
            $db->updateAvailableTickets($eventId, $quantity);
            
            $pdo->commit();
            
            // Enviar email
            $subject = "Tus tickets para " . $event['title'];
            $emailBody = generateEmailBody($event, $tickets, $name, $totalPrice);
            
            // Generar PDF y adjuntar
            $pdfPath = generateTicketPDF($event, $tickets, $name, $totalPrice);
            sendTicketEmail($email, $subject, $emailBody, $pdfPath);
            
            // Redirigir a página de confirmación
            $_SESSION['purchase_success'] = [
                'event_title' => $event['title'],
                'tickets' => $tickets,
                'total_price' => $totalPrice,
                'email' => $email
            ];
            
            header('Location: success.php');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error al procesar la compra. Por favor intenta nuevamente.';
        }
        }
    }
}

function generateEmailBody($event, $tickets, $name, $totalPrice) {
    $body = "<h2>¡Gracias por tu compra, $name!</h2>";
    $body .= "<h3>Evento: {$event['title']}</h3>";
    $body .= "<p><strong>Fecha:</strong> " . formatDate($event['date_event']) . "</p>";
    $body .= "<p><strong>Lugar:</strong> {$event['location']}</p>";
    $body .= "<p><strong>Tickets comprados:</strong> " . count($tickets) . "</p>";
    $body .= "<p><strong>Total pagado:</strong> " . formatCurrency($totalPrice) . "</p>";
    $body .= "<h4>Tus códigos de ticket:</h4><ul>";
    
    foreach ($tickets as $ticket) {
        $body .= "<li><strong>{$ticket['code']}</strong></li>";
    }
    
    $body .= "</ul>";
    $body .= "<p>Presenta estos códigos en la entrada del evento.</p>";
    
    return $body;
}


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar Tickets - <?php echo htmlspecialchars($event['title']); ?></title>
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
        
        body { background-color: var(--color-gray-light); }
        .bg-primary { background-color: var(--color-gray-dark); }
        .text-primary { color: var(--color-gray-dark); }
        .btn-primary { background-color: var(--color-gray-dark); }
        .btn-primary:hover { background-color: var(--color-black); }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="bg-primary text-white shadow-lg">
        <nav class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-ticket-alt text-2xl"></i>
                    <h1 class="text-2xl font-bold">Tickets</h1>
                </div>
                <a href="index.php" class="hover:text-gray-300 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Volver
                </a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Event Info -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <div class="flex flex-col md:flex-row gap-6">
                    <?php if ($event['image_url']): ?>
                        <img src="<?php echo SITE_URL . '/' . $event['image_url']; ?>" 
                             alt="<?php echo htmlspecialchars($event['title']); ?>" 
                             class="w-full md:w-1/3 h-48 object-cover rounded-lg">
                    <?php endif; ?>
                    
                    <div class="flex-1">
                        <h2 class="text-3xl font-bold mb-4 text-primary"><?php echo htmlspecialchars($event['title']); ?></h2>
                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($event['description']); ?></p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt text-primary mr-3"></i>
                                <span><?php echo formatDate($event['date_event']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-map-marker-alt text-primary mr-3"></i>
                                <span><?php echo htmlspecialchars($event['location']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-ticket-alt text-primary mr-3"></i>
                                <span><?php echo $event['available_tickets']; ?> disponibles</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-tag text-primary mr-3"></i>
                                <span class="text-2xl font-bold"><?php echo formatCurrency($event['price']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Purchase Form -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-2xl font-bold mb-6 text-primary">Completar Compra</h3>
                
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <ul class="list-disc list-inside">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="purchaseForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-user mr-2"></i>Nombre Completo *
                            </label>
                            <input type="text" name="name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-envelope mr-2"></i>Email *
                            </label>
                            <input type="email" name="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-phone mr-2"></i>Teléfono *
                            </label>
                            <input type="tel" name="phone" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-ticket-alt mr-2"></i>Cantidad de Tickets *
                            </label>
                            <select name="quantity" required id="quantity"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                                <?php 
                                $maxQuantity = min(5, $event['available_tickets']);
                                for ($i = 1; $i <= $maxQuantity; $i++): 
                                ?>
                                    <option value="<?php echo $i; ?>" <?php echo (isset($_POST['quantity']) && $_POST['quantity'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> ticket<?php echo $i > 1 ? 's' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Price Summary -->
                    <div class="mt-6 p-4 bg-gray-100 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-semibold">Total a pagar:</span>
                            <span class="text-2xl font-bold text-primary" id="totalPrice">
                                <?php echo formatCurrency($event['price']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            <i class="fas <?php echo $event['price'] > 0 ? 'fa-credit-card' : 'fa-ticket-alt'; ?> mr-2"></i>
                            <?php echo $event['price'] > 0 ? 'Completar Compra' : 'Obtener Tickets Gratis'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Actualizar precio total
        const quantitySelect = document.getElementById('quantity');
        const totalPriceElement = document.getElementById('totalPrice');
        const basePrice = <?php echo $event['price']; ?>;
        
        quantitySelect.addEventListener('change', function() {
            const quantity = parseInt(this.value);
            const total = basePrice * quantity;
            totalPriceElement.textContent = '$' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        });
    </script>
</body>
</html>
