<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$db = new Database();
$currentPage = 'success';
$pageTitle = 'Compra completada - ' . SITE_NAME;

$purchase = $_SESSION['purchase_success'] ?? null;
$emailError = $_SESSION['email_error'] ?? null;

unset($_SESSION['purchase_success']);
unset($_SESSION['email_error']);
unset($_SESSION['smtp_log']);
unset($_SESSION['debug_email']);
unset($_SESSION['pending_purchase']);

$hasError = false;
$errorMsg = '';
$isQueued = !empty($purchase['queued']);

if (!$purchase || empty($purchase['event_id'])) {
    $hasError = true;
    $errorMsg = 'No se encontró una compra activa en tu sesión. Por seguridad, vuelve al inicio y accede otra vez desde el flujo de compra.';
    $purchase = [
        'event_id' => 0,
        'event_title' => 'Compra',
        'tickets' => [],
        'email' => '',
        'phone' => '',
        'queued' => false,
    ];
}

$eventData = null;
$imgUrl = '';

if (!empty($purchase['event_id'])) {
    $eventData = $db->getEventById((int) $purchase['event_id']);
    if ($eventData && !empty($eventData['image_url'])) {
        $imgUrl = rtrim(SITE_URL, '/') . '/' . ltrim((string) $eventData['image_url'], '/');
    }
}

$extraHead = '
    <meta name="robots" content="noindex, nofollow, noarchive">
';

