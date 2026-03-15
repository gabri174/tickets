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
$tickets = $db->getAllTickets($adminId);
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
    <title>Tickets - Admin Tickets</title>
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
        .sidebar { background-color: var(--color-gray-dark); }
        .btn-primary { background-color: var(--color-gray-dark); }
        .btn-primary:hover { background-color: var(--color-black); }
        .card { background: white; }
    </style>
</head>
<body class="font-sans">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="sidebar w-64 text-white">
            <div class="p-6">
                <div class="flex items-center space-x-2 mb-8">
                    <i class="fas fa-ticket-alt text-2xl"></i>
                    <h1 class="text-xl font-bold">Admin Panel</h1>
                </div>
                
                <nav class="space-y-2">
                    <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="events.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Eventos</span>
                    </a>
                    <a href="tickets.php" class="flex items-center space-x-3 p-3 rounded-lg bg-white bg-opacity-10">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Tickets</span>
                    </a>
                    <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                    <a href="settings.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition">
                        <i class="fas fa-cog"></i>
                        <span>Configuración</span>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            
            <!-- User Info -->
            <div class="absolute bottom-0 left-0 right-0 p-6 border-t border-gray-600">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                        <p class="text-xs text-gray-300"><?php echo htmlspecialchars($_SESSION['admin_email']); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="mt-4 flex items-center space-x-2 text-sm hover:text-gray-300 transition">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar sesión</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm px-8 py-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-2xl font-bold text-gray-800">Gestión de Tickets</h2>
                    <div class="flex space-x-3">
                        <a href="?export=true" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-download mr-2"></i>Exportar CSV
                        </a>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <div class="p-8">
                <!-- Filters -->
                <div class="card rounded-lg shadow-sm border p-6 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Buscar</label>
                            <input type="text" name="search" 
                                   placeholder="Código, nombre o email..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Evento</label>
                            <select name="event_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800">
                                <option value="">Todos los eventos</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo ($eventId == $event['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="btn-primary text-white px-4 py-2 rounded-lg hover:opacity-90 transition flex-1">
                                <i class="fas fa-search mr-2"></i>Filtrar
                            </button>
                            <?php if ($search || $eventId): ?>
                                <a href="tickets.php" class="ml-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="card rounded-lg p-4 border">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Tickets</p>
                                <p class="text-2xl font-bold"><?php echo $total; ?></p>
                            </div>
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-ticket-alt text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card rounded-lg p-4 border">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Válidos</p>
                                <p class="text-2xl font-bold text-green-600">
                                    <?php echo count(array_filter($tickets, fn($t) => $t['status'] === 'valid')); ?>
                                </p>
                            </div>
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-check text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card rounded-lg p-4 border">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Utilizados</p>
                                <p class="text-2xl font-bold text-yellow-600">
                                    <?php echo count(array_filter($tickets, fn($t) => $t['status'] === 'used')); ?>
                                </p>
                            </div>
                            <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card rounded-lg p-4 border">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Cancelados</p>
                                <p class="text-2xl font-bold text-red-600">
                                    <?php echo count(array_filter($tickets, fn($t) => $t['status'] === 'cancelled')); ?>
                                </p>
                            </div>
                            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-times text-red-600"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tickets Table -->
                <div class="card rounded-lg shadow-sm border">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-3 px-4">Código</th>
                                        <th class="text-left py-3 px-4">Evento</th>
                                        <th class="text-left py-3 px-4">Asistente</th>
                                        <th class="text-left py-3 px-4">Contacto</th>
                                        <th class="text-left py-3 px-4">Fecha Compra</th>
                                        <th class="text-left py-3 px-4">Estado</th>
                                        <th class="text-left py-3 px-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($displayTickets)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-8 text-gray-500">
                                                No se encontraron tickets
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($displayTickets as $ticket): ?>
                                            <tr class="border-b hover:bg-gray-50">
                                                <td class="py-3 px-4">
                                                    <code class="text-sm bg-gray-100 px-2 py-1 rounded">
                                                        <?php echo htmlspecialchars($ticket['ticket_code']); ?>
                                                    </code>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="font-medium"><?php echo htmlspecialchars($ticket['event_title']); ?></div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="font-medium"><?php echo htmlspecialchars($ticket['attendee_name']); ?></div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="text-sm">
                                                        <div><?php echo htmlspecialchars($ticket['attendee_email']); ?></div>
                                                        <?php if ($ticket['attendee_phone']): ?>
                                                            <div class="text-gray-500"><?php echo htmlspecialchars($ticket['attendee_phone']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="text-sm"><?php echo formatDate($ticket['purchase_date']); ?></div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <?php if ($ticket['status'] === 'valid'): ?>
                                                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Válido</span>
                                                    <?php elseif ($ticket['status'] === 'used'): ?>
                                                        <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">Utilizado</span>
                                                    <?php else: ?>
                                                        <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">Cancelado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="flex space-x-2">
                                                        <a href="../public/ticket.php?code=<?php echo urlencode($ticket['ticket_code']); ?>" 
                                                           target="_blank"
                                                           class="text-blue-600 hover:text-blue-800 transition"
                                                           title="Ver ticket">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button onclick="copyTicketCode('<?php echo htmlspecialchars($ticket['ticket_code']); ?>')" 
                                                                class="text-green-600 hover:text-green-800 transition"
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
                            <div class="flex justify-between items-center mt-6">
                                <div class="text-sm text-gray-600">
                                    Mostrando <?php echo $pagination['offset'] + 1; ?> - 
                                    <?php echo min($pagination['offset'] + $limit, $total); ?> 
                                    de <?php echo $total; ?> tickets
                                </div>
                                
                                <div class="flex space-x-2">
                                    <?php if ($pagination['has_prev']): ?>
                                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $eventId ? '&event_id=' . $eventId : ''; ?>" 
                                           class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($pagination['total_pages'], $page + 2);
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++): 
                                    ?>
                                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $eventId ? '&event_id=' . $eventId : ''; ?>" 
                                           class="px-3 py-1 border <?php echo ($i == $page) ? 'bg-gray-800 text-white' : 'border-gray-300 hover:bg-gray-50'; ?> rounded transition">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($pagination['has_next']): ?>
                                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $eventId ? '&event_id=' . $eventId : ''; ?>" 
                                           class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function copyTicketCode(code) {
            navigator.clipboard.writeText(code).then(function() {
                showNotification('Código copiado al portapapeles');
            });
        }
        
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 2000);
        }
    </script>
</body>
</html>
