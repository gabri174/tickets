<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

$isAsync = isset($_GET['async_success']) && $_GET['async_success'] === 'true';

// --- ENDPOINT PARA POLLING DE ESTADO ---
if (isset($_GET['check_status']) && $_GET['check_status'] === '1') {
    header('Content-Type: application/json');
    $email = $_GET['email'] ?? '';
    $phone = $_GET['phone'] ?? '';
    $eventId = $_GET['event_id'] ?? 0;

    if (($email || $phone) && $eventId) {
        $db = new Database();
        // Buscar por email o por teléfono
        $recentTickets = [];
        if ($email) {
            $recentTickets = $db->getRecentTicketsByEmail($email, $eventId, 10);
        }
        // Si no hay por email, intentar por teléfono
        if (empty($recentTickets) && $phone) {
            $recentTickets = $db->getRecentTicketsByPhone($phone, $eventId, 10);
        }

        if (count($recentTickets) > 0) {
            echo json_encode(['ready' => true, 'count' => count($recentTickets), 'debug' => ['email' => $email, 'phone' => $phone]]);
        } else {
            echo json_encode(['ready' => false, 'message' => 'Generando tickets...', 'debug' => ['email' => $email, 'phone' => $phone, 'event' => $eventId]]);
        }
    } else {
        echo json_encode(['error' => 'Datos incompletos', 'email' => $email, 'phone' => $phone, 'event' => $eventId]);
    }
    exit();
}

// --- ENDPOINT DE DEBUG PARA VERIFICAR PARÁMETROS ---
if (isset($_GET['debug_params']) && $_GET['debug_params'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'GET' => $_GET,
        'SESSION' => $_SESSION,
        'isAsync' => $isAsync,
        'tickets_found' => isset($purchase['tickets']) ? count($purchase['tickets']) : 0
    ]);
    exit();
}

// --- AUTO-DETECTION PARA TICKETS EN COLA ---
// Si estamos en modo espera pero ya existen los tickets en DB (aunque el mail tarde), los mostramos.
$phone = $_GET['phone'] ?? '';
if ($isAsync && isset($_GET['email']) && isset($_GET['event_id'])) {
    $email = $_GET['email'];
    $eventId = $_GET['event_id'];
    $db = new Database();
    // Buscamos tickets para este mail/evento en los últimos 10 minutos
    $recentTickets = [];
    if ($email) {
        $recentTickets = $db->getRecentTicketsByEmail($email, $eventId, 10);
    }
    // Si no hay por email, intentar por teléfono
    if (empty($recentTickets) && $phone) {
        $recentTickets = $db->getRecentTicketsByPhone($phone, $eventId, 10);
    }

    if (count($recentTickets) > 0) {
        $isAsync = false; // Cambiamos a modo éxito total - tickets encontrados
        $purchase = [
            'event_id' => $eventId,
            'event_title' => '', // Se actualizará abajo con getEventById
            'tickets' => [],
            'email' => $email,
            'phone' => $phone
        ];
        foreach ($recentTickets as $rt) {
            $purchase['tickets'][] = [
                'code' => $rt['ticket_code'],
                'name' => $rt['attendee_name'],
                'qr_path' => $rt['qr_code_path'],
                'type_name' => $rt['type_name']
            ];
        }
    } else {
        // --- FAILSAFE: Si no hay tickets pero hay compra pendiente en sesión, procesar ahora ---
        if (isset($_SESSION['pending_purchase']) && $_SESSION['pending_purchase']['event_id'] == $eventId) {
            try {
                $db = new Database();
                $result = completePurchase($_SESSION['pending_purchase'], $db);
                $_SESSION['purchase_success'] = $result;
                unset($_SESSION['pending_purchase']);
                
                $isAsync = false;
                $purchase = $result;
            } catch (Throwable $e) {
                // Loguear error pero mantener modo asíncrono por si acaso el worker real funciona
                error_log("Failsafe en success.php falló: " . $e->getMessage());
            }
        }
    }
}

// Verificar si hay datos de compra o si es asíncrona
if (!isset($_SESSION['purchase_success']) && !$isAsync && (!isset($purchase['tickets']) || count($purchase['tickets']) == 0)) {
    header('Location: index.php');
    exit();
}

$purchase = $_SESSION['purchase_success'] ?? $purchase ?? ['event_id' => $_GET['event_id'] ?? 0, 'email' => 'tu correo electrónico'];
$emailError = $_SESSION['email_error'] ?? null;
$debugMode = isset($_GET['debug']);

if ($debugMode) {
    $_SESSION['debug_email'] = true;
}

