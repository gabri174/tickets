<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

checkAdminSession();

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$db = new Database();

$adminRole = $_SESSION['admin_role'] ?? '';
$adminId = $adminRole === 'superadmin' ? null : (int) ($_SESSION['admin_id'] ?? 0);

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$search = trim(cleanInput($_GET['search'] ?? ''));
$eventId = max(0, (int) ($_GET['event_id'] ?? 0));
$message = trim(cleanInput($_GET['message'] ?? ''));
$error = trim(cleanInput($_GET['error'] ?? ''));

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectTickets(string $message = '', string $error = ''): void {
    $params = [];
    if ($message !== '') $params['message'] = $message;
    if ($error !== '') $params['error'] = $error;
    header('Location: tickets.php' . ($params ? '?' . http_build_query($params) : ''));
    exit();
}

function validStatus(string $status): bool {
    return in_array($status, ['valid', 'used', 'cancelled'], true);
}

function safeText(string $value, int $max = 190): string {
    $value = trim(cleanInput($value));
    return mb_substr($value, 0, $max);
}

function safeEmail(string $value): string {
    return mb_strtolower(trim(cleanInput($value)));
}

function safePhone(string $value): string {
    $value = trim(cleanInput($value));
    return preg_replace('/[^\d+\-\s()]/', '', $value);
}

function safeZip(string $value): string {
    return preg_replace('/[^A-Za-z0-9\- ]/', '', trim(cleanInput($value)));
}

