<?php
require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';
require_once '../includes/functions/functions.php';

$db = new Database();
$error = '';
$success = '';

// Obtener ID del evento desde la URL
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$eventId) {
    header('Location: ../');
    exit();
}

// Obtener datos del evento
$event = $db->getEventById($eventId);
if (!$event || $event['status'] !== 'active') {
    header('Location: ../');
    exit();
}

// Obtener tipos de entrada del evento
$ticketTypes = $db->getTicketTypesByEvent($eventId);

// Si no hay tipos de entrada, crear uno por defecto
if (empty($ticketTypes) && $event['price'] > 0) {
    $db->createTicketType($eventId, 'Entrada General', 'Acceso general al evento', $event['price'], $event['max_tickets'], 0);
    $ticketTypes = $db->getTicketTypesByEvent($eventId);
}

// Procesar compra
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception('Error de seguridad. Por favor, intenta de nuevo.');
        }

        $ticketTypeId = isset($_POST['ticket_type_id']) ? (int)$_POST['ticket_type_id'] : null;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        $attendees = $_POST['attendees'] ?? [];
        $phone = cleanInput($_POST['phone'] ?? '');
        $zipCode = cleanInput($_POST['zip_code'] ?? '');
        $referral = cleanInput($_POST['referral'] ?? '');

        // Validaciones básicas
        if (empty($attendees) || count($attendees) !== $quantity) {
            throw new Exception('Debes ingresar los datos de todos los asistentes');
        }

        if (empty($phone)) {
            throw new Exception('El teléfono es obligatorio');
        }

        // Calcular precio total
        $totalPrice = 0;
        if ($ticketTypeId) {
            $ticketType = $db->getTicketTypeById($ticketTypeId);
            $totalPrice = $ticketType['price'] * $quantity;
        } else {
            $totalPrice = $event['price'] * $quantity;
        }

        // Preparar datos para la compra
        $purchaseData = [
            'event_id' => $eventId,
            'ticket_type_id' => $ticketTypeId,
            'quantity' => $quantity,
            'attendees' => $attendees,
            'phone' => $phone,
            'total_price' => $totalPrice,
            'zip_code' => $zipCode
        ];

        // Completar la compra
        $result = completePurchase($purchaseData, $db);

        // Redirigir a la página de confirmación
        $_SESSION['purchase_success'] = true;
        $_SESSION['purchase_result'] = $result;
        header('Location: confirmation.php?event_id=' . $eventId);
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$eventDate = date('d/m/Y', strtotime($event['date_event']));
$eventTime = date('H:i', strtotime($event['date_event']));
$organizer = $db->getAdminById($event['admin_id']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - Tickets</title>
    <meta name="description" content="<?php echo htmlspecialchars($event['seo_description'] ?? $event['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($event['seo_keywords'] ?? ''); ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($event['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($event['description']); ?>">
    <meta property="og:image" content="<?php echo SITE_URL . '/' . htmlspecialchars($event['image_url']); ?>">
    <meta property="og:type" content="event">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #0A0E14;
            color: white;
            font-family: 'Outfit', sans-serif;
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

        .btn-primary {
            background: linear-gradient(135deg, #DAFB71 0%, #a3d94a 100%);
            color: #000;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(218, 251, 113, 0.3);
        }

        input, select, textarea {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }

        input:focus, select:focus, textarea:focus {
            border-color: rgba(218, 251, 113, 0.5) !important;
            box-shadow: 0 0 15px rgba(218, 251, 113, 0.1) !important;
        }

        .ticket-type-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .ticket-type-card:hover,
        .ticket-type-card.selected {
            border-color: rgba(218, 251, 113, 0.5);
            background: rgba(218, 251, 113, 0.05);
        }

        .ticket-type-card input[type="radio"] {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-[#0A0E14]/90 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="store.php?id=<?php echo $event['admin_id']; ?>" class="flex items-center gap-3 text-gray-400 hover:text-white transition">
                <i class="fas fa-arrow-left"></i>
                <span class="text-sm font-bold">Volver a la tienda</span>
            </a>
            <a href="../" class="px-4 py-2 rounded-full bg-white/5 border border-white/10 text-xs font-bold text-gray-300 hover:text-white hover:bg-white/10 transition">
                <i class="fas fa-home mr-2"></i>Inicio
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative py-12 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Event Info -->
                <div class="space-y-6">
                    <div>
                        <span class="px-3 py-1 bg-lime-400/10 border border-lime-400/20 rounded-full text-xs font-bold text-lime-400 uppercase tracking-wider">
                            <?php echo htmlspecialchars($event['category']); ?>
                        </span>
                    </div>

                    <h1 class="text-4xl md:text-5xl font-black tracking-tighter">
                        <?php echo htmlspecialchars($event['title']); ?>
                    </h1>

                    <p class="text-xl text-gray-400">
                        <?php echo htmlspecialchars($event['description']); ?>
                    </p>

                    <!-- Event Details -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="glass-card rounded-2xl p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-lime-400/10 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-calendar text-lime-400"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-bold">Fecha</p>
                                    <p class="font-bold"><?php echo $eventDate; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-clock text-blue-400"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-bold">Hora</p>
                                    <p class="font-bold"><?php echo $eventTime; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-4 col-span-2">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-purple-500/10 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-map-marker-alt text-purple-400"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-bold">Ubicación</p>
                                    <p class="font-bold"><?php echo htmlspecialchars($event['location']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Event Image -->
                    <?php if ($event['image_url']): ?>
                        <div class="rounded-3xl overflow-hidden">
                            <img src="<?php echo htmlspecialchars($event['image_url']); ?>"
                                 alt="<?php echo htmlspecialchars($event['title']); ?>"
                                 class="w-full h-64 object-cover">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Purchase Form -->
                <div class="glass-card rounded-[2.5rem] p-8 h-fit sticky top-24">
                    <h2 class="text-2xl font-black mb-6">Comprar Entradas</h2>

                    <?php if ($error): ?>
                        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl text-sm mb-6">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-xl text-sm mb-6">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="purchaseForm">
                        <?php echo csrf_field(); ?>

                        <!-- Ticket Type Selection -->
                        <?php if (count($ticketTypes) > 1): ?>
                            <div class="mb-6">
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">
                                    Tipo de Entrada
                                </label>
                                <div class="space-y-3">
                                    <?php foreach ($ticketTypes as $type): ?>
                                        <label class="ticket-type-card block glass-card rounded-2xl p-4 cursor-pointer">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <input type="radio" name="ticket_type_id" value="<?php echo $type['id']; ?>"
                                                           <?php echo $loop->first ? 'checked' : ''; ?>>
                                                    <div>
                                                        <p class="font-bold"><?php echo htmlspecialchars($type['name']); ?></p>
                                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($type['description']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-xl font-black text-lime-400"><?php echo number_format($type['price'], 2); ?>€</p>
                                                    <p class="text-xs text-gray-500"><?php echo $type['available_tickets']; ?> disponibles</p>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif (count($ticketTypes) === 1): ?>
                            <input type="hidden" name="ticket_type_id" value="<?php echo $ticketTypes[0]['id']; ?>">
                        <?php else: ?>
                            <input type="hidden" name="ticket_type_id" value="">
                        <?php endif; ?>

                        <!-- Quantity -->
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">
                                Cantidad
                            </label>
                            <div class="flex items-center gap-4">
                                <button type="button" onclick="updateQuantity(-1)"
                                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:bg-white/10 transition">
                                    <i class="fas fa-minus text-sm"></i>
                                </button>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="10"
                                       class="w-16 text-center py-2 rounded-xl font-bold" readonly>
                                <button type="button" onclick="updateQuantity(1)"
                                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:bg-white/10 transition">
                                    <i class="fas fa-plus text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Attendees Fields (dynamically shown) -->
                        <div id="attendeesContainer" class="space-y-6 mb-6">
                            <!-- Will be populated by JavaScript -->
                        </div>

                        <!-- Contact Info -->
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">
                                Teléfono de Contacto
                            </label>
                            <input type="tel" name="phone" required placeholder="+34 600 000 000"
                                   class="w-full px-4 py-3 rounded-xl outline-none">
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">
                                Código Postal
                            </label>
                            <input type="text" name="zip_code" placeholder="28001"
                                   class="w-full px-4 py-3 rounded-xl outline-none">
                        </div>

                        <!-- Referral -->
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">
                                Código de Referido (opcional)
                            </label>
                            <input type="text" name="referral" placeholder="AFILIADO123"
                                   class="w-full px-4 py-3 rounded-xl outline-none">
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="w-full btn-primary py-4 rounded-2xl text-lg flex items-center justify-center gap-2">
                            <span>Completar Compra</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>

                        <p class="text-xs text-gray-500 text-center mt-4">
                            <i class="fas fa-lock mr-1"></i>
                            Pago seguro. Recibirás tus entradas por email.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Organizer Info -->
    <section class="max-w-7xl mx-auto px-4 pb-20">
        <div class="glass-card rounded-[2.5rem] p-8">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-gradient-to-br from-lime-400 to-blue-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-2xl text-white"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Organizado por</p>
                    <p class="text-xl font-black"><?php echo htmlspecialchars($organizer['username']); ?></p>
                </div>
                <a href="store.php?id=<?php echo $event['admin_id']; ?>"
                   class="ml-auto px-6 py-3 rounded-full bg-white/5 border border-white/10 text-sm font-bold hover:bg-white/10 transition">
                    Ver más eventos
                </a>
            </div>
        </div>
    </section>

    <script>
        const maxTickets = <?php echo $ticketTypes[0]['available_tickets'] ?? $event['available_tickets']; ?>;
        const ticketTypes = <?php echo json_encode($ticketTypes); ?>;

        function updateQuantity(delta) {
            const input = document.getElementById('quantity');
            let value = parseInt(input.value) + delta;
            value = Math.max(1, Math.min(value, maxTickets, 10));
            input.value = value;
            renderAttendeeFields(value);
        }

        function renderAttendeeFields(count) {
            const container = document.getElementById('attendeesContainer');
            container.innerHTML = '';

            for (let i = 0; i < count; i++) {
                const fieldset = document.createElement('div');
                fieldset.className = 'glass-card rounded-2xl p-4';
                fieldset.innerHTML = `
                    <p class="text-xs font-bold text-lime-400 uppercase mb-3">
                        <i class="fas fa-user mr-2"></i>Asistente ${i + 1}
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-bold mb-2">Nombre</label>
                            <input type="text" name="attendees[${i}][name]" required placeholder="Juan"
                                   class="w-full px-3 py-2 rounded-xl outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-bold mb-2">Apellidos</label>
                            <input type="text" name="attendees[${i}][surname]" required placeholder="Pérez"
                                   class="w-full px-3 py-2 rounded-xl outline-none text-sm">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-[10px] text-gray-500 uppercase font-bold mb-2">Email</label>
                        <input type="email" name="attendees[${i}][email]" required placeholder="juan@example.com"
                               class="w-full px-3 py-2 rounded-xl outline-none text-sm">
                    </div>
                `;
                container.appendChild(fieldset);
            }
        }

        // Initialize
        renderAttendeeFields(1);

        // Form submission
        document.getElementById('purchaseForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando...';
        });
    </script>
</body>
</html>
