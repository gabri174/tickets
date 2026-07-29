<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/RedisCache.php';
require_once '../includes/classes/InventoryLock.php';
require_once '../includes/classes/QueueService.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$db = new Database();
$cache = RedisCache::getInstance();
$errors = [];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$eventId = (int) $_GET['id'];
if ($eventId <= 0) {
    header('Location: index.php');
    exit();
}

// Evento desde caché
$event = $cache->getEvent($eventId);
if (!$event) {
    $event = $db->getEventById($eventId);
    if ($event) {
        $cache->setEvent($eventId, $event);
    }
}

if (!$event || ($event['status'] ?? 'active') !== 'active') {
    header('Location: index.php');
    exit();
}

// Referido
if (!isset($_SESSION['referral']) && isset($_GET['ref'])) {
    $_SESSION['referral'] = cleanInput($_GET['ref']);
}

// Tracking de visita
try {
    $sessionId = session_id();
    $rawIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ipHash = hash('sha256', $rawIp);
    $db->trackVisit($eventId, $sessionId, $ipHash);
} catch (Throwable $e) {
    if (function_exists('qLog')) qLog('[WARNING] trackVisit falló: ' . $e->getMessage());
}

$ticketTypes = $cache->getTicketTypes($eventId);
if (!$ticketTypes) {
    $ticketTypes = $db->getTicketTypesByEvent($eventId);
    if ($ticketTypes) {
        $cache->setTicketTypes($eventId, $ticketTypes);
    }
}