function csvSafe($value): string {
    $value = (string) $value;
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

function adminRateKey(): string {
    return 'admin_tickets_' . ($_SESSION['admin_id'] ?? '0');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirectTickets('', 'Error de seguridad. Inténtalo de nuevo.');
    }

    if (function_exists('checkRateLimit') && !checkRateLimit(adminRateKey(), 40, 300)) {
        redirectTickets('', 'Demasiadas acciones en poco tiempo. Espera un momento.');
    }

    $action = safeText($_POST['action'] ?? '', 50);

    if ($action === 'update_status') {
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $status = safeText($_POST['status'] ?? '', 20);

        if ($ticketId <= 0 || !validStatus($status)) {
            redirectTickets('', 'Datos inválidos para cambiar el estado.');
        }

        if ($db->updateTicketStatus($ticketId, $status, $adminId)) {
            redirectTickets('Estado del ticket actualizado correctamente.');
        }

        redirectTickets('', 'No se pudo actualizar el estado del ticket.');
    }

    if ($action === 'edit_ticket') {
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $name = safeText($_POST['attendee_name'] ?? '', 120);
        $email = safeEmail($_POST['attendee_email'] ?? '');
        $phone = safePhone($_POST['attendee_phone'] ?? '');

        if ($ticketId <= 0 || $name === '' || $email === '' || !validateEmail($email)) {
            redirectTickets('', 'Datos inválidos del asistente.');
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,40}$/', $phone)) {
            redirectTickets('', 'El teléfono no tiene un formato válido.');
        }

        if ($db->updateTicketData($ticketId, $name, $email, $phone, $adminId)) {
            redirectTickets('Datos del ticket actualizados correctamente.');
        }

        redirectTickets('', 'No se pudo actualizar el ticket.');
    }

    if ($action === 'resend') {
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);

        if ($ticketId <= 0) {
            redirectTickets('', 'Ticket inválido.');
        }

        try {
            $ticket = $db->getTicketById($ticketId);
            if (!$ticket) {
                redirectTickets('', 'Ticket no encontrado.');
            }

            $event = $db->getEventById((int) $ticket['event_id'], $adminId);
            if (!$event) {
                redirectTickets('', 'No tienes permiso para acceder a ese ticket.');
            }

            $recipient = safeEmail($ticket['attendee_email'] ?? '');
            if ($recipient === '' || !validateEmail($recipient)) {
                redirectTickets('', 'El ticket no tiene un email válido.');
            }

            $ticketsData = [[
                'code' => (string) ($ticket['ticket_code'] ?? ''),
                'qr_path' => (string) ($ticket['qr_code_path'] ?? ''),
                'name' => (string) ($ticket['attendee_name'] ?? ''),
                'email' => $recipient,
                'type_name' => (string) ($ticket['type_name'] ?? '')
            ]];

            $subject = 'Reenvío de tu ticket para ' . ($event['title'] ?? 'tu evento');
            $emailBody = generateEmailBody($event, $ticketsData, (string) ($ticket['attendee_name'] ?? ''), (float) ($event['price'] ?? 0));
            $pdfContent = generateTicketPDF($event, $ticketsData, (float) ($event['price'] ?? 0));

            if (sendTicketEmail($recipient, $subject, $emailBody, $pdfContent)) {
                redirectTickets('Ticket reenviado correctamente.');
            }

            redirectTickets('', 'No se pudo reenviar el ticket.');
        } catch (Throwable $e) {
            error_log('admin/tickets resend error: ' . $e->getMessage());
            redirectTickets('', 'Se produjo un error al reenviar el ticket.');
        }
    }

    if ($action === 'create_manual') {
        $eventIdPost = (int) ($_POST['event_id'] ?? 0);
        $ticketTypeId = !empty($_POST['ticket_type_id']) ? (int) $_POST['ticket_type_id'] : null;
        $name = safeText($_POST['name'] ?? '', 120);
        $email = safeEmail($_POST['email'] ?? '');
        $phone = safePhone($_POST['phone'] ?? '');
        $zipCode = safeZip($_POST['zip_code'] ?? '');

        if ($eventIdPost <= 0 || $name === '' || $email === '' || !validateEmail($email)) {
            redirectTickets('', 'Datos inválidos para crear el ticket manual.');
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,40}$/', $phone)) {
            redirectTickets('', 'El teléfono no tiene un formato válido.');
        }

        if ($zipCode !== '' && !preg_match('/^[A-Za-z0-9\- ]{3,12}$/', $zipCode)) {
            redirectTickets('', 'El código postal no es válido.');
        }

        $event = $db->getEventById($eventIdPost, $adminId);
        if (!$event) {
            redirectTickets('', 'No puedes crear tickets para ese evento.');
        }

        try {
            $purchaseData = [
                'event_id' => $eventIdPost,
                'ticket_type_id' => $ticketTypeId,
                'quantity' => 1,
                'attendees' => [[
                    'name' => $name,
                    'surname' => '',
                    'email' => $email
                ]],
                'phone' => $phone,
                'zip_code' => $zipCode,
                'total_price' => 0
            ];

            $result = completePurchase($purchaseData, $db);

            if ($result) {
                redirectTickets('Ticket manual creado correctamente.');
            }

            redirectTickets('', 'No se pudo crear el ticket manual.');
        } catch (Throwable $e) {
            error_log('admin/tickets create_manual error: ' . $e->getMessage());
            redirectTickets('', 'Se produjo un error al crear el ticket manual.');
        }
    }

    redirectTickets('', 'Acción no válida.');
}

if (isset($_GET['action']) && $_GET['action'] === 'download_pdf') {
    $ticketCode = safeText($_GET['code'] ?? '', 100);

    if ($ticketCode === '') {
        http_response_code(400);
        exit('Solicitud inválida');
    }

    $ticket = $db->getTicketByCode($ticketCode);
    if (!$ticket) {
        http_response_code(404);
        exit('Ticket no encontrado');
    }

    $event = $db->getEventById((int) $ticket['event_id'], $adminId);
    if (!$event) {
        http_response_code(403);
        exit('Acceso denegado');
    }

    require_once ROOT_PATH . '/includes/classes/TicketPDF.php';

    $ticketsForPdf = [[
        'code' => (string) ($ticket['ticket_code'] ?? ''),
        'qr_path' => (string) ($ticket['qr_code_path'] ?? '')
    ]];

    $pdf = new TicketPDF($event, $ticketsForPdf, (string) ($ticket['attendee_name'] ?? ''), (float) ($event['price'] ?? 0));
    $pdfContent = $pdf->generatePDF();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="ticket_' . rawurlencode((string) $ticket['ticket_code']) . '.pdf"');
    header('X-Content-Type-Options: nosniff');
    echo $pdfContent;
    exit();
}

