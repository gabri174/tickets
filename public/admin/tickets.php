<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

checkAdminSession();

$db = new Database();

// Obtener parámetros de paginación y filtrado
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Obtener tickets
$adminId = ($_SESSION['admin_role'] === 'superadmin') ? null : $_SESSION['admin_id'];

// Procesar actualización de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $ticketId = intval($_POST['ticket_id']);
    $newStatus = cleanInput($_POST['status']);
    if (in_array($newStatus, ['valid', 'used', 'cancelled'])) {
        if ($db->updateTicketStatus($ticketId, $newStatus, $adminId)) {
            $message = "Estado del ticket actualizado correctamente.";
        } else {
            $error = "No se pudo actualizar el estado del ticket.";
        }
    }
}

// Procesar descarga de PDF
if (isset($_GET['action']) && $_GET['action'] === 'download_pdf' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $ticket = $db->getTicketByCode($_GET['code'] ?? ''); // O buscar por ID si existiera el método, usamos el código que es seguro
    if ($ticket) {
        // Verificar que el admin puede ver este ticket
        $event = $db->getEventById($ticket['event_id'], $adminId);
        if ($event) {
            require_once ROOT_PATH . '/includes/classes/TicketPDF.php';
            $tickets_for_pdf = [['code' => $ticket['ticket_code'], 'qr_path' => $ticket['qr_code_path']]];
            $pdf = new TicketPDF($event, $tickets_for_pdf, $ticket['attendee_name'], $event['price']);
            $pdfContent = $pdf->generatePDF();
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="ticket_' . $ticket['ticket_code'] . '.pdf"');
            echo $pdfContent;
            exit();
        }
    }
}

$tickets = $db->getAllTickets($adminId);
$message = $message ?? '';
$error = $error ?? '';
$events = $db->getAllEvents($adminId);

// Filtrar tickets
if ($search || $eventId) {
    $filteredTickets = [];
    foreach ($tickets as $ticket) {
        $matchSearch = empty($search) || 
                      stripos($ticket['ticket_code'], $search) !== false ||
                      stripos($ticket['attendee_name'], $search) !== false ||
                      stripos($ticket['attendee_email'], $search) !== false;
        
        $matchEvent = $eventId === 0 || $ticket['event_id'] == $eventId;
        
        if ($matchSearch && $matchEvent) {
            $filteredTickets[] = $ticket;
        }
    }
    $tickets = $filteredTickets;
}

// Paginación
$total = count($tickets);
$pagination = paginate($total, $page, $limit);
$displayTickets = array_slice($tickets, $pagination['offset'], $limit);

