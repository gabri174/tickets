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
    $quantity = (int) cleanInput($_POST['quantity']);
    $attendees = isset($_POST['attendees']) ? $_POST['attendees'] : [];
    $phone = cleanInput($_POST['phone']);
    
    // Validaciones
    $errors = [];
    
    if (empty($phone)) $errors[] = 'El teléfono de contacto es requerido';
    if ($quantity < 1 || $quantity > $event['available_tickets']) $errors[] = 'Cantidad de tickets inválida';
    
    // Validar cada asistente
    if (count($attendees) !== $quantity) {
        $errors[] = 'Debes completar los datos de todos los asistentes';
    } else {
        foreach ($attendees as $index => $attendee) {
            $num = $index + 1;
            if (empty(cleanInput($attendee['name']))) $errors[] = "El nombre del asistente $num es requerido";
            if (empty(cleanInput($attendee['surname']))) $errors[] = "Los apellidos del asistente $num son requeridos";
            if (empty(cleanInput($attendee['email'])) || !validateEmail($attendee['email'])) {
                $errors[] = "El email del asistente $num es inválido";
            }
        }
    }
    
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
            $pdo = $db->getPdo();
            $pdo->beginTransaction();
            
            $primary_email = cleanInput($attendees[0]['email']); // Usamos el primer email como principal para el envío
            $primary_name = cleanInput($attendees[0]['name']) . ' ' . cleanInput($attendees[0]['surname']);

            foreach ($attendees as $attendee) {
                $a_name = cleanInput($attendee['name']) . ' ' . cleanInput($attendee['surname']);
                $a_email = cleanInput($attendee['email']);
                
                $ticketCode = generateTicketCode();
                $qrFilename = $ticketCode;
                $qrData = SITE_URL . "/ticket.php?code=" . $ticketCode;
                $qrPath = generateQRCode($qrData, $qrFilename);
                
                $db->createTicket($eventId, $ticketCode, $a_name, $a_email, $phone, $qrPath);
                $tickets[] = [
                    'code' => $ticketCode,
                    'qr_path' => $qrPath,
                    'name' => $a_name,
                    'email' => $a_email
                ];
            }
            
            // Actualizar tickets disponibles
            $db->updateAvailableTickets($eventId, $quantity);
            
            $pdo->commit();
            
            // Enviar email
            $subject = "Tus tickets para " . $event['title'];
            $emailBody = generateEmailBody($event, $tickets, $primary_name, $totalPrice);
            
            // Generar PDF y adjuntar
            $pdfPath = generateTicketPDF($event, $tickets, $totalPrice);
            try {
                sendTicketEmail($primary_email, $subject, $emailBody, $pdfPath);
            } catch (Exception $mailEx) {
                $_SESSION['email_error'] = "Error al enviar el correo: " . $mailEx->getMessage();
            }
            
            // Redirigir a página de confirmación
            $_SESSION['purchase_success'] = [
                'event_title' => $event['title'],
                'tickets' => $tickets,
                'total_price' => $totalPrice,
                'email' => $primary_email,
                'phone' => $phone
            ];
            
            header('Location: success.php');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error al procesar la compra: ' . $e->getMessage();
        }
        }
    }
}

