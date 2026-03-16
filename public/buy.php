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
                'event_id' => $eventId,
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
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        input[type="number"], input[type="text"], input[type="email"], input[type="tel"] {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            outline: none;
            transition: border-color 0.3s ease;
        }
        input:focus { border-color: var(--accent-blue); }
        label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #A1A1A1; }
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
                    <a href="#" class="text-sm font-semibold text-gray-400 hover:text-white transition">Eventos</a>
                    <a href="#" class="text-sm font-semibold text-gray-400 hover:text-white transition">Mis Tickets</a>
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
            <a href="index.php" class="glass-pill w-12 h-12 flex items-center justify-center text-lg hover:bg-white/10 transition">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h2 class="text-2xl font-bold">Reserva de Entradas</h2>
            <div class="hidden md:flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-lime-400"></span>
                <span class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">Reserva Segura</span>
            </div>
            <div class="w-12 md:hidden"></div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 items-start">
            <!-- Sidebar: Event Summary -->
            <div class="lg:col-span-1 lg:sticky lg:top-6">
                <div class="glass-card overflow-hidden">
                    <div class="h-48 w-full overflow-hidden">
                        <?php if ($event['image_url']): ?>
                            <img src="<?php echo SITE_URL . '/' . $event['image_url']; ?>" alt="" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full bg-gray-800 flex items-center justify-center text-3xl">
                                <i class="fas fa-image text-gray-700"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        <h3 class="font-bold text-xl mb-4"><?php echo htmlspecialchars($event['title']); ?></h3>
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center gap-3 text-sm text-gray-400">
                                <i class="fas fa-calendar-alt text-blue-400 w-5"></i>
                                <span><?php echo formatDate($event['date_event'], 'l, d F Y'); ?></span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-gray-400">
                                <i class="fas fa-clock text-blue-400 w-5"></i>
                                <span>19:30 PM</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-gray-400">
                                <i class="fas fa-map-marker-alt text-red-400 w-5"></i>
                                <span><?php echo htmlspecialchars($event['location']); ?></span>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-white/10">
                            <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest mb-1">PRECIO UNITARIO</p>
                            <span class="text-lime-400 font-bold text-2xl"><?php echo formatCurrency($event['price']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main: Form -->
            <div class="lg:col-span-2">

        <!-- Form -->
        <form method="POST" action="" id="purchaseForm" class="space-y-6">
            <?php if (!empty($errors)): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl text-sm mb-6">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Quantity -->
            <div class="glass-card p-6">
                <label for="quantity"><i class="fas fa-ticket-alt mr-2"></i>¿Cuántos tickets necesitas?</label>
                <input type="number" name="quantity" id="quantity" 
                       min="1" max="<?php echo $event['available_tickets']; ?>" 
                       value="<?php echo isset($_POST['quantity']) ? $_POST['quantity'] : '1'; ?>">
                <p class="text-[10px] text-gray-500 mt-2">Disponibles: <?php echo $event['available_tickets']; ?></p>
            </div>

            <div id="attendeesContainer" class="space-y-4">
                <!-- Los campos de los asistentes se generarán aquí con JS -->
            </div>

            <!-- Contact -->
            <div class="glass-card p-6">
                <label><i class="fas fa-phone mr-2"></i>Móvil de contacto</label>
                <input type="tel" name="phone" required placeholder="Ej: +34 600 000 000"
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                <p class="text-[10px] text-gray-500 mt-2">Usaremos este número para enviarte confirmaciones por WhatsApp.</p>
            </div>

            <!-- Total and Submit -->
            <div class="pt-8">
                <div class="glass-card p-8 bg-gradient-to-br from-white/5 to-transparent">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <p class="text-gray-400 font-medium">Total a pagar</p>
                            <p class="text-[10px] text-gray-500">Incluye impuestos y cargos de servicio</p>
                        </div>
                        <span class="text-4xl font-black text-white" id="totalPrice"><?php echo formatCurrency($event['price']); ?></span>
                    </div>

                    <button type="submit" class="btn-modern btn-lime w-full text-lg py-5 shadow-lg shadow-lime-400/10">
                        <i class="fas <?php echo $event['price'] > 0 ? 'fa-credit-card' : 'fa-check-circle'; ?> mr-3"></i>
                        <?php echo $event['price'] > 0 ? 'Proceder al Pago' : 'Confirmar mi Plaza'; ?>
                    </button>
                    
                    <p class="text-center text-[10px] text-gray-500 mt-6 flex items-center justify-center gap-2">
                        <i class="fas fa-lock text-lime-400/50"></i>
                        Tus datos están protegidos bajo cifrado de 256 bits
                    </p>
                </div>
            </div>
        </form>
    </div> <!-- End lg:col-span-2 -->
</div> <!-- End grid -->

<div class="h-20"></div>

    <!-- Footer -->
    <footer class="bg-[#0A0E14] border-t border-white/5 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="flex items-center justify-center gap-2 mb-6">
                <i class="fas fa-ticket-alt text-lime-400"></i>
                <span class="text-xl font-bold text-white tracking-tighter">TICKETAPP</span>
            </div>
            <p class="text-gray-500 text-sm mb-8">La plataforma líder para tus entradas digitales.</p>
            <div class="pt-8 border-t border-white/5 text-gray-600 text-[10px] uppercase tracking-widest font-bold">
                &copy; <?php echo date('Y'); ?> TicketApp. Todos los derechos reservados.
            </div>
        </div>
    </footer>

    <script>
        const quantityInput = document.getElementById('quantity');
        const attendeesContainer = document.getElementById('attendeesContainer');
        const totalPriceElement = document.getElementById('totalPrice');
        const basePrice = <?php echo $event['price']; ?>;
        
        function updateAttendeeFields() {
            const quantity = parseInt(quantityInput.value) || 0;
            const currentFields = attendeesContainer.children.length;
            
            if (quantity > currentFields) {
                for (let i = currentFields; i < quantity; i++) {
                    const attendeeDiv = document.createElement('div');
                    attendeeDiv.className = 'glass-card p-5 animate-slide-up';
                    attendeeDiv.innerHTML = `
                        <div class="flex items-center gap-3 mb-4">
                            <span class="w-8 h-8 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center text-xs font-bold">${i + 1}</span>
                            <h4 class="font-bold text-sm">Asistente ${i + 1}</h4>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs">Nombre</label>
                                <input type="text" name="attendees[${i}][name]" required>
                            </div>
                            <div>
                                <label class="text-xs">Apellidos</label>
                                <input type="text" name="attendees[${i}][surname]" required>
                            </div>
                            <div>
                                <label class="text-xs">Email</label>
                                <input type="email" name="attendees[${i}][email]" required>
                            </div>
                        </div>
                    `;
                    attendeesContainer.appendChild(attendeeDiv);
                }
            } else if (quantity < currentFields) {
                for (let i = currentFields; i > quantity; i--) {
                    attendeesContainer.lastElementChild.remove();
                }
            }
            
            // Actualizar precio
            const total = basePrice * quantity;
            totalPriceElement.textContent = '$' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        quantityInput.addEventListener('input', updateAttendeeFields);
        document.addEventListener('DOMContentLoaded', updateAttendeeFields);
    </script>

    <style>
        .animate-slide-up {
            animation: slideUp 0.4s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</body>
</html>