$extraStyles = '
    .success-checkmark {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: block;
        stroke-width: 2;
        stroke: #DAFB71;
        stroke-miterlimit: 10;
        margin: 10% auto;
        box-shadow: inset 0px 0px 0px #DAFB71;
        animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
    }
    .animate-ticket {
        animation: bounceIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes bounceIn {
        from { opacity: 0; transform: scale(0.9) translateY(40px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
';

require_once '../includes/partials/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <?php if ($hasError): ?>
        <div class="flex flex-col md:flex-row items-center gap-8 mb-12 py-10 bg-red-500/5 border border-red-500/20 rounded-[2.5rem] px-10 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-red-500/5 blur-[100px] -mr-32 -mt-32"></div>
            <div class="h-20 w-20 bg-red-500/20 border border-red-500/30 rounded-3xl flex items-center justify-center text-red-400 shadow-2xl flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-4xl"></i>
            </div>
            <div class="text-center md:text-left">
                <h2 class="text-4xl font-black text-white mb-2 tracking-tighter">Sesión no disponible</h2>
                <p class="text-gray-400 font-medium text-lg"><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="md:ml-auto">
                <a href="index.php" class="btn-modern bg-white/10 text-white px-6 py-3 text-sm font-bold">
                    <i class="fas fa-home mr-2"></i>Volver al inicio
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="flex flex-col md:flex-row items-center gap-8 mb-12 py-10 bg-lime-400/5 border border-lime-400/10 rounded-[2.5rem] px-10 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-lime-400/10 blur-[100px] -mr-32 -mt-32"></div>
            <div class="h-20 w-20 bg-lime-400 rounded-3xl flex items-center justify-center text-black shadow-2xl shadow-lime-400/30 flex-shrink-0 animate-bounce">
                <i class="fas fa-check text-4xl"></i>
            </div>
            <div class="text-center md:text-left">
                <h2 class="text-4xl font-black text-white mb-2 tracking-tighter">
                    <?php echo $isQueued ? '¡Reserva en proceso!' : '¡Compra completada!'; ?>
                </h2>
                <p class="text-gray-400 font-medium text-lg">
                    <?php if ($isQueued): ?>
                        Estamos terminando de procesar tu reserva. Te enviaremos la confirmación a
                        <span class="text-white"><?php echo htmlspecialchars((string) ($purchase['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php else: ?>
                        Tus tickets están listos. Hemos enviado un correo a
                        <span class="text-white"><?php echo htmlspecialchars((string) ($purchase['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!$isQueued && !empty($purchase['tickets'])): ?>
                <div class="md:ml-auto flex flex-col items-center gap-2">
                    <button onclick="shareOnWhatsApp()" class="text-[10px] text-lime-400 uppercase font-black tracking-widest hover:underline" type="button">
                        Compartir por WhatsApp
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($emailError): ?>
        <div class="glass-card mb-8 p-4 border-yellow-500/20 bg-yellow-500/5 text-yellow-400 text-sm text-center rounded-2xl">
            <i class="fas fa-envelope-open-text mr-2"></i> <?php echo htmlspecialchars((string) $emailError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!$hasError && $isQueued): ?>
        <div class="glass-card mb-10 p-6 text-center">
            <p class="text-white text-lg font-bold mb-2">Tu solicitud ya entró en cola correctamente.</p>
            <p class="text-gray-400 text-sm">No necesitas volver a enviar el formulario. En cuanto termine el proceso, recibirás la confirmación en tu correo.</p>
        </div>
    <?php endif; ?>

    <?php if (!$hasError && !$isQueued && !empty($purchase['tickets'])): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            <?php foreach ($purchase['tickets'] as $ticket): ?>
                <?php
                    $ticketCode = (string) ($ticket['code'] ?? '');
                    $ticketName = (string) ($ticket['name'] ?? '');
                    $ticketType = (string) ($ticket['type_name'] ?? 'General');
                    $qrFile = basename((string) ($ticket['qr_path'] ?? ''));
                    $qrWebPath = rtrim(SITE_URL, '/') . '/qrcodes/' . rawurlencode($qrFile);
                    $ticketPublicUrl = rtrim(SITE_URL, '/') . '/ticket.php?code=' . rawurlencode($ticketCode);
                ?>
                <div class="ticket-main-card shadow-2xl animate-ticket">
                    <?php if ($imgUrl): ?>
                        <div class="p-2">
                            <img src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>" class="ticket-image rounded-2xl shadow-lg" alt="<?php echo htmlspecialchars((string) ($purchase['event_title'] ?? 'Evento'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    <?php else: ?>
                        <div class="ticket-image bg-gray-200 flex items-center justify-center text-gray-400" aria-hidden="true">
                            <i class="fas fa-ticket-alt text-5xl"></i>
                        </div>
                    <?php endif; ?>

                    <div class="ticket-content">
                        <h3 class="text-2xl font-bold text-center mb-6 leading-tight"><?php echo htmlspecialchars((string) ($purchase['event_title'] ?? 'Evento'), ENT_QUOTES, 'UTF-8'); ?></h3>

                        <div class="grid grid-cols-2 gap-y-6 gap-x-4 mb-2">
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">FECHA</p>
                                <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars(formatDate($eventData['date_event'] ?? date('Y-m-d'), 'd M, Y'), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">HORA</p>
                                <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars(formatDate($eventData['date_event'] ?? date('Y-m-d H:i:s'), 'H:i'), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">LUGAR</p>
                                <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars((string) ($eventData['location'] ?? 'Ubicación pendiente'), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">TIPO</p>
                                <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($ticketType, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>

                        <div class="ticket-divider"></div>

                        <div class="flex flex-col items-center">
                            <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-4">ASISTENTE</p>
                            <p class="text-lg font-bold text-gray-900 mb-6"><?php echo htmlspecialchars($ticketName, ENT_QUOTES, 'UTF-8'); ?></p>

                            <div class="p-2 border-2 border-dashed border-gray-200 rounded-2xl mb-4 bg-white">
                                <img src="<?php echo htmlspecialchars($qrWebPath, ENT_QUOTES, 'UTF-8'); ?>" alt="QR del ticket" class="w-32 h-32 contrast-125">
                            </div>

                            <p class="text-xs font-mono text-gray-400 tracking-tighter uppercase"><?php echo htmlspecialchars($ticketCode, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 p-6 pt-0">
                        <button
                            onclick='shareIndividualTicket(<?php echo json_encode($ticketCode, JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode((string) ($purchase["event_title"] ?? "Evento"), JSON_UNESCAPED_UNICODE); ?>)'
                            class="btn-modern btn-lime w-full text-xs py-3"
                            type="button">
                            <i class="fab fa-whatsapp mr-2"></i> Compartir ticket
                        </button>

                        <a href="<?php echo htmlspecialchars($ticketPublicUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"
                           class="btn-modern bg-gray-100 text-gray-800 w-full text-xs py-3">
                            <i class="fas fa-external-link-alt mr-2"></i> Ver ticket online
                        </a>

                        <a href="wallet.php?ticket_code=<?php echo rawurlencode($ticketCode); ?>"
                           class="btn-modern bg-black text-white w-full text-xs py-3 hover:bg-gray-900 border border-gray-800 transition-colors shadow-lg">
                            <i class="fab fa-apple mr-2 text-lg"></i> Añadir a Wallet
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="text-center mt-8">
        <a href="index.php" class="btn-modern btn-lime inline-flex items-center gap-2 px-8 py-4 text-base font-bold">
            <i class="fas fa-home"></i>
            Volver al inicio
        </a>
    </div>

    <div class="h-12"></div>
</div>

<?php require_once '../includes/partials/footer.php'; ?>

<script>
function shareOnWhatsApp() {
    const eventTitle = <?php echo json_encode((string) ($purchase['event_title'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
    const phone = <?php echo json_encode(preg_replace('/[^0-9]/', '', (string) ($purchase['phone'] ?? '')), JSON_UNESCAPED_UNICODE); ?>;
    const firstTicket = <?php echo json_encode((string) ($purchase['tickets'][0]['code'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
    const baseUrl = <?php echo json_encode(rtrim(SITE_URL, '/'), JSON_UNESCAPED_UNICODE); ?>;
    const ticketUrl = firstTicket ? `${baseUrl}/ticket.php?code=${encodeURIComponent(firstTicket)}` : baseUrl;

    const message =
        `🎉 ¡Hola! Aquí tienes tus entradas para "${eventTitle}"!\n\n` +
        `Puedes ver tu entrada principal aquí:\n${ticketUrl}\n\n` +
        `¡Gracias por tu compra! 🎪`;

    const waUrl = phone
        ? `https://wa.me/${phone}?text=${encodeURIComponent(message)}`
        : `https://wa.me/?text=${encodeURIComponent(message)}`;

    window.open(waUrl, '_blank', 'noopener');
}

function shareIndividualTicket(code, eventTitle) {
    const baseUrl = <?php echo json_encode(rtrim(SITE_URL, '/'), JSON_UNESCAPED_UNICODE); ?>;
    const phone = <?php echo json_encode(preg_replace('/[^0-9]/', '', (string) ($purchase['phone'] ?? '')), JSON_UNESCAPED_UNICODE); ?>;
    const ticketUrl = `${baseUrl}/ticket.php?code=${encodeURIComponent(code)}`;

    const message =
        `🎫 Aquí tienes tu entrada para "${eventTitle}"\n\n` +
        `Código: ${code}\n` +
        `Presenta el QR al llegar:\n${ticketUrl}`;

    const waUrl = phone
        ? `https://wa.me/${phone}?text=${encodeURIComponent(message)}`
        : `https://wa.me/?text=${encodeURIComponent(message)}`;

    window.open(waUrl, '_blank', 'noopener');
}
</script>
</body>
</html>
