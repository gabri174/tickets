<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

$db = new Database();
$isAdmin = !empty($_SESSION['admin_id']);
$validationSuccess = false;
$errorMsg = '';

if (!isset($_GET['code']) || $_GET['code'] === '') {
    http_response_code(404);
    exit('Ticket no encontrado');
}

$ticketCode = cleanInput($_GET['code']);

// Defensa básica: limitar formato esperado del código
if (!preg_match('/^[A-Za-z0-9\-_]{6,100}$/', $ticketCode)) {
    http_response_code(404);
    exit('Ticket no encontrado');
}

// Validación de entrada: solo admins, solo POST, con CSRF
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'validate'
) {
    if (!$isAdmin) {
        http_response_code(403);
        exit('Acceso denegado');
    }

    require_valid_csrf('validate_ticket', 'csrf_token', true);

    $currentTicket = $db->getTicketByCode($ticketCode);

    if ($currentTicket && ($currentTicket['status'] ?? '') === 'valid') {
        if ($db->updateTicketStatus((int) $currentTicket['id'], 'used')) {
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
    http_response_code(404);
    exit('Ticket no encontrado');
}

if (!isset($ticket['image_url'])) {
    $ticket['image_url'] = null;
}

$ticketStatus = (string) ($ticket['status'] ?? 'cancelled');
$allowedStatuses = ['valid', 'used', 'cancelled'];
if (!in_array($ticketStatus, $allowedStatuses, true)) {
    $ticketStatus = 'cancelled';
}

$eventTitle = (string) ($ticket['event_title'] ?? 'Evento');
$eventLocation = (string) ($ticket['location'] ?? '');
$attendeeName = (string) ($ticket['attendee_name'] ?? '');
$ticketTypeName = (string) ($ticket['ticket_type_name'] ?? 'General');
$ticketCodeSafe = (string) ($ticket['ticket_code'] ?? '');

$pageTitle = 'Ticket - ' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8');
$currentPage = 'ticket';

$ticketUrl = rtrim(SITE_URL, '/') . '/ticket.php?code=' . rawurlencode($ticketCodeSafe);
$ogImageTag = '';

if (!empty($ticket['image_url'])) {
    $ogImageUrl = rtrim(SITE_URL, '/') . '/' . ltrim((string) $ticket['image_url'], '/');
    $ogImageTag = '<meta property="og:image" content="' . htmlspecialchars($ogImageUrl, ENT_QUOTES, 'UTF-8') . '">';
}

$extraHead = '
    <meta property="og:title" content="🎫 Ticket: ' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') . '">
    <meta property="og:description" content="' . htmlspecialchars('Fecha: ' . formatDate($ticket['date_event']) . ' en ' . $eventLocation . '. ¡Presenta este ticket en la entrada!', ENT_QUOTES, 'UTF-8') . '">
    ' . $ogImageTag . '
    <meta property="og:url" content="' . htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') . '">
    <meta property="og:type" content="website">
    <meta name="robots" content="noindex, nofollow, noarchive">
';

$extraStyles = '
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
';

require_once '../includes/partials/header.php';
?>

<?php if ($isAdmin): ?>
    <div class="sticky top-20 left-0 right-0 z-40 px-4 py-4 no-print flex justify-center bg-[#0A0E14]/50 backdrop-blur-md border-b border-white/5">
        <div class="bg-gray-900/90 backdrop-blur-xl border border-white/10 px-6 py-3 rounded-2xl shadow-2xl flex items-center gap-6">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-lime-400 animate-pulse"></div>
                <span class="text-[10px] font-black uppercase tracking-widest text-lime-400">Modo Administrador</span>
            </div>
            <div class="h-4 w-px bg-white/10"></div>

            <?php if ($ticketStatus === 'valid'): ?>
                <form method="POST" class="m-0">
                    <?php echo csrf_field('validate_ticket'); ?>
                    <input type="hidden" name="action" value="validate">
                    <button type="submit" class="bg-lime-400 text-black px-4 py-1.5 rounded-xl text-xs font-bold hover:scale-105 transition-all shadow-lg shadow-lime-400/20">
                        <i class="fas fa-check-circle mr-1"></i> Validar entrada
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
                    <span class="font-black uppercase tracking-tight text-lg">¡Ticket validado!</span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="mb-6">
                <div class="bg-red-500 text-white px-6 py-4 rounded-2xl flex items-center justify-center gap-3 shadow-2xl">
                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                    <span class="font-black uppercase tracking-tight text-lg"><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white/5 border border-white/10 p-1.5 rounded-[2.5rem] shadow-2xl backdrop-blur-2xl">
            <div class="ticket-main-card shadow-2xl relative">
                <div class="status-badge status-<?php echo htmlspecialchars($ticketStatus, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php
                    echo $ticketStatus === 'valid'
                        ? 'VÁLIDO'
                        : ($ticketStatus === 'used' ? 'UTILIZADO' : 'CANCELADO');
                    ?>
                </div>

                <?php if (!empty($ticket['image_url'])): ?>
                    <img
                        src="<?php echo htmlspecialchars(rtrim(SITE_URL, '/') . '/' . ltrim((string) $ticket['image_url'], '/'), ENT_QUOTES, 'UTF-8'); ?>"
                        class="ticket-image"
                        alt="<?php echo htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                <?php else: ?>
                    <div class="ticket-image bg-gray-200 flex items-center justify-center text-gray-400" aria-hidden="true">
                        <i class="fas fa-ticket-alt text-5xl"></i>
                    </div>
                <?php endif; ?>

                <div class="ticket-content">
                    <h3 class="text-2xl font-bold text-center mb-6 leading-tight"><?php echo htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8'); ?></h3>

                    <div class="grid grid-cols-2 gap-y-6 gap-x-4 mb-2">
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">FECHA</p>
                            <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars(formatDate($ticket['date_event'], 'd M, Y'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">HORA</p>
                            <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars(formatDate($ticket['date_event'], 'H:i'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">LUGAR</p>
                            <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($eventLocation, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">TIPO</p>
                            <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($ticketTypeName, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>

                    <div class="ticket-divider"></div>

                    <div class="flex flex-col items-center">
                        <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-4">ASISTENTE</p>
                        <p class="text-lg font-bold text-gray-900 mb-6"><?php echo htmlspecialchars($attendeeName, ENT_QUOTES, 'UTF-8'); ?></p>

                        <div class="p-2 border-2 border-dashed border-gray-200 rounded-2xl mb-4 bg-white">
                            <?php
                            $qrFile = basename((string) ($ticket['qr_code_path'] ?? ''));
                            $qrWebPath = rtrim(SITE_URL, '/') . '/qrcodes/' . rawurlencode($qrFile);
                            ?>
                            <img src="<?php echo htmlspecialchars($qrWebPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Código QR del ticket" class="w-40 h-40 contrast-125">
                        </div>

                        <p class="text-xs font-mono text-gray-400 tracking-[0.2em] uppercase"><?php echo htmlspecialchars($ticketCodeSafe, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 p-6 pt-0 no-print">
                    <button onclick="shareTicket()" class="btn-modern btn-lime w-full text-sm py-3" type="button">
                        <i class="fab fa-whatsapp mr-2"></i> Compartir por WhatsApp
                    </button>
                    <div class="flex gap-2">
                        <button onclick="window.print()" class="btn-modern bg-gray-200 text-gray-800 flex-1 text-xs py-3" type="button">
                            <i class="fas fa-print mr-2"></i> Imprimir
                        </button>
                        <a href="index.php" class="btn-modern bg-gray-800 text-white flex-1 text-xs py-3 text-center">
                            <i class="fas fa-home mr-2"></i> Inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-[10px] text-gray-500 text-center mt-12 uppercase tracking-widest font-bold no-print">
            <i class="fas fa-info-circle mr-1 text-lime-400/50"></i>
            Presenta este QR en la entrada del evento
        </p>
    </div>

    <div class="h-12 no-print"></div>
</div>

<?php require_once '../includes/partials/footer.php'; ?>

<script>
function shareTicket() {
    const message =
        `🎫 Mi ticket para "${<?php echo json_encode($eventTitle, JSON_UNESCAPED_UNICODE); ?>}"\n\n` +
        `📅 ${<?php echo json_encode(formatDate($ticket['date_event']), JSON_UNESCAPED_UNICODE); ?>}\n` +
        `📍 ${<?php echo json_encode($eventLocation, JSON_UNESCAPED_UNICODE); ?>}\n` +
        `🎟️ Código: ${<?php echo json_encode($ticketCodeSafe, JSON_UNESCAPED_UNICODE); ?>}\n\n` +
        `Ver ticket: ${window.location.href}`;

    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank', 'noopener');
}
</script>
</body>
</html>
