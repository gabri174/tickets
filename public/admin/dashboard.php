<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

checkAdminSession();

$db = new Database();
$adminId = ($_SESSION['admin_role'] === 'superadmin') ? null : $_SESSION['admin_id'];

$stats = [
    'total_events' => $db->countEvents($adminId),
    'total_tickets' => $db->countTickets($adminId),
    'recent_events' => array_slice($db->getAllEvents($adminId), 0, 5),
    'recent_tickets' => array_slice($db->getAllTickets($adminId), 0, 10)
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Tickets</title>
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
                    <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg bg-white bg-opacity-10">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="events.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Eventos</span>
                    </a>
                    <a href="tickets.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition">
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
                    <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">
                            <?php echo date('d/m/Y H:i'); ?>
                        </span>
                        <a href="../public/" target="_blank" class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-external-link-alt mr-1"></i>Ver sitio
                        </a>
                    </div>
                </div>
            </header>
            
            <!-- Dashboard Content -->
            <div class="p-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="card rounded-lg p-6 shadow-sm border">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Total Eventos</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total_events']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="events.php" class="text-sm text-blue-600 hover:text-blue-800">
                                Ver todos <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="card rounded-lg p-6 shadow-sm border">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Tickets Vendidos</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total_tickets']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-ticket-alt text-green-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="tickets.php" class="text-sm text-green-600 hover:text-green-800">
                                Ver todos <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="card rounded-lg p-6 shadow-sm border">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Ingresos Totales</p>
                                <p class="text-3xl font-bold text-gray-800">$0</p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="text-sm text-gray-500">En desarrollo</span>
                        </div>
                    </div>
                    
                    <div class="card rounded-lg p-6 shadow-sm border">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Tasa Conversión</p>
                                <p class="text-3xl font-bold text-gray-800">0%</p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="text-sm text-gray-500">En desarrollo</span>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Recent Events -->
                    <div class="card rounded-lg shadow-sm border">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Eventos Recientes</h3>
                                <a href="events.php" class="text-sm text-blue-600 hover:text-blue-800">
                                    Ver todos
                                </a>
                            </div>
                            
                            <?php if (empty($stats['recent_events'])): ?>
                                <p class="text-gray-500 text-center py-8">No hay eventos registrados</p>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($stats['recent_events'] as $event): ?>
                                        <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition">
                                            <div class="flex-1">
                                                <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($event['title']); ?></h4>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-calendar-alt mr-1"></i>
                                                    <?php echo formatDate($event['date_event']); ?>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-sm font-medium text-gray-800">
                                                    <?php echo formatCurrency($event['price']); ?>
                                                </span>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo $event['available_tickets']; ?> disponibles
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Tickets -->
                    <div class="card rounded-lg shadow-sm border">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Tickets Recientes</h3>
                                <a href="tickets.php" class="text-sm text-green-600 hover:text-green-800">
                                    Ver todos
                                </a>
                            </div>
                            
                            <?php if (empty($stats['recent_tickets'])): ?>
                                <p class="text-gray-500 text-center py-8">No hay tickets vendidos</p>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($stats['recent_tickets'] as $ticket): ?>
                                        <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition">
                                            <div class="flex-1">
                                                <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($ticket['event_title']); ?></h4>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-user mr-1"></i>
                                                    <?php echo htmlspecialchars($ticket['attendee_name']); ?>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <code class="text-xs bg-gray-100 px-2 py-1 rounded">
                                                    <?php echo substr($ticket['ticket_code'], 0, 12); ?>...
                                                </code>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo formatDate($ticket['purchase_date'], 'd/m H:i'); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="mt-8 card rounded-lg shadow-sm border p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Acciones Rápidas</h3>
                    <div class="flex flex-wrap gap-4">
                        <a href="events.php?action=create" class="btn-primary text-white px-4 py-2 rounded-lg hover:opacity-90 transition">
                            <i class="fas fa-plus mr-2"></i>Crear Evento
                        </a>
                        <a href="tickets.php?export=true" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-download mr-2"></i>Exportar Tickets
                        </a>
                        <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                        <a href="settings.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                            <i class="fas fa-cog mr-2"></i>Configuración
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
