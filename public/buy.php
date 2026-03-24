<?php
session_start();
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/PaymentGateway.php';
require_once '../includes/classes/FinassetsGateway.php';

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

// Analytics: Capturar Referido (ref) y Tracking de Visita
if (!isset($_SESSION['referral']) && isset($_GET['ref'])) {
    $_SESSION['referral'] = cleanInput($_GET['ref']);
}

// Registrar visita (funnel de conversión)
try {
    $sessionId = session_id();
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR']);
    $db->trackVisit($eventId, $sessionId, $ipHash);
} catch (Throwable $e) {
    // Si falla el tracking (ej. tabla no existe), no bloqueamos la compra
    error_log("Error in trackVisit: " . $e->getMessage());
}

$ticketTypes = $db->getTicketTypesByEvent($eventId);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = (int) cleanInput($_POST['quantity']);
    $attendees = isset($_POST['attendees']) ? $_POST['attendees'] : [];
    $phone = cleanInput($_POST['phone']);
    $zipCode = cleanInput($_POST['zip_code']);
    
    // Validaciones
    $errors = [];
    
    if (empty($phone)) $errors[] = 'El teléfono de contacto es requerido';
    if (empty($zipCode)) $errors[] = 'El código postal es requerido para la analítica del evento';
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
        // Obtener precio y validar tipo de entrada
        $ticketTypeId = isset($_POST['ticket_type_id']) ? (int) $_POST['ticket_type_id'] : null;
        $unitPrice = $event['price'];
        $ticketTypeName = '';

        if (!empty($ticketTypes)) {
            if (!$ticketTypeId) {
                $errors[] = 'Debes seleccionar un tipo de entrada';
            } else {
                $selectedType = null;
                foreach ($ticketTypes as $tt) {
                    if ($tt['id'] == $ticketTypeId) {
                        $selectedType = $tt;
                        break;
                    }
                }
                if (!$selectedType) {
                    $errors[] = 'Tipo de entrada inválido';
                } elseif ($selectedType['available_tickets'] < $quantity) {
                    $errors[] = 'No hay suficientes entradas disponibles de este tipo';
                } else {
                    $unitPrice = $selectedType['price'];
                    $ticketTypeName = $selectedType['name'];
                }
            }
        }

        if (empty($errors)) {
            $totalPrice = $unitPrice * $quantity;
        try {
            $pdo = $db->getPdo();
            
            // Obtener configuración de pago del organizador
            $stmt = $pdo->prepare("SELECT preferred_payment_method, payment_config FROM admins WHERE id = ?");
            $stmt->execute([$event['admin_id']]);
            $admin = $stmt->fetch();
            
            $paymentMethod = $admin['preferred_payment_method'] ?? 'none';
            $paymentConfig = json_decode($admin['payment_config'] ?? '{}', true);

            // Si el precio es 0, omitimos el pago
            if ($totalPrice <= 0) {
                $paymentMethod = 'none';
            }

            if ($paymentMethod === 'none') {
                $result = completePurchase([
                    'event_id' => $eventId,
                    'ticket_type_id' => $ticketTypeId,
                    'quantity' => $quantity,
                    'attendees' => $attendees,
                    'phone' => $phone,
                    'zip_code' => $zipCode,
                    'total_price' => $totalPrice
                ], $db);
                
                $_SESSION['purchase_success'] = $result;
                
                header('Location: success.php');
                exit();
            } elseif ($paymentMethod === 'finassets') {
                // Forzar el uso de la configuración del cliente. Si no existe, lanzar error.
                if (empty($paymentConfig)) {
                    throw new Exception('El organizador no ha configurado las credenciales de pago para este evento.');
                }

                $gateway = new FinassetsGateway($paymentConfig);
                
                // Guardamos datos temporales en la sesión para completar la compra tras el pago
                $_SESSION['pending_purchase'] = [
                    'event_id' => $eventId,
                    'ticket_type_id' => $ticketTypeId,
                    'quantity' => $quantity,
                    'attendees' => $attendees,
                    'phone' => $phone,
                    'zip_code' => $zipCode,
                    'total_price' => $totalPrice
                ];

                $cancelUrl = SITE_URL . "/buy.php?id=" . $eventId . "&error=payment_cancelled";
                $successUrl = SITE_URL . "/callback_finassets.php";
                
                $paymentUrl = $gateway->createPaymentRequest($totalPrice, "Entradas para " . $event['title'], $cancelUrl, $successUrl);
                
                header('Location: ' . $paymentUrl);
                exit();
            } else {
                $errors[] = 'El método de pago configurado (' . $paymentMethod . ') aún no está plenamente integrado. Por favor, contacta con el organizador.';
            }
            
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Error al procesar la compra: ' . $e->getMessage();
        }
        }
    }
}


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['seo_title'] ?: $event['title'] . ' - TicketApp'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($event['seo_description'] ?: 'Compra tus entradas para ' . $event['title'] . ' en ' . $event['location']); ?>">
    <?php if ($event['seo_keywords']): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($event['seo_keywords']); ?>">
    <?php endif; ?>
    
    <!-- Open Graph / WhatsApp / Facebook -->
    <meta property="og:type" content="event">
    <meta property="og:url" content="<?php echo SITE_URL . '/buy.php?id=' . $eventId; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($event['seo_title'] ?: $event['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($event['seo_description'] ?: 'Compra tus entradas para ' . $event['title'] . ' en ' . $event['location']); ?>">
    <meta property="og:image" content="<?php echo SITE_URL . '/' . ($event['image_url'] ?: 'assets/img/default-event.jpg'); ?>">
    <meta property="og:site_name" content="TicketApp">
    
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
            if (!menu.classList.contains('hidden')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        }
    </script>

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
                        <div class="pt-4 border-t border-white/10" id="priceDisplayBox">
                            <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest mb-1">PRECIO UNITARIO</p>
                            <span class="text-lime-400 font-bold text-2xl" id="unitPriceDisplay"><?php echo formatCurrency($event['price']); ?></span>
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

            <!-- Ticket Type Selection -->
            <?php if (!empty($ticketTypes)): ?>
            <div class="glass-card p-6">
                <label class="mb-4"><i class="fas fa-layer-group mr-2"></i>Selecciona tu tipo de entrada</label>
                <div class="grid grid-cols-1 gap-3">
                    <?php foreach ($ticketTypes as $index => $type): ?>
                        <label class="relative flex items-center p-4 rounded-2xl border border-white/10 bg-white/5 cursor-pointer hover:bg-white/10 transition-all group">
                            <input type="radio" name="ticket_type_id" value="<?php echo $type['id']; ?>" 
                                   data-price="<?php echo $type['price']; ?>" 
                                   data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                   class="ticket-type-radio hidden" 
                                   <?php echo $index === 0 ? 'checked' : ''; ?> required>
                            <div class="w-5 h-5 rounded-full border-2 border-white/20 mr-4 flex items-center justify-center group-hover:border-lime-400/50 transition-colors radio-custom">
                                <div class="w-2.5 h-2.5 rounded-full bg-lime-400 scale-0 transition-transform radio-dot"></div>
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-sm"><?php echo htmlspecialchars($type['name']); ?></p>
                                <?php if ($type['description']): ?>
                                    <p class="text-[10px] text-gray-500"><?php echo htmlspecialchars($type['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-lime-400"><?php echo formatCurrency($type['price']); ?></p>
                                <p class="text-[9px] text-gray-500 uppercase"><?php echo $type['available_tickets']; ?> disponibles</p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quantity -->
            <div class="glass-card p-6">
                <label for="quantity"><i class="fas fa-ticket-alt mr-2"></i>¿Cuántos tickets necesitas?</label>
                <input type="number" name="quantity" id="quantity" 
                       min="1" max="<?php echo $event['available_tickets']; ?>" 
                       value="<?php echo isset($_POST['quantity']) ? $_POST['quantity'] : '1'; ?>">
                <p class="text-[10px] text-gray-500 mt-2">Capacidad máxima del evento: <?php echo $event['available_tickets']; ?></p>
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

            <div class="glass-card p-6">
                <label><i class="fas fa-map-marker-alt mr-2"></i>Código Postal (CP)</label>
                <input type="text" name="zip_code" required placeholder="Ej: 28001"
                       value="<?php echo isset($_POST['zip_code']) ? htmlspecialchars($_POST['zip_code']) : ''; ?>">
                <p class="text-[10px] text-gray-500 mt-2">Necesario para las analíticas de geolocalización del evento.</p>
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
        const unitPriceDisplay = document.getElementById('unitPriceDisplay');
        const typeRadios = document.querySelectorAll('.ticket-type-radio');
        
        let currentUnitPrice = <?php echo $event['price']; ?>;
        
        function updatePrices() {
            const selectedRadio = document.querySelector('.ticket-type-radio:checked');
            if (selectedRadio) {
                currentUnitPrice = parseFloat(selectedRadio.dataset.price);
                unitPriceDisplay.textContent = formatCurrency(currentUnitPrice);
                
                // Update Radio UI
                document.querySelectorAll('.radio-dot').forEach(dot => dot.classList.add('scale-0'));
                selectedRadio.closest('label').querySelector('.radio-dot').classList.remove('scale-0');
                document.querySelectorAll('.radio-custom').forEach(rd => rd.classList.remove('border-lime-400'));
                selectedRadio.closest('label').querySelector('.radio-custom').classList.add('border-lime-400');
            }

            const quantity = parseInt(quantityInput.value) || 0;
            const total = currentUnitPrice * quantity;
            totalPriceElement.textContent = formatCurrency(total);
        }

        function formatCurrency(amount) {
            return '$' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
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
            
            updatePrices();
        }
        
        quantityInput.addEventListener('input', updateAttendeeFields);
        typeRadios.forEach(radio => radio.addEventListener('change', updatePrices));
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