function generateEmailBody($event, $tickets, $name, $totalPrice) {
    $date = formatDate($event['date_event'], 'd/m/Y H:i');
    $quantity = count($tickets);
    $price = formatCurrency($totalPrice);
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; color: #333;'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <h1 style='color: #202426; margin-bottom: 5px;'>¡Hola, $name!</h1>
            <p style='font-size: 16px; color: #666; margin-top: 0;'>Aquí tienes tus tickets para tu próximo evento.</p>
        </div>
        
        <div style='background-color: #f9f9f9; padding: 25px; border-radius: 10px; margin-bottom: 30px;'>
            <h2 style='margin-top: 0; color: #202426; border-bottom: 2px solid #ebcf94; padding-bottom: 10px; display: inline-block;'>{$event['title']}</h2>
            <div style='margin-top: 15px;'>
                <p><strong>📅 Fecha:</strong> $date</p>
                <p><strong>📍 Lugar:</strong> {$event['location']}</p>
                <p><strong>🎟️ Cantidad:</strong> $quantity ticket(s)</p>
                <p><strong>💰 Total pagado:</strong> $price</p>
            </div>
        </div>
        
        <div style='text-align: center; padding: 20px; background-color: #ebcf94; border-radius: 8px; color: #202426;'>
            <p style='margin: 0; font-weight: bold; font-size: 18px;'>📄 Tus tickets están adjuntos en formato PDF</p>
            <p style='margin: 10px 0 0 0; font-size: 14px;'>Por favor, descarga el archivo adjunto y preséntalo en la entrada.</p>
        </div>
        
        <div style='margin-top: 40px; text-align: center; font-size: 12px; color: #999;'>
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>&copy; " . date('Y') . " Tickets - En Su Presencia</p>
        </div>
    </div>";
    
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
                    <div class="mb-6">
                        <label class="block text-gray-700 font-semibold mb-2">
                            <i class="fas fa-ticket-alt mr-2"></i>Cantidad de Tickets *
                        </label>
                        <input type="number" name="quantity" id="quantity" 
                               min="1" max="<?php echo $event['available_tickets']; ?>" 
                               value="<?php echo isset($_POST['quantity']) ? $_POST['quantity'] : '1'; ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    </div>

                    <div id="attendeesContainer" class="space-y-6">
                        <!-- Los campos de los asistentes se generarán aquí con JS -->
                    </div>

                    <div class="mt-8 border-t pt-6">
                        <label class="block text-gray-700 font-semibold mb-2">
                            <i class="fas fa-phone mr-2"></i>Teléfono de contacto (para avisos) *
                        </label>
                        <input type="tel" name="phone" required
                               placeholder="Ej: +34 600 000 000"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
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
        const quantityInput = document.getElementById('quantity');
        const attendeesContainer = document.getElementById('attendeesContainer');
        const totalPriceElement = document.getElementById('totalPrice');
        const basePrice = <?php echo $event['price']; ?>;
        
        function updateAttendeeFields() {
            const quantity = parseInt(quantityInput.value) || 0;
            const currentFields = attendeesContainer.children.length;
            
            if (quantity > currentFields) {
                // Añadir campos
                for (let i = currentFields; i < quantity; i++) {
                    const attendeeDiv = document.createElement('div');
                    attendeeDiv.className = 'attendee-block bg-gray-50 p-4 rounded-lg border border-gray-200';
                    attendeeDiv.innerHTML = `
                        <h4 class="font-bold text-gray-800 mb-4 border-b pb-2 flex items-center">
                            <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-xs mr-2">${i + 1}</span>
                            Datos del Asistente ${i + 1}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs text-gray-600 font-semibold mb-1">Nombre *</label>
                                <input type="text" name="attendees[${i}][name]" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 font-semibold mb-1">Apellidos *</label>
                                <input type="text" name="attendees[${i}][surname]" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 font-semibold mb-1">Email *</label>
                                <input type="email" name="attendees[${i}][email]" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-primary text-sm">
                            </div>
                        </div>
                    `;
                    attendeesContainer.appendChild(attendeeDiv);
                }
            } else if (quantity < currentFields) {
                // Quitar campos
                for (let i = currentFields; i > quantity; i--) {
                    attendeesContainer.lastElementChild.remove();
                }
            }
            
            // Actualizar precio
            const total = basePrice * quantity;
            totalPriceElement.textContent = '$' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        quantityInput.addEventListener('input', updateAttendeeFields);
        
        // Inicializar campos
        document.addEventListener('DOMContentLoaded', updateAttendeeFields);
    </script>
</body>
</html>