$tickets = $db->getAllTickets($adminId);
$events = $db->getAllEvents($adminId);

if ($search !== '' || $eventId > 0) {
    $tickets = array_values(array_filter($tickets, function ($ticket) use ($search, $eventId) {
        $matchSearch =
            $search === '' ||
            stripos((string) ($ticket['ticket_code'] ?? ''), $search) !== false ||
            stripos((string) ($ticket['attendee_name'] ?? ''), $search) !== false ||
            stripos((string) ($ticket['attendee_email'] ?? ''), $search) !== false;

        $matchEvent = $eventId === 0 || (int) ($ticket['event_id'] ?? 0) === $eventId;

        return $matchSearch && $matchEvent;
    }));
}

if (isset($_GET['export']) && $_GET['export'] === 'true') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tickets_' . date('Y-m-d') . '.csv"');
    header('X-Content-Type-Options: nosniff');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, ['ID', 'Código Ticket', 'Evento', 'Nombre Asistente', 'Email', 'Teléfono', 'Fecha Compra', 'Estado']);

    foreach ($tickets as $ticket) {
        fputcsv($output, [
            csvSafe($ticket['id'] ?? ''),
            csvSafe($ticket['ticket_code'] ?? ''),
            csvSafe($ticket['event_title'] ?? ''),
            csvSafe($ticket['attendee_name'] ?? ''),
            csvSafe($ticket['attendee_email'] ?? ''),
            csvSafe($ticket['attendee_phone'] ?? ''),
            csvSafe(formatDate($ticket['purchase_date'] ?? '')),
            csvSafe($ticket['status'] ?? '')
        ]);
    }

    fclose($output);
    exit();
}