$selectedTicketTypeId = null;
$quantityValue = 1;
$phoneValue = '';
$zipCodeValue = '';
$attendeesValue = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('buy_ticket', 'csrf_token', true);

    if (!checkRateLimit('buy_ticket', 10, 300)) {
        $errors[] = 'Has realizado demasiados intentos. Espera unos minutos antes de volver a intentarlo.';
    }

    $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
    $attendees = isset($_POST['attendees']) && is_array($_POST['attendees']) ? $_POST['attendees'] : [];
    $phone = cleanInput($_POST['phone'] ?? '');
    $zipCode = cleanInput($_POST['zip_code'] ?? '');
    $ticketTypeId = isset($_POST['ticket_type_id']) && $_POST['ticket_type_id'] !== '' ? (int) $_POST['ticket_type_id'] : null;

    $selectedTicketTypeId = $ticketTypeId;
    $quantityValue = max(1, $quantity);
    $phoneValue = $phone;
    $zipCodeValue = $zipCode;
    $attendeesValue = $attendees;

    if ($phone === '') {
        $errors[] = 'El teléfono de contacto es obligatorio.';
    }

    if ($zipCode === '') {
        $errors[] = 'El código postal es obligatorio.';
    } elseif (!preg_match('/^[A-Za-z0-9\- ]{3,12}$/', $zipCode)) {
        $errors[] = 'El código postal no tiene un formato válido.';
    }

    if ($quantity < 1) {
        $errors[] = 'Debes seleccionar al menos una entrada.';
    }

    if ($quantity > (int) ($event['available_tickets'] ?? 0)) {
        $errors[] = 'No hay suficientes entradas disponibles para este evento.';
    }

    if (count($attendees) !== $quantity) {
        $errors[] = 'Debes completar los datos de todos los asistentes.';
    } else {
        foreach ($attendees as $index => $attendee) {
            $num = $index + 1;
            $name = cleanInput($attendee['name'] ?? '');
            $surname = cleanInput($attendee['surname'] ?? '');
            $email = cleanInput($attendee['email'] ?? '');

            $attendeesValue[$index]['name'] = $name;
            $attendeesValue[$index]['surname'] = $surname;
            $attendeesValue[$index]['email'] = $email;

            if ($name === '') {
                $errors[] = "El nombre del asistente $num es obligatorio.";
            }
            if ($surname === '') {
                $errors[] = "Los apellidos del asistente $num son obligatorios.";
            }
            if ($email === '' || !validateEmail($email)) {
                $errors[] = "El email del asistente $num no es válido.";
            }
        }
    }

    $unitPrice = (float) ($event['price'] ?? 0);
    $ticketTypeName = '';
    $selectedType = null;

    if (!empty($ticketTypes)) {
        if (!$ticketTypeId) {
            $errors[] = 'Debes seleccionar un tipo de entrada.';
        } else {
            foreach ($ticketTypes as $tt) {
                if ((int) ($tt['id'] ?? 0) === $ticketTypeId) {
                    $selectedType = $tt;
                    break;
                }
            }

            if (!$selectedType) {
                $errors[] = 'El tipo de entrada seleccionado no es válido.';
            } elseif ((int) ($selectedType['available_tickets'] ?? 0) < $quantity) {
                $errors[] = 'No hay suficientes entradas disponibles de este tipo.';
            } else {
                $unitPrice = (float) ($selectedType['price'] ?? 0);
                $ticketTypeName = (string) ($selectedType['name'] ?? '');
            }
        }
    }

    if (empty($errors)) {
        $totalPrice = $unitPrice * $quantity;

        $inventoryLock = new InventoryLock($cache);
        $stockEventKey = InventoryLock::eventKey($eventId);
        $stockTypeKey = $ticketTypeId ? InventoryLock::typeKey($ticketTypeId) : null;
        $inventoryBlocked = false;
        $eventStockReserved = false;
        $typeStockReserved = false;

        if ($cache->isAvailable()) {
            $cache->initStock(
                $eventId,
                (int) ($event['available_tickets'] ?? 0),
                $ticketTypeId,
                (int) (($selectedType['available_tickets'] ?? 0))
            );

            $remaining = $inventoryLock->decrementStock($stockEventKey, $quantity);
            if ($remaining === -2) {
                $errors[] = '⚡ Sin stock disponible en este momento. Inténtalo de nuevo.';
                $inventoryBlocked = true;
            } elseif ($remaining >= 0) {
                $eventStockReserved = true;
            }

            if (!$inventoryBlocked && $stockTypeKey) {
                $remainingType = $inventoryLock->decrementStock($stockTypeKey, $quantity);
                if ($remainingType === -2) {
                    if ($eventStockReserved) {
                        $inventoryLock->restoreStock($stockEventKey, $quantity);
                        $eventStockReserved = false;
                    }
                    $errors[] = '⚡ Sin entradas disponibles de este tipo en este momento. Inténtalo de nuevo.';
                    $inventoryBlocked = true;
                } elseif ($remainingType >= 0) {
                    $typeStockReserved = true;
                }
            }
        }

        if (!$inventoryBlocked && empty($errors)) {
            try {
                $purchaseData = [
                    'event_id'       => $eventId,
                    'ticket_type_id' => $ticketTypeId,
                    'quantity'       => $quantity,
                    'attendees'      => $attendeesValue,
                    'phone'          => $phone,
                    'zip_code'       => $zipCode,
                    'total_price'    => $totalPrice,
                    'referral'       => $_SESSION['referral'] ?? null,
                ];

                // De momento solo compra directa / gratuita
                if ($totalPrice <= 0) {
                    $queue = new QueueService();
                    $queued = $queue->enqueuePurchase($purchaseData);

                    if (!empty($queued['queued'])) {
                        $_SESSION['purchase_success'] = [
                            'event_title' => $event['title'],
                            'tickets'     => [],
                            'queued'      => true,
                            'email'       => $attendeesValue[0]['email'] ?? '',
                            'phone'       => $phone,
                            'total_price' => $totalPrice,
                        ];
                    } else {
                        $result = completePurchase($purchaseData, $db);
                        $cache->invalidateEvent($eventId);
                        if ($ticketTypeId) {
                            $cache->invalidateTicketTypes($eventId);
                        }
                        $_SESSION['purchase_success'] = $result;
                    }

                    $fallbackEmail = urlencode($attendeesValue[0]['email'] ?? '');
                    header('Location: success.php?event_id=' . $eventId . '&email=' . $fallbackEmail . '&phone=' . urlencode($phone));
                    exit();
                } else {
                    $errors[] = 'El flujo de pago online todavía no está habilitado en esta versión segura. Solo se permiten reservas gratuitas o con pago ya integrado correctamente.';
                }
            } catch (Throwable $e) {
                if ($cache->isAvailable()) {
                    if ($eventStockReserved) {
                        $inventoryLock->restoreStock($stockEventKey, $quantity);
                    }
                    if ($typeStockReserved && $stockTypeKey) {
                        $inventoryLock->restoreStock($stockTypeKey, $quantity);
                    }
                }

                if (function_exists('qLog')) {
                    qLog('[ERROR] Error al procesar compra en buy.php: ' . $e->getMessage());
                }

                $errors[] = 'Error al procesar la compra. Inténtalo de nuevo en unos instantes.';
            }
        }
    }
}