// Exportar a CSV
if (isset($_GET['export']) && $_GET['export'] === 'true') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tickets_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Cabeceras
    fputcsv($output, [
        'ID', 'Código Ticket', 'Evento', 'Nombre Asistente', 'Email', 'Teléfono', 
        'Fecha Compra', 'Estado'
    ]);
    
    // Datos
    foreach ($tickets as $ticket) {
        fputcsv($output, [
            $ticket['id'],
            $ticket['ticket_code'],
            $ticket['event_title'],
            $ticket['attendee_name'],
            $ticket['attendee_email'],
            $ticket['attendee_phone'],
            formatDate($ticket['purchase_date']),
            $ticket['status']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets & Ventas - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #0A0E14;
            color: white;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
        }
        .glass-sidebar {
            background: rgba(255,255,255,0.02);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255,255,255,0.05);
        }
        .glass-card {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .nav-link { transition: all 0.2s ease; position: relative; }
        .nav-link.active { background: rgba(218,251,113,0.1); color: #DAFB71; }
        .nav-link.active::before {
            content: ''; position: absolute; left: 0; top: 20%; bottom: 20%;
            width: 3px; background: #DAFB71; border-radius: 0 4px 4px 0;
        }
        .text-gradient {
            background: linear-gradient(to right, #DAFB71, #60A5FA);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        input, select {
            background: rgba(255,255,255,0.05) !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            color: white !important;
        }
        input::placeholder { color: rgba(255,255,255,0.3) !important; }
        input:focus, select:focus {
            border-color: rgba(218,251,113,0.5) !important;
            box-shadow: 0 0 15px rgba(218,251,113,0.1) !important;
        }
        select option { background: #111827; color: white; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        tr { border-bottom: 1px solid rgba(255,255,255,0.05); }
        tr:hover { background: rgba(255,255,255,0.02); }
    </style>
</head>
<body class="overflow-hidden">
<div class="flex h-screen">
    <!-- Sidebar -->
    <aside class="glass-sidebar w-72 flex flex-col z-20">
        <div class="p-8">
            <div class="flex items-center gap-3 mb-10">
                <div class="w-10 h-10 bg-lime-400 rounded-xl flex items-center justify-center shadow-lg shadow-lime-400/20">
                    <i class="fas fa-ticket-alt text-black text-xl"></i>
                </div>
                <span class="text-xl font-black tracking-tighter">TICKET<span class="text-lime-400">APP</span></span>
            </div>
            <nav class="space-y-2">
                <a href="dashboard.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5">
                    <i class="fas fa-grid-2 text-lg"></i><span>Dashboard</span>
                </a>
                <a href="events.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5">
                    <i class="fas fa-calendar-alt text-lg"></i><span>Gestionar Eventos</span>
                </a>
                <a href="tickets.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm">
                    <i class="fas fa-ticket-alt text-lg"></i><span>Ventas & Tickets</span>
                </a>
                <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                <a href="settings.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5">
                    <i class="fas fa-cog text-lg"></i><span>Configuración</span>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="mt-auto p-6 border-t border-white/5">
            <div class="glass-card p-4 rounded-2xl flex items-center gap-3">
                <div class="w-10 h-10 bg-white/5 rounded-full flex items-center justify-center border border-white/10">
                    <i class="fas fa-user-circle text-gray-400"></i>
                </div>
                <div class="flex-1 overflow-hidden">
                    <p class="text-xs font-black truncate"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                    <p class="text-[10px] text-gray-500 truncate"><?php echo htmlspecialchars($_SESSION['admin_email']); ?></p>
                </div>
                <a href="logout.php" class="text-gray-500 hover:text-red-400 transition-colors p-2">
                    <i class="fas fa-power-off"></i>
                </a>
            </div>
        </div>
    </aside>
        
    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto bg-[#0A0E14] relative">
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-lime-400/5 blur-[120px] rounded-full pointer-events-none"></div>

        <!-- Header -->
        <header class="sticky top-0 z-10 bg-[#0A0E14]/80 backdrop-blur-xl border-b border-white/5 px-8 h-20 flex items-center">
            <div class="flex flex-1 justify-between items-center">
                <div>
                    <h2 class="text-2xl font-black tracking-tighter">Ventas & <span class="text-gradient">Tickets</span></h2>
                    <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Historial de compras y gestión de accesos</p>
                </div>
                <a href="?export=true" class="flex items-center gap-2 px-5 py-2.5 bg-lime-400 text-black rounded-xl font-black text-xs hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">
                    <i class="fas fa-file-csv"></i>Exportar CSV
                </a>
            </div>
        </header>

        <div class="p-8 relative z-10">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 shadow-lg">
                    <i class="fas fa-check-circle"></i>
                    <span class="font-bold text-sm"><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 shadow-lg">
                    <i class="fas fa-exclamation-circle"></i>
                    <span class="font-bold text-sm"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="glass-card rounded-[2rem] p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Buscar</label>
                        <input type="text" name="search"
                               placeholder="Código, nombre o email..."
                               class="w-full px-5 py-3.5 rounded-xl outline-none transition-all text-sm"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Evento</label>
                        <select name="event_id" class="w-full px-5 py-3.5 rounded-xl outline-none transition-all text-sm">
                            <option value="">Todos los eventos</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>" <?php echo ($eventId == $event['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 py-3.5 bg-lime-400 text-black rounded-xl font-black text-xs hover:shadow-[0_0_20px_rgba(218,251,113,0.25)] transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-search"></i>Filtrar
                        </button>
                        <?php if ($search || $eventId): ?>
                            <a href="tickets.php" class="py-3.5 px-4 rounded-xl border border-white/10 text-gray-400 hover:text-white hover:bg-white/5 transition-all">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="glass-card rounded-[1.5rem] p-6">
                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Total</p>
                    <p class="text-3xl font-black tracking-tighter"><?php echo $total; ?></p>
                    <div class="mt-3 w-8 h-8 rounded-xl bg-blue-400/10 flex items-center justify-center text-blue-400">
                        <i class="fas fa-ticket-alt text-sm"></i>
                    </div>
                </div>
                <div class="glass-card rounded-[1.5rem] p-6">
                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Válidos</p>
                    <p class="text-3xl font-black tracking-tighter text-lime-400">
                        <?php echo count(array_filter($tickets, fn($t) => $t['status'] === 'valid')); ?>
                    </p>
                    <div class="mt-3 w-8 h-8 rounded-xl bg-lime-400/10 flex items-center justify-center text-lime-400">
                        <i class="fas fa-check text-sm"></i>
                    </div>
                </div>
                <div class="glass-card rounded-[1.5rem] p-6">
                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Utilizados</p>
                    <p class="text-3xl font-black tracking-tighter text-yellow-400">
                        <?php echo count(array_filter($tickets, fn($t) => $t['status'] === 'used')); ?>
                    </p>
                    <div class="mt-3 w-8 h-8 rounded-xl bg-yellow-400/10 flex items-center justify-center text-yellow-400">
                        <i class="fas fa-clock text-sm"></i>
                    </div>
                </div>
                <div class="glass-card rounded-[1.5rem] p-6">
                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Cancelados</p>
                    <p class="text-3xl font-black tracking-tighter text-red-400">
                        <?php echo count(array_filter($tickets, fn($t) => $t['status'] === 'cancelled')); ?>
                    </p>
                    <div class="mt-3 w-8 h-8 rounded-xl bg-red-400/10 flex items-center justify-center text-red-400">
                        <i class="fas fa-ban text-sm"></i>
                    </div>
                </div>
            </div>

            <!-- Tickets Table -->
            <div class="glass-card rounded-[2rem] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-white/5">
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
                                                <?php echo htmlspecialchars($ticket['ticket_code']); ?>
                                            </code>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="text-sm font-bold"><?php echo htmlspecialchars($ticket['event_title']); ?></div>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="text-sm font-bold"><?php echo htmlspecialchars($ticket['attendee_name']); ?></div>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="text-xs text-gray-300"><?php echo htmlspecialchars($ticket['attendee_email']); ?></div>
                                            <?php if ($ticket['attendee_phone']): ?>
                                                <div class="text-[10px] text-gray-500"><?php echo htmlspecialchars($ticket['attendee_phone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="text-xs text-gray-400"><?php echo formatDate($ticket['purchase_date']); ?></div>
                                        </td>
                                        <td class="py-4 px-6">
                                            <?php if ($ticket['status'] === 'valid'): ?>
                                                <span class="bg-lime-400/10 text-lime-400 border border-lime-400/20 text-[10px] font-black px-3 py-1 rounded-full">Válido</span>
                                            <?php elseif ($ticket['status'] === 'used'): ?>
                                                <span class="bg-yellow-400/10 text-yellow-400 border border-yellow-400/20 text-[10px] font-black px-3 py-1 rounded-full">Utilizado</span>
                                            <?php else: ?>
                                                <span class="bg-red-400/10 text-red-400 border border-red-400/20 text-[10px] font-black px-3 py-1 rounded-full">Cancelado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-3">
                                                <a href="../ticket.php?code=<?php echo urlencode($ticket['ticket_code']); ?>"
                                                   target="_blank"
                                                   class="text-gray-500 hover:text-blue-400 transition-colors"
                                                   title="Ver ticket">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?action=download_pdf&id=<?php echo $ticket['id']; ?>&code=<?php echo urlencode($ticket['ticket_code']); ?>"
                                                   class="text-gray-500 hover:text-red-400 transition-colors"
                                                   title="Descargar PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                                <button onclick="openStatusModal(<?php echo $ticket['id']; ?>, '<?php echo $ticket['status']; ?>')"
                                                        class="text-gray-500 hover:text-lime-400 transition-colors"
                                                        title="Cambiar estado">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="copyTicketCode('<?php echo htmlspecialchars($ticket['ticket_code']); ?>')"
                                                        class="text-gray-500 hover:text-white transition-colors"
                                                        title="Copiar código">
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

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="flex justify-between items-center px-6 py-5 border-t border-white/5">
                        <div class="text-xs text-gray-500 font-bold">
                            Mostrando <?php echo $pagination['offset'] + 1; ?>–<?php echo min($pagination['offset'] + $limit, $total); ?> de <?php echo $total; ?> tickets
                        </div>
                        <div class="flex gap-2">
                            <?php if ($pagination['has_prev']): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $eventId ? '&event_id=' . $eventId : ''; ?>"
                                   class="px-3 py-1.5 rounded-lg border border-white/10 text-gray-400 hover:text-white hover:bg-white/5 transition-all text-xs font-bold">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($pagination['total_pages'], $page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $eventId ? '&event_id=' . $eventId : ''; ?>"
                                   class="px-3 py-1.5 rounded-lg text-xs font-black transition-all <?php echo ($i == $page) ? 'bg-lime-400 text-black' : 'border border-white/10 text-gray-400 hover:text-white hover:bg-white/5'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($pagination['has_next']): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $eventId ? '&event_id=' . $eventId : ''; ?>"
                                   class="px-3 py-1.5 rounded-lg border border-white/10 text-gray-400 hover:text-white hover:bg-white/5 transition-all text-xs font-bold">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Status Modal -->
<div id="statusModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden z-50 flex items-center justify-center">
    <div class="glass-card rounded-[2rem] w-full max-w-md p-8 border border-white/10">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-black tracking-tighter">Cambiar Estado</h3>
            <button onclick="closeStatusModal()" class="w-8 h-8 rounded-xl bg-white/5 flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <form method="POST">
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
                <button type="button" onclick="closeStatusModal()" class="flex-1 py-4 rounded-xl border border-white/10 text-gray-400 hover:text-white hover:bg-white/5 transition-all font-black text-xs">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 py-4 bg-lime-400 text-black rounded-xl font-black text-xs hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">
                    Actualizar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function copyTicketCode(code) {
        navigator.clipboard.writeText(code).then(function() {
            showNotification('Código copiado al portapapeles');
        });
    }
    function openStatusModal(id, currentStatus) {
        document.getElementById('statusTicketId').value = id;
        document.getElementById('statusSelect').value = currentStatus;
        document.getElementById('statusModal').classList.remove('hidden');
    }
    function closeStatusModal() {
        document.getElementById('statusModal').classList.add('hidden');
    }
    document.getElementById('statusModal').addEventListener('click', function(e) {
        if (e.target === this) closeStatusModal();
    });
    function showNotification(message) {
        const n = document.createElement('div');
        n.className = 'fixed top-6 right-6 bg-lime-400 text-black px-5 py-3 rounded-2xl shadow-lg z-50 font-black text-sm';
        n.textContent = message;
        document.body.appendChild(n);
        setTimeout(() => document.body.removeChild(n), 2000);
    }
</script>
</body>
</html>