$total = count($tickets);
$pagination = paginate($total, $page, $limit);
$displayTickets = array_slice($tickets, $pagination['offset'], $limit);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets & Ventas - Admin</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{background:#0A0E14;color:#fff;font-family:'Outfit',sans-serif;min-height:100vh}
        .glass-sidebar{background:rgba(255,255,255,.02);backdrop-filter:blur(20px);border-right:1px solid rgba(255,255,255,.05)}
        .glass-card{background:rgba(255,255,255,.03);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08)}
        .nav-link{transition:all .2s ease;position:relative}
        .nav-link.active{background:rgba(218,251,113,.1);color:#DAFB71}
        .nav-link.active:before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:#DAFB71;border-radius:0 4px 4px 0}
        .text-gradient{background:linear-gradient(to right,#DAFB71,#60A5FA);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        input,select{background:rgba(255,255,255,.05)!important;border:1px solid rgba(255,255,255,.1)!important;color:white!important}
        input::placeholder{color:rgba(255,255,255,.3)!important}
        input:focus,select:focus{border-color:rgba(218,251,113,.5)!important;box-shadow:0 0 15px rgba(218,251,113,.1)!important}
        select option{background:#111827;color:white}
        ::-webkit-scrollbar{width:6px}
        ::-webkit-scrollbar-track{background:transparent}
        ::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:10px}
        tr{border-bottom:1px solid rgba(255,255,255,.05)}
        tr:hover{background:rgba(255,255,255,.02)}
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen overflow-x-hidden">
    <?php include '../../includes/templates/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto relative p-4 lg:p-0">
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-lime-400/5 blur-[120px] rounded-full pointer-events-none"></div>

        <header class="sticky top-0 z-10 bg-[#0A0E14]/80 backdrop-blur-xl border-b border-white/5 px-8 h-20 flex items-center">
            <div class="flex flex-1 justify-between items-center gap-4">
                <div>
                    <h2 class="text-2xl font-black tracking-tighter">Ventas <span class="text-gradient">Tickets</span></h2>
                    <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Historial de compras y gestión de accesos</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="openManualTicketModal()" class="flex items-center gap-2 px-5 py-2.5 bg-blue-500 text-white rounded-xl font-black text-xs hover:shadow-[0_0_20px_rgba(59,130,246,0.3)] transition-all">
                        <i class="fas fa-plus"></i>Nuevo Ticket Manual
                    </button>
                    <a href="?export=true<?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $eventId > 0 ? '&event_id=' . $eventId : ''; ?>"
                       class="flex items-center gap-2 px-5 py-2.5 bg-lime-400 text-black rounded-xl font-black text-xs hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">
                        <i class="fas fa-file-csv"></i>Exportar CSV
                    </a>
                </div>
            </div>
        </header>

        <div class="p-8 relative z-10">
            <?php if ($message !== ''): ?>
                <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 shadow-lg">
                    <i class="fas fa-check-circle"></i><span class="font-bold text-sm"><?php echo h($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 shadow-lg">
                    <i class="fas fa-exclamation-circle"></i><span class="font-bold text-sm"><?php echo h($error); ?></span>
                </div>
            <?php endif; ?>

            <div class="glass-card rounded-[2rem] p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Buscar</label>
                        <input type="text" name="search" placeholder="Código, nombre o email..." class="w-full px-5 py-3.5 rounded-xl outline-none transition-all text-sm" value="<?php echo h($search); ?>">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Evento</label>
                        <select name="event_id" class="w-full px-5 py-3.5 rounded-xl outline-none transition-all text-sm">
                            <option value="0">Todos los eventos</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo (int) $event['id']; ?>" <?php echo $eventId === (int) $event['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($event['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 py-3.5 bg-lime-400 text-black rounded-xl font-black text-xs hover:shadow-[0_0_20px_rgba(218,251,113,0.25)] transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-search"></i>Filtrar
                        </button>
                        <?php if ($search !== '' || $eventId > 0): ?>
                            <a href="tickets.php" class="py-3.5 px-4 rounded-xl border border-white/10 text-gray-400 hover:text-white hover:bg-white/5 transition-all">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="glass-card rounded-[1.5rem] p-6">
                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Total</p>
                    <p class="text-3xl font-black tracking-tighter"><?php echo $total; ?></p>
                </div>
                <div class="glass-card rounded-[1.5rem] p-6">
                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Válidos</p>
                    <p class="text-3xl font-black tracking-tighter text-lime-400"><?php echo count(array_filter($tickets, fn($t) => ($t['status'] ?? '') === 'valid')); ?></p>
                </div>
                <div class="glass-card rounded-[1.5rem] p-6">
                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Utilizados</p>
                    <p class="text-3xl font-black tracking-tighter text-yellow-400"><?php echo count(array_filter($tickets, fn($t) => ($t['status'] ?? '') === 'used')); ?></p>
                </div>
                <div class="glass-card rounded-[1.5rem] p-6">
                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Cancelados</p>
                    <p class="text-3xl font-black tracking-tighter text-red-400"><?php echo count(array_filter($tickets, fn($t) => ($t['status'] ?? '') === 'cancelled')); ?></p>
                </div>
            </div>

            <div class="glass-card rounded-[2rem] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th class="text-left py-4 px-6 text-[10px] font-black text-gray-500 uppercase tracking-widest">Código</th>
                                <th class="text-left py-4 px-6 text-[10px] font-black text-gray-500 uppercase tracking-widest">Evento</th>
                                <th class="text-left py-4 px-6 text-[10px] font-black text-gray-500 uppercase tracking-widest">Asistente</th>
                                <th class="text-left py-4 px-6 text-[10px] font-black text-gray-500 uppercase tracking-widest">Contacto</th>
                                <th class="text-left py-4 px-6 text-[10px] font-black text-gray-500 uppercase tracking-widest">Fecha</th>
                                <th class="text-left py-4 px-6 text-[10px] font-black text-gray-500 uppercase tracking-widest">Estado</th>
                                <th class="text-left py-4 px-6 text-[10px] font-black text-gray-500 uppercase tracking-widest">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($displayTickets)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-16 text-gray-600">
                                        <i class="fas fa-ticket-alt text-4xl mb-4 block opacity-30"></i>
                                        <p class="font-bold text-sm">No se encontraron tickets</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($displayTickets as $ticket): ?>
                                    <tr>
                                        <td class="py-4 px-6">
                                            <code class="text-xs bg-white/5 border border-white/10 px-2 py-1 rounded-lg font-mono text-lime-400">
                                                <?php echo h($ticket['ticket_code'] ?? ''); ?>
                                            </code>
                                        </td>
                                        <td class="py-4 px-6"><div class="text-sm font-bold"><?php echo h($ticket['event_title'] ?? ''); ?></div></td>
                                        <td class="py-4 px-6"><div class="text-sm font-bold"><?php echo h($ticket['attendee_name'] ?? ''); ?></div></td>
                                        <td class="py-4 px-6">
                                            <div class="text-xs text-gray-300"><?php echo h($ticket['attendee_email'] ?? ''); ?></div>
                                            <?php if (!empty($ticket['attendee_phone'])): ?>
                                                <div class="text-[10px] text-gray-500"><?php echo h($ticket['attendee_phone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6"><div class="text-xs text-gray-400"><?php echo h(formatDate($ticket['purchase_date'] ?? '')); ?></div></td>
                                        <td class="py-4 px-6">
                                            <?php $status = $ticket['status'] ?? ''; ?>
                                            <?php if ($status === 'valid'): ?>
                                                <span class="bg-lime-400/10 text-lime-400 border border-lime-400/20 text-[10px] font-black px-3 py-1 rounded-full">Válido</span>
                                            <?php elseif ($status === 'used'): ?>
                                                <span class="bg-yellow-400/10 text-yellow-400 border border-yellow-400/20 text-[10px] font-black px-3 py-1 rounded-full">Utilizado</span>
                                            <?php else: ?>
                                                <span class="bg-red-400/10 text-red-400 border border-red-400/20 text-[10px] font-black px-3 py-1 rounded-full">Cancelado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-3">
                                                <a href="../ticket.php?code=<?php echo urlencode((string) ($ticket['ticket_code'] ?? '')); ?>" target="_blank" rel="noopener noreferrer" class="text-gray-500 hover:text-blue-400 transition-colors" title="Ver ticket">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?action=download_pdf&amp;code=<?php echo urlencode((string) ($ticket['ticket_code'] ?? '')); ?>" class="text-gray-500 hover:text-red-400 transition-colors" title="Descargar PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                                <button type="button" onclick="resendTicket(<?php echo (int) ($ticket['id'] ?? 0); ?>)" class="text-gray-500 hover:text-lime-400 transition-colors" title="Reenviar ticket">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                                <button type="button" onclick='openEditModal(<?php echo json_encode($ticket, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' class="text-gray-500 hover:text-blue-400 transition-colors" title="Editar datos">
                                                    <i class="fas fa-user-edit"></i>
                                                </button>
                                                <button type="button" onclick='openStatusModal(<?php echo (int) ($ticket["id"] ?? 0); ?>, <?php echo json_encode((string) ($ticket["status"] ?? ""), JSON_UNESCAPED_UNICODE); ?>)' class="text-gray-500 hover:text-yellow-400 transition-colors" title="Cambiar estado">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                                <button type="button" onclick='copyTicketCode(<?php echo json_encode((string) ($ticket["ticket_code"] ?? ""), JSON_UNESCAPED_UNICODE); ?>)' class="text-gray-500 hover:text-white transition-colors" title="Copiar código">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
                    <div class="flex justify-between items-center px-6 py-5 border-t border-white/5">
                        <div class="text-xs text-gray-500 font-bold">
                            Mostrando <?php echo (int) $pagination['offset'] + 1; ?>–<?php echo min((int) $pagination['offset'] + $limit, $total); ?> de <?php echo $total; ?> tickets
                        </div>
                        <div class="flex gap-2">
                            <?php if (!empty($pagination['has_prev'])): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $eventId > 0 ? '&event_id=' . $eventId : ''; ?>" class="px-3 py-1.5 rounded-lg border border-white/10 text-gray-400 hover:text-white hover:bg-white/5 transition-all text-xs font-bold">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min((int) $pagination['total_pages'], $page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $eventId > 0 ? '&event_id=' . $eventId : ''; ?>"
                                   class="px-3 py-1.5 rounded-lg text-xs font-black transition-all <?php echo $i === $page ? 'bg-lime-400 text-black' : 'border border-white/10 text-gray-400 hover:text-white hover:bg-white/5'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if (!empty($pagination['has_next'])): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $eventId > 0 ? '&event_id=' . $eventId : ''; ?>" class="px-3 py-1.5 rounded-lg border border-white/10 text-gray-400 hover:text-white hover:bg-white/5 transition-all text-xs font-bold">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="statusModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden z-50 flex items-center justify-center">
        <div class="glass-card rounded-[2rem] w-full max-w-md p-8 border border-white/10">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-black tracking-tighter">Cambiar Estado</h3>
                <button onclick="closeStatusModal()" class="w-8 h-8 rounded-xl bg-white/5 flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-all">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="ticket_id" id="statusTicketId">

                <div class="mb-8 space-y-1">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Nuevo Estado</label>
                    <select name="status" id="statusSelect" class="w-full px-5 py-4 rounded-xl outline-none transition-all">
                        <option value="valid">Válido</option>
                        <option value="used">Utilizado</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeStatusModal()" class="flex-1 py-4 rounded-xl border border-white/10 text-gray-400 hover:text-white hover:bg-white/5 transition-all font-black text-xs">Cancelar</button>
                    <button type="submit" class="flex-1 py-4 bg-lime-400 text-black rounded-xl font-black text-xs hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">Actualizar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden z-50 flex items-center justify-center">
        <div class="glass-card rounded-[2rem] w-full max-w-md p-8 border border-white/10">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-black tracking-tighter">Editar Asistente</h3>
                <button onclick="closeEditModal()" class="w-8 h-8 rounded-xl bg-white/5 flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-all">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_ticket">
                <input type="hidden" name="ticket_id" id="editTicketId">

                <div class="space-y-4 mb-8">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Nombre Completo</label>
                        <input type="text" name="attendee_name" id="editAttendeeName" required maxlength="120" class="w-full px-5 py-4 rounded-xl outline-none transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Correo Electrónico</label>
                        <input type="email" name="attendee_email" id="editAttendeeEmail" required maxlength="190" class="w-full px-5 py-4 rounded-xl outline-none transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Teléfono</label>
                        <input type="text" name="attendee_phone" id="editAttendeePhone" maxlength="40" class="w-full px-5 py-4 rounded-xl outline-none transition-all">
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeEditModal()" class="flex-1 py-4 rounded-xl border border-white/10 text-gray-400 hover:text-white hover:bg-white/5 transition-all font-black text-xs">Cancelar</button>
                    <button type="submit" class="flex-1 py-4 bg-lime-400 text-black rounded-xl font-black text-xs hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <div id="manualTicketModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeManualTicketModal()"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg p-4">
            <div class="glass-card rounded-[2.5rem] overflow-hidden shadow-2xl">
                <div class="bg-white/5 px-8 py-6 border-b border-white/5 flex justify-between items-center">
                    <h3 class="text-xl font-black tracking-tight"><i class="fas fa-plus-circle text-blue-400 mr-2"></i>Crear Ticket <span class="text-gradient">Manual</span></h3>
                    <button onclick="closeManualTicketModal()" class="text-gray-500 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
                </div>

                <form action="tickets.php" method="POST" class="p-8 space-y-6">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create_manual">

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Evento</label>
                        <select name="event_id" required onchange="loadTicketTypes(this.value)" class="w-full px-5 py-3.5 rounded-xl outline-none transition-all text-sm bg-white/5 border border-white/10">
                            <option value="">Selecciona un evento</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo (int) $event['id']; ?>"><?php echo h($event['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="ticketTypeContainer" class="space-y-2 hidden">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Tipo de Entrada</label>
                        <select name="ticket_type_id" id="manualTicketTypeId" class="w-full px-5 py-3.5 rounded-xl outline-none transition-all text-sm bg-white/5 border border-white/10"></select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Nombre</label>
                            <input type="text" name="name" required maxlength="120" placeholder="Ej. Juan" class="w-full px-5 py-3.5 rounded-xl outline-none border border-white/10 bg-white/5 text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Email</label>
                            <input type="email" name="email" required maxlength="190" placeholder="email@ejemplo.com" class="w-full px-5 py-3.5 rounded-xl outline-none border border-white/10 bg-white/5 text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Teléfono</label>
                            <input type="tel" name="phone" maxlength="40" placeholder="+34 600 000 000" class="w-full px-5 py-3.5 rounded-xl outline-none border border-white/10 bg-white/5 text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Código Postal</label>
                            <input type="text" name="zip_code" maxlength="12" placeholder="Ej. 28001" class="w-full px-5 py-3.5 rounded-xl outline-none border border-white/10 bg-white/5 text-sm">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full py-4 bg-lime-400 text-black rounded-2xl font-black text-xs hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">
                            GENERAR Y ENVIAR TICKET
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showNotification(message, isError = false) {
            const n = document.createElement('div');
            n.className = 'fixed top-6 right-6 px-5 py-3 rounded-2xl shadow-lg z-50 font-black text-sm';
            n.className += isError ? ' bg-red-500 text-white' : ' bg-lime-400 text-black';
            n.textContent = message;
            document.body.appendChild(n);
            setTimeout(() => n.remove(), 2200);
        }

        function copyTicketCode(code) {
            navigator.clipboard.writeText(code).then(() => showNotification('Código copiado'));
        }

        function openStatusModal(id, currentStatus) {
            document.getElementById('statusTicketId').value = id;
            document.getElementById('statusSelect').value = currentStatus;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        function openEditModal(ticket) {
            document.getElementById('editTicketId').value = ticket.id || '';
            document.getElementById('editAttendeeName').value = ticket.attendee_name || '';
            document.getElementById('editAttendeeEmail').value = ticket.attendee_email || '';
            document.getElementById('editAttendeePhone').value = ticket.attendee_phone || '';
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function resendTicket(id) {
            if (!confirm('¿Estás seguro de que deseas reenviar este ticket al correo del asistente?')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'tickets.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'resend';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'ticket_id';
            idInput.value = String(id);

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = <?php echo json_encode($_SESSION['csrf_token'] ?? '', JSON_UNESCAPED_UNICODE); ?>;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            form.appendChild(csrfInput);
            document.body.appendChild(form);
            form.submit();
        }

        function openManualTicketModal() {
            document.getElementById('manualTicketModal').classList.remove('hidden');
        }

        function closeManualTicketModal() {
            document.getElementById('manualTicketModal').classList.add('hidden');
        }

        function loadTicketTypes(eventId) {
            const container = document.getElementById('ticketTypeContainer');
            const select = document.getElementById('manualTicketTypeId');

            if (!eventId) {
                container.classList.add('hidden');
                select.innerHTML = '';
                return;
            }

            fetch('api_ticket_types.php?event_id=' + encodeURIComponent(eventId), {
                credentials: 'same-origin'
            })
            .then(r => r.ok ? r.json() : [])
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    select.innerHTML = '<option value="">Tipo General</option>';
                    data.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type.id;
                        option.textContent = `${type.name} - ${type.price}`;
                        select.appendChild(option);
                    });
                    container.classList.remove('hidden');
                } else {
                    container.classList.add('hidden');
                    select.innerHTML = '';
                }
            })
            .catch(() => {
                container.classList.add('hidden');
                select.innerHTML = '';
            });
        }

        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) closeStatusModal();
        });

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
    </script>
</body>
</html>