$currentPage = 'buy';
$pageTitle = htmlspecialchars(($event['seo_title'] ?: $event['title'] . ' - TicketApp'), ENT_QUOTES, 'UTF-8');
$metaDescription = $event['seo_description'] ?: ('Compra tus entradas para ' . $event['title'] . ' en ' . $event['location']);
$metaKeywords = $event['seo_keywords'] ?: '';

$eventImage = !empty($event['image_url']) ? ltrim($event['image_url'], '/') : 'assets/img/default-event.jpg';
$buyUrl = rtrim(SITE_URL, '/') . '/buy.php?id=' . $eventId;

$extraHead = '
    <meta property="og:type" content="event">
    <meta property="og:url" content="' . htmlspecialchars($buyUrl, ENT_QUOTES, 'UTF-8') . '">
    <meta property="og:title" content="' . htmlspecialchars(($event['seo_title'] ?: $event['title']), ENT_QUOTES, 'UTF-8') . '">
    <meta property="og:description" content="' . htmlspecialchars(($event['seo_description'] ?: ('Compra tus entradas para ' . $event['title'] . ' en ' . $event['location'])), ENT_QUOTES, 'UTF-8') . '">
    <meta property="og:image" content="' . htmlspecialchars(rtrim(SITE_URL, '/') . '/' . $eventImage, ENT_QUOTES, 'UTF-8') . '">
    <meta property="og:site_name" content="TicketApp">
';