// Limpiar sesión después de mostrar
if (!$debugMode) {
    unset($_SESSION['purchase_success']);
    unset($_SESSION['email_error']);
    unset($_SESSION['smtp_log']);
    unset($_SESSION['debug_email']);
}

$db = new Database();
$eventData = $db->getEventById($purchase['event_id'] ?? 0);
$imgUrl = ($eventData && $eventData['image_url']) ? SITE_URL . '/' . $eventData['image_url'] : '';


$currentPage = 'success';
$pageTitle = '¡Compra Exitosa! - ' . SITE_NAME;
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
        <!-- Success Status -->
        <div class="flex flex-col md:flex-row items-center gap-8 mb-12 py-10 bg-lime-400/5 border border-lime-400/10 rounded-[2.5rem] px-10 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-lime-400/10 blur-[100px] -mr-32 -mt-32"></div>
            <div class="h-20 w-20 bg-lime-400 rounded-3xl flex items-center justify-center text-black shadow-2xl shadow-lime-400/30 flex-shrink-0 animate-bounce">
                <i class="fas fa-check text-4xl"></i>
            </div>
            <div class="text-center md:text-left">
                <?php if ($isAsync): ?>
                    <h2 class="text-4xl font-black text-white mb-2 tracking-tighter">¡Reserva Recibida!</h2>
                    <p class="text-gray-400 font-medium text-lg">Estamos generando tus códigos. Recibirás tus entradas en <span class="text-white">breve en tu correo</span>.</p>
                <?php else: ?>
                    <h2 class="text-4xl font-black text-white mb-2 tracking-tighter">¡Compra Completada!</h2>
                    <p class="text-gray-400 font-medium text-lg">Tus tickets están listos. Hemos enviado un correo a <span class="text-white"><?php echo htmlspecialchars($purchase['email']); ?></span></p>
                <?php endif; ?>
            </div>
            <?php if (!$isAsync): ?>
            <div class="md:ml-auto flex flex-col items-center gap-2">
                 <div id="whatsappStatus" class="flex items-center gap-3 px-6 py-3 bg-white/5 border border-white/10 rounded-2xl text-sm font-bold text-gray-400">
                    <div class="w-2 h-2 rounded-full bg-lime-400 animate-pulse"></div>
                    Enviando por WhatsApp...
                 </div>
                 <button onclick="shareOnWhatsApp()" class="text-[10px] text-lime-400 uppercase font-black tracking-widest hover:underline">¿No se abrió? Reenviar</button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($emailError): ?>
            <div class="glass-card mb-8 p-4 border-red-500/20 bg-red-500/5 text-red-400 text-xs text-center">
                <i class="fas fa-exclamation-triangle mr-1"></i> <?php echo htmlspecialchars($emailError); ?>
            </div>
        <?php endif; ?>

        <?php if ($isAsync && count($purchase['tickets']) > 0): ?>
            <!-- Éxito después de procesamiento async - Banner verde -->
            <div class="glass-card mb-8 p-6 border-lime-400/30 bg-lime-400/10 rounded-2xl flex items-center gap-4">
                <div class="w-14 h-14 bg-lime-400 rounded-2xl flex items-center justify-center text-black flex-shrink-0 shadow-xl shadow-lime-400/30">
                    <i class="fas fa-check text-3xl"></i>
                </div>
                <div class="text-left flex-1">
                    <h3 class="text-2xl font-black text-white mb-1">¡Tickets Generados!</h3>
                    <p class="text-gray-300 text-sm">Hemos enviado los tickets a <span class="text-white font-bold"><?php echo htmlspecialchars($purchase['email']); ?></span>. Revisa tu bandeja de entrada (y spam).</p>
                </div>
                <div class="hidden md:block">
                    <i class="fas fa-envelope-open-text text-4xl text-lime-400"></i>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isAsync): ?>
            <!-- Async Processing State con Polling -->
            <div class="glass-card mb-12 p-10 text-center bg-white/5 border border-lime-400/20 rounded-3xl relative overflow-hidden" id="processingCard">
                <div class="absolute inset-0 bg-lime-400/5 animate-pulse"></div>
                <div class="relative z-10">
                    <i class="fas fa-spinner fa-spin text-5xl text-lime-400 mb-6 drop-shadow-lg" id="spinnerIcon"></i>
                    <h3 class="text-3xl font-black text-white mb-4 tracking-tight" id="statusTitle">Procesando tus entradas...</h3>
                    <p class="text-gray-400 max-w-xl mx-auto mb-8 text-lg" id="statusText">Nuestros sistemas están emitiendo tus códigos QR en este mismo instante. Debido a la alta demanda, están en cola y te llegarán al correo electrónico en unos segundos.</p>
                    <div class="flex justify-center gap-4">
                        <button onclick="checkStatus()" class="btn-modern btn-lime text-sm py-4 px-10 font-bold tracking-wide" id="checkBtn">
                            <i class="fas fa-sync-alt mr-2"></i> Verificar estado
                        </button>
                        <button onclick="window.location.reload()" class="btn-modern bg-gray-100 text-gray-800 text-sm py-4 px-10 font-bold tracking-wide">
                            <i class="fas fa-redo mr-2"></i> Recargar
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-4" id="lastCheck"></p>
                </div>
            </div>

            <script>
                // Polling automático cada 1 segundo (más rápido para mejor UX)
                let pollInterval;
                let maxAttempts = 30; // 30 * 1s = 30 segundos máximo
                let attempt = 0;

                function checkStatus() {
                    // Construir URL completa con todos los parámetros
                    const urlParams = new URLSearchParams(window.location.search);
                    const checkUrl = window.location.pathname + '?' + urlParams.toString() + '&check_status=1';

                    fetch(checkUrl)
                        .then(r => r.json())
                        .then(data => {
                            attempt++;
                            document.getElementById('lastCheck').textContent = 'Verificando... (' + attempt + '/' + maxAttempts + ') - ' + new Date().toLocaleTimeString();

                            if (data.ready && data.count > 0) {
                                // Tickets listos - recargar para mostrar
                                clearInterval(pollInterval);
                                document.getElementById('statusTitle').textContent = '¡' + data.count + ' Tickets Generados!';
                                document.getElementById('statusText').textContent = 'Hemos generado tus ' + data.count + ' entradas correctamente. Recargando para mostrar...';
                                document.getElementById('spinnerIcon').className = 'fas fa-check text-5xl text-lime-400 mb-6 drop-shadow-lg';
                                document.getElementById('checkBtn').style.display = 'none';
                                setTimeout(() => window.location.reload(), 1000);
                            } else if (data.error) {
                                // Error - mostrar mensaje
                                clearInterval(pollInterval);
                                document.getElementById('statusTitle').textContent = 'Error en el procesamiento';
                                document.getElementById('statusText').textContent = data.error;
                                document.getElementById('spinnerIcon').className = 'fas fa-times text-5xl text-red-400 mb-6 drop-shadow-lg';
                                document.getElementById('checkBtn').style.display = 'none';
                            } else if (attempt >= maxAttempts) {
                                // Timeout - pero seguir intentando
                                clearInterval(pollInterval);
                                document.getElementById('statusTitle').textContent = 'Aún procesando...';
                                document.getElementById('statusText').textContent = 'Toma más tiempo de lo esperado. Haz click en "Verificar estado" para comprobar manualmente.';
                                document.getElementById('spinnerIcon').className = 'fas fa-clock text-5xl text-yellow-400 mb-6 drop-shadow-lg';
                            }
                        })
                        .catch(err => {
                            console.error('Error checking status:', err);
                            document.getElementById('lastCheck').textContent = 'Error de conexión. Reintentando...';
                        });
                }

                // Iniciar polling automático al cargar
                document.addEventListener('DOMContentLoaded', () => {
                    checkStatus(); // Primera verificación inmediata
                    pollInterval = setInterval(checkStatus, 1000); // 1 segundo
                });
            </script>
        <?php else: ?>
            <!-- Tickets Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                <?php foreach ($purchase['tickets'] as $index => $ticket): ?>
                    <div class="ticket-main-card shadow-2xl animate-ticket">
                        <!-- Top Section: Image -->
                        <?php if ($imgUrl): ?>
                            <div class="p-2">
                                 <img src="<?php echo $imgUrl; ?>" class="ticket-image rounded-2xl shadow-lg" alt="">
                            </div>
                        <?php else: ?>
                            <div class="ticket-image bg-gray-200 flex items-center justify-center text-gray-400">
                                 <i class="fas fa-ticket-alt text-5xl"></i>
                            </div>
                        <?php endif; ?>

                        <div class="ticket-content">
                            <h3 class="text-2xl font-bold text-center mb-6 leading-tight"><?php echo htmlspecialchars($purchase['event_title']); ?></h3>
                            
                            <div class="grid grid-cols-2 gap-y-6 gap-x-4 mb-2">
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">FECHA</p>
                                    <p class="text-sm font-bold text-gray-800"><?php echo formatDate($eventData['date_event'] ?? date('Y-m-d'), 'd M, Y'); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">HORA</p>
                                    <p class="text-sm font-bold text-gray-800">19:30 PM</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">LUGAR</p>
                                    <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($eventData['location'] ?? 'Auditorio Principal'); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-1">TIPO</p>
                                    <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($ticket['type_name'] ?? 'General'); ?></p>
                                </div>
                            </div>

                            <div class="ticket-divider"></div>

                            <div class="flex flex-col items-center">
                                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-none mb-4">ASISTENTE</p>
                                <p class="text-lg font-bold text-gray-900 mb-6"><?php echo htmlspecialchars($ticket['name']); ?></p>
                                
                                <!-- QR Visual -->
                                <div class="p-2 border-2 border-dashed border-gray-200 rounded-2xl mb-4 bg-white">
                                    <?php 
                                    $qrWebPath = SITE_URL . '/qrcodes/' . basename($ticket['qr_path']);
                                    ?>
                                    <img src="<?php echo $qrWebPath; ?>" alt="QR" class="w-32 h-32 contrast-125">
                                </div>
                                <p class="text-xs font-mono text-gray-400 tracking-tighter uppercase"><?php echo $ticket['code']; ?></p>
                            </div>
                        </div>

                        <!-- Action Floating Bar -->
                        <div class="flex flex-col gap-2 p-6 pt-0">
                            <button onclick="shareIndividualTicket('<?php echo $ticket['code']; ?>', '<?php echo htmlspecialchars($purchase['event_title']); ?>')" 
                                    class="btn-modern btn-lime w-full text-xs py-3">
                                <i class="fab fa-whatsapp mr-2"></i> Compartir Ticket
                            </button>
                            <a href="ticket.php?code=<?php echo $ticket['code']; ?>" target="_blank"
                               class="btn-modern bg-gray-100 text-gray-800 w-full text-xs py-3">
                                <i class="fas fa-external-link-alt mr-2"></i> Ver Ticket Online
                            </a>
                            <a href="wallet.php?ticket_code=<?php echo $ticket['code']; ?>" 
                               class="btn-modern bg-black text-white w-full text-xs py-3 hover:bg-gray-900 border border-gray-800 transition-colors shadow-lg">
                                <i class="fab fa-apple mr-2 text-lg"></i> Añadir a Wallet
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Botón Volver al Inicio -->
            <div class="text-center mt-8">
                <a href="index.php" class="btn-modern btn-lime inline-flex items-center gap-2 px-8 py-4 text-base font-bold">
                    <i class="fas fa-home"></i>
                    Volver al Inicio
                </a>
            </div>
        <?php endif; ?>

        <!-- Spacer para el footer -->
        <div class="h-12"></div>
    </div>

    <?php require_once '../includes/partials/footer.php'; ?>

    <script>
        function shareOnWhatsApp() {
            const eventTitle = <?php echo json_encode($purchase['event_title']); ?>;
            const phone = <?php echo json_encode(preg_replace('/[^0-9]/', '', $purchase['phone'] ?? '')); ?>;
            const firstTicket = <?php echo json_encode($purchase['tickets'][0]['code'] ?? ''); ?>;
            const ticketUrl = firstTicket ? `<?php echo SITE_URL; ?>/ticket.php?code=${firstTicket}` : `<?php echo SITE_URL; ?>`;
            
            const message = `🎉 ¡Hola! Aquí tienes tus entradas para "${eventTitle}"!\n\n` +
                          `Puedes ver tu entrada principal aquí:\n${ticketUrl}\n\n` +
                          `¡Gracias por tu compra! 🎪`;
            window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
            
            const statusDiv = document.getElementById('whatsappStatus');
            if (statusDiv) {
                statusDiv.innerHTML = '<i class="fas fa-check text-lime-400 mr-2"></i> Enviado correctamente';
            }
        }

        function shareIndividualTicket(code, eventTitle) {
            const ticketUrl = `<?php echo SITE_URL; ?>/ticket.php?code=${code}`;
            const phone = <?php echo json_encode(preg_replace('/[^0-9]/', '', $purchase['phone'] ?? '')); ?>;
            const message = `🎫 Aquí tienes tu entrada para "${eventTitle}"\n\n` +
                          `Código: ${code}\n` +
                          `Presenta el QR al llegar:\n${ticketUrl}`;
            window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
        }

        // Auto-trigger WhatsApp after 2 seconds (ONLY IF NOT ASYNC)
        <?php if (!$isAsync): ?>
        document.addEventListener('DOMContentLoaded', () => {
             setTimeout(() => {
                 shareOnWhatsApp();
             }, 2000);
        });
        <?php endif; ?>
    </script>
</body>
</html>