$extraStyles = '
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
    .animate-slide-up {
        animation: slideUp 0.4s ease-out;
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
';

require_once '../includes/partials/header.php';
?>

<header class="flex items-center justify-between mb-12">
    <a href="index.php" class="glass-pill w-12 h-12 flex items-center justify-center text-lg hover:bg-white/10 transition" aria-label="Volver al listado de eventos">
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
    <div class="lg:col-span-1 lg:sticky lg:top-6">
        <div class="glass-card overflow-hidden">
            <div class="h-48 w-full overflow-hidden">
                <?php if (!empty($event['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars(rtrim(SITE_URL, '/') . '/' . ltrim($event['image_url'], '/'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full bg-gray-800 flex items-center justify-center text-3xl" aria-hidden="true">
                        <i class="fas fa-image text-gray-700"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <h3 class="font-bold text-xl mb-4"><?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="space-y-3 mb-6">
                    <div class="flex items-center gap-3 text-sm text-gray-400">
                        <i class="fas fa-calendar-alt text-blue-400 w-5"></i>
                        <span><?php echo htmlspecialchars(formatDate($event['date_event'], 'd/m/Y'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-gray-400">
                        <i class="fas fa-clock text-blue-400 w-5"></i>
                        <span><?php echo htmlspecialchars(formatDate($event['date_event'], 'H:i'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-gray-400">
                        <i class="fas fa-map-marker-alt text-red-400 w-5"></i>
                        <span><?php echo htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <div class="pt-4 border-t border-white/10" id="priceDisplayBox">
                    <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest mb-1">PRECIO UNITARIO</p>
                    <span class="text-lime-400 font-bold text-2xl" id="unitPriceDisplay"><?php echo htmlspecialchars(formatCurrency($event['price']), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <form method="POST" action="" id="purchaseForm" class="space-y-6" novalidate>
            <?php echo csrf_field('buy_ticket'); ?>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl text-sm mb-6">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($ticketTypes)): ?>
            <div class="glass-card p-6">
                <label class="mb-4"><i class="fas fa-layer-group mr-2"></i>Selecciona tu tipo de entrada</label>
                <div class="grid grid-cols-1 gap-3">
                    <?php foreach ($ticketTypes as $index => $type): ?>
                        <?php
                            $typeId = (int) ($type['id'] ?? 0);
                            $isChecked = $selectedTicketTypeId !== null
                                ? ($selectedTicketTypeId === $typeId)
                                : ($index === 0);
                        ?>
                        <label class="relative flex items-center p-4 rounded-2xl border border-white/10 bg-white/5 cursor-pointer hover:bg-white/10 transition-all group">
                            <input
                                type="radio"
                                name="ticket_type_id"
                                value="<?php echo $typeId; ?>"
                                data-price="<?php echo htmlspecialchars((string) ($type['price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
                                data-name="<?php echo htmlspecialchars((string) ($type['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                class="ticket-type-radio hidden"
                                <?php echo $isChecked ? 'checked' : ''; ?>
                                required
                            >
                            <div class="w-5 h-5 rounded-full border-2 border-white/20 mr-4 flex items-center justify-center group-hover:border-lime-400/50 transition-colors radio-custom <?php echo $isChecked ? 'border-lime-400' : ''; ?>">
                                <div class="w-2.5 h-2.5 rounded-full bg-lime-400 transition-transform radio-dot <?php echo $isChecked ? '' : 'scale-0'; ?>"></div>
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-sm"><?php echo htmlspecialchars((string) ($type['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if (!empty($type['description'])): ?>
                                    <p class="text-[10px] text-gray-500"><?php echo htmlspecialchars((string) $type['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-lime-400"><?php echo htmlspecialchars(formatCurrency($type['price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-[9px] text-gray-500 uppercase"><?php echo (int) ($type['available_tickets'] ?? 0); ?> disponibles</p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="glass-card p-6">
                <label for="quantity"><i class="fas fa-ticket-alt mr-2"></i>¿Cuántos tickets necesitas?</label>
                <input
                    type="number"
                    name="quantity"
                    id="quantity"
                    min="1"
                    max="<?php echo (int) ($event['available_tickets'] ?? 0); ?>"
                    value="<?php echo htmlspecialchars((string) $quantityValue, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
                <p class="text-[10px] text-gray-500 mt-2">Capacidad disponible ahora: <?php echo (int) ($event['available_tickets'] ?? 0); ?></p>
            </div>

            <div id="attendeesContainer" class="space-y-4"></div>

            <div class="glass-card p-6">
                <label for="phone"><i class="fas fa-phone mr-2"></i>Móvil de contacto</label>
                <input
                    id="phone"
                    type="tel"
                    name="phone"
                    required
                    placeholder="Ej: +34 600 000 000"
                    value="<?php echo htmlspecialchars($phoneValue, ENT_QUOTES, 'UTF-8'); ?>"
                >
                <p class="text-[10px] text-gray-500 mt-2">Usaremos este número para enviarte confirmaciones de la compra.</p>
            </div>

            <div class="glass-card p-6">
                <label for="zip_code"><i class="fas fa-map-marker-alt mr-2"></i>Código Postal (CP)</label>
                <input
                    id="zip_code"
                    type="text"
                    name="zip_code"
                    required
                    placeholder="Ej: 28001"
                    value="<?php echo htmlspecialchars($zipCodeValue, ENT_QUOTES, 'UTF-8'); ?>"
                >
                <p class="text-[10px] text-gray-500 mt-2">Necesario para las analíticas de geolocalización del evento.</p>
            </div>

            <div class="pt-8">
                <div class="glass-card p-8 bg-gradient-to-br from-white/5 to-transparent">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <p class="text-gray-400 font-medium">Total a pagar</p>
                            <p class="text-[10px] text-gray-500">Incluye impuestos y cargos de servicio</p>
                        </div>
                        <span class="text-4xl font-black text-white" id="totalPrice"><?php echo htmlspecialchars(formatCurrency($event['price']), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <button type="submit" class="btn-modern btn-lime w-full text-lg py-5 shadow-lg shadow-lime-400/10">
                        <i class="fas <?php echo ((float) ($event['price'] ?? 0) > 0) ? 'fa-credit-card' : 'fa-check-circle'; ?> mr-3"></i>
                        <?php echo ((float) ($event['price'] ?? 0) > 0) ? 'Proceder al pago' : 'Confirmar mi plaza'; ?>
                    </button>

                    <p class="text-center text-[10px] text-gray-500 mt-6 flex items-center justify-center gap-2">
                        <i class="fas fa-lock text-lime-400/50"></i>
                        Tus datos están protegidos bajo cifrado de 256 bits
                    </p>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="h-20"></div>

<?php require_once '../includes/partials/footer.php'; ?>

<script>
const quantityInput = document.getElementById('quantity');
const attendeesContainer = document.getElementById('attendeesContainer');
const totalPriceElement = document.getElementById('totalPrice');
const unitPriceDisplay = document.getElementById('unitPriceDisplay');
const typeRadios = document.querySelectorAll('.ticket-type-radio');

const attendeesValue = <?php echo json_encode($attendeesValue, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
let currentUnitPrice = <?php echo json_encode((float) ($event['price'] ?? 0)); ?>;

function formatCurrency(amount) {
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount || 0);
}

function updatePrices() {
    const selectedRadio = document.querySelector('.ticket-type-radio:checked');

    if (selectedRadio) {
        currentUnitPrice = parseFloat(selectedRadio.dataset.price || '0');
        unitPriceDisplay.textContent = formatCurrency(currentUnitPrice);

        document.querySelectorAll('.radio-dot').forEach(dot => dot.classList.add('scale-0'));
        document.querySelectorAll('.radio-custom').forEach(rd => rd.classList.remove('border-lime-400'));

        const radioLabel = selectedRadio.closest('label');
        if (radioLabel) {
            const dot = radioLabel.querySelector('.radio-dot');
            const custom = radioLabel.querySelector('.radio-custom');
            if (dot) dot.classList.remove('scale-0');
            if (custom) custom.classList.add('border-lime-400');
        }
    }

    const quantity = parseInt(quantityInput.value || '0', 10) || 0;
    totalPriceElement.textContent = formatCurrency(currentUnitPrice * quantity);
}

function createAttendeeField(i, saved = {}) {
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
                <input type="text" name="attendees[${i}][name]" required value="${escapeHtml(saved.name || '')}">
            </div>
            <div>
                <label class="text-xs">Apellidos</label>
                <input type="text" name="attendees[${i}][surname]" required value="${escapeHtml(saved.surname || '')}">
            </div>
            <div>
                <label class="text-xs">Email</label>
                <input type="email" name="attendees[${i}][email]" required value="${escapeHtml(saved.email || '')}">
            </div>
        </div>
    `;
    return attendeeDiv;
}

function updateAttendeeFields() {
    const quantity = parseInt(quantityInput.value || '0', 10) || 0;
    const safeQuantity = Math.max(0, quantity);

    attendeesContainer.innerHTML = '';
    for (let i = 0; i < safeQuantity; i++) {
        attendeesContainer.appendChild(createAttendeeField(i, attendeesValue[i] || {}));
    }

    updatePrices();
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

quantityInput.addEventListener('input', updateAttendeeFields);
typeRadios.forEach(radio => radio.addEventListener('change', updatePrices));
document.addEventListener('DOMContentLoaded', () => {
    updateAttendeeFields();
    updatePrices();
});
</script>
</body>
</html>
