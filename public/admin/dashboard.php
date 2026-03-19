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
    <title>Dashboard - Admin <?php echo SITE_NAME; ?></title>
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
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(218, 251, 113, 0.2);
            transform: translateY(-2px);
        }

        .nav-link {
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link.active {
            background: rgba(218, 251, 113, 0.1);
            color: #DAFB71;
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            bottom: 20%;
            width: 3px;
            background: #DAFB71;
            border-radius: 0 4px 4px 0;
        }

        .text-gradient {
            background: linear-gradient(to right, #DAFB71, #60A5FA);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
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
                    <a href="dashboard.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm">
                        <i class="fas fa-grid-2 text-lg"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="events.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5 whitespace-nowrap">
                        <i class="fas fa-calendar-alt text-lg"></i>
                        <span>Gestionar Eventos</span>
                    </a>
                    <a href="tickets.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5 whitespace-nowrap">
                        <i class="fas fa-ticket-alt text-lg"></i>
                        <span>Ventas & Tickets</span>
                    </a>
                    <a href="payment_settings.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5 whitespace-nowrap">
                        <i class="fas fa-wallet text-lg"></i>
                        <span>Métodos de Pago</span>
                    </a>
                    <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                    <a href="settings.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5 whitespace-nowrap">
                        <i class="fas fa-cog text-lg"></i>
                        <span>Configuración</span>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            
            <!-- User Profile (Bottom) -->
            <div class="mt-auto p-6 border-t border-white/5">
                <div class="glass-card p-4 rounded-2xl flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/5 rounded-full flex items-center justify-center border border-white/10">
                        <i class="fas fa-user-circle text-gray-400"></i>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="text-xs font-black truncate"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                        <p class="text-[10px] text-gray-500 truncate"><?php echo htmlspecialchars($_SESSION['admin_email']); ?></p>
                    </div>
                    <a href="logout.php" class="text-gray-500 hover:text-red-400 transition-colors p-2" title="Cerrar Sesión">
                        <i class="fas fa-power-off"></i>
                    </a>
                </div>
            </div>
        </aside>
        
        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto bg-[#0A0E14] relative">
            <!-- Decorative Glows -->
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-lime-400/5 blur-[120px] rounded-full pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-[300px] h-[300px] bg-blue-500/5 blur-[100px] rounded-full pointer-events-none"></div>

            <!-- Header -->
            <header class="sticky top-0 z-10 bg-[#0A0E14]/80 backdrop-blur-xl border-b border-white/5 px-8 h-20 flex items-center">
                <div class="flex flex-1 justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-black tracking-tighter">Panel de <span class="text-gradient">Control</span></h2>
                        <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Resumen general de tu actividad</p>
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="hidden md:block text-right">
                            <p class="text-xs font-bold"><?php echo date('l, d F'); ?></p>
                            <p class="text-[10px] text-gray-500 uppercase"><?php echo date('H:i'); ?> GMT+1</p>
                        </div>
                        <a href="../" target="_blank" class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-xs font-bold text-gray-300 hover:text-white hover:bg-white/10 transition">
                            <i class="fas fa-eye text-[10px]"></i>
                            Ver Tienda
                        </a>
                    </div>
                </div>
            </header>
            
            <!-- Dashboard Content -->
            <div class="p-8 relative z-10">
                <!-- Top Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    <div class="glass-card rounded-[2rem] p-6 group">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-lime-400/10 rounded-2xl flex items-center justify-center group-hover:bg-lime-400/20 transition-colors">
                                <i class="fas fa-calendar-check text-lime-400 text-xl"></i>
                            </div>
                            <span class="text-[10px] font-black text-lime-400/50 uppercase tracking-widest">Eventos</span>
                        </div>
                        <p class="text-4xl font-black mb-1"><?php echo $stats['total_events']; ?></p>
                        <p class="text-xs text-gray-500 font-medium">Eventos registrados</p>
                    </div>
                    
                    <div class="glass-card rounded-[2rem] p-6 group">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-500/10 rounded-2xl flex items-center justify-center group-hover:bg-blue-500/20 transition-colors">
                                <i class="fas fa-ticket-alt text-blue-400 text-xl"></i>
                            </div>
                            <span class="text-[10px] font-black text-blue-500/50 uppercase tracking-widest">Tickets</span>
                        </div>
                        <p class="text-4xl font-black mb-1"><?php echo $stats['total_tickets']; ?></p>
                        <p class="text-xs text-gray-500 font-medium">Ventas totales</p>
                    </div>
                    
                    <div class="glass-card rounded-[2rem] p-6 group opacity-50">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-purple-500/10 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-sack-dollar text-purple-400 text-xl"></i>
                            </div>
                            <span class="text-[10px] font-black text-purple-500/50 uppercase tracking-widest">Ingresos</span>
                        </div>
                        <p class="text-4xl font-black mb-1">0.00€</p>
                        <p class="text-xs text-gray-500 font-medium">Próximamente</p>
                    </div>
                    
                    <div class="glass-card rounded-[2rem] p-6 group opacity-50">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-orange-500/10 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-users text-orange-400 text-xl"></i>
                            </div>
                            <span class="text-[10px] font-black text-orange-500/50 uppercase tracking-widest">Usuarios</span>
                        </div>
                        <p class="text-4xl font-black mb-1">0</p>
                        <p class="text-xs text-gray-500 font-medium">En desarrollo</p>
                    </div>
                </div>
                
                <!-- Detailed Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Events List -->
                    <div class="glass-card rounded-[2.5rem] overflow-hidden flex flex-col">
                        <div class="p-8 border-b border-white/5 flex justify-between items-center bg-white/5">
                            <div>
                                <h3 class="text-lg font-black tracking-tight">Mis Eventos</h3>
                                <p class="text-[10px] text-lime-400/70 font-bold uppercase tracking-widest">Gestión Activa</p>
                            </div>
                            <a href="events.php" class="text-xs font-bold text-gray-400 hover:text-white transition">Gestionar Todos <i class="fas fa-chevron-right ml-1"></i></a>
                        </div>
                        
                        <div class="flex-1 p-2">
                            <?php if (empty($stats['recent_events'])): ?>
                                <div class="flex flex-col items-center justify-center py-20 text-gray-600">
                                    <i class="fas fa-calendar-times text-4xl mb-4 opacity-20"></i>
                                    <p class="text-sm">Aún no has creado eventos</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-1">
                                    <?php foreach ($stats['recent_events'] as $event): ?>
                                        <div class="group flex items-center gap-4 p-4 rounded-3xl hover:bg-white/5 transition-all">
                                            <div class="w-14 h-14 rounded-2xl overflow-hidden bg-gray-800 flex-shrink-0">
                                                <?php if ($event['image_url']): ?>
                                                    <img src="../<?php echo $event['image_url']; ?>" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-500" alt="">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center">
                                                        <i class="fas fa-image text-gray-700"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-bold text-sm truncate group-hover:text-lime-400 transition-colors"><?php echo htmlspecialchars($event['title']); ?></h4>
                                                <div class="flex items-center gap-3 mt-1">
                                                    <span class="text-[10px] text-gray-500 flex items-center gap-1">
                                                        <i class="fas fa-clock text-gray-700"></i>
                                                        <?php echo formatDate($event['date_event'], 'd M'); ?>
                                                    </span>
                                                    <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-lime-400/10 text-lime-400/80 uppercase">
                                                        <?php echo $event['available_tickets']; ?> Libres
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="text-right flex-shrink-0">
                                                <p class="text-xs font-black text-white"><?php echo formatCurrency($event['price']); ?></p>
                                                <p class="text-[9px] text-gray-600 uppercase font-bold">Por ticket</p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Sales Log -->
                    <div class="glass-card rounded-[2.5rem] overflow-hidden flex flex-col">
                        <div class="p-8 border-b border-white/5 flex justify-between items-center bg-white/5">
                            <div>
                                <h3 class="text-lg font-black tracking-tight">Ventas Recientes</h3>
                                <p class="text-[10px] text-blue-400/70 font-bold uppercase tracking-widest">Tiempo Real</p>
                            </div>
                            <a href="tickets.php" class="text-xs font-bold text-gray-400 hover:text-white transition">Historial <i class="fas fa-chevron-right ml-1"></i></a>
                        </div>
                        
                        <div class="flex-1 p-2">
                            <?php if (empty($stats['recent_tickets'])): ?>
                                <div class="flex flex-col items-center justify-center py-20 text-gray-600">
                                    <i class="fas fa-ticket-alt text-4xl mb-4 opacity-20"></i>
                                    <p class="text-sm text-center">No hay ventas registradas todavía</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="text-[9px] text-gray-500 uppercase tracking-widest text-left">
                                                <th class="px-4 py-3 font-black">Asistente</th>
                                                <th class="px-4 py-3 font-black">Evento</th>
                                                <th class="px-4 py-3 font-black text-right">Código</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-white/5">
                                            <?php foreach ($stats['recent_tickets'] as $ticket): ?>
                                                <tr class="hover:bg-white/5 transition-colors">
                                                    <td class="px-4 py-4">
                                                        <p class="text-xs font-bold whitespace-nowrap"><?php echo htmlspecialchars($ticket['attendee_name']); ?></p>
                                                        <p class="text-[9px] text-gray-500 italic"><?php echo formatDate($ticket['purchase_date'], 'H:i'); ?></p>
                                                    </td>
                                                    <td class="px-4 py-4 max-w-[150px]">
                                                        <p class="text-xs font-medium text-gray-400 truncate"><?php echo htmlspecialchars($ticket['event_title']); ?></p>
                                                    </td>
                                                    <td class="px-4 py-4 text-right">
                                                        <span class="inline-block px-2 py-1 bg-white/5 border border-white/10 rounded-lg text-[10px] font-mono text-gray-500">
                                                            <?php echo substr($ticket['ticket_code'], 0, 8); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Floating Action Button for Events -->
                <div class="mt-12 flex justify-center">
                    <a href="events.php?action=create" class="group relative px-8 py-4 bg-lime-400 text-black rounded-3xl font-black shadow-xl shadow-lime-400/20 hover:shadow-lime-400/40 hover:-translate-y-1 transition-all flex items-center gap-3">
                        <i class="fas fa-plus text-lg"></i>
                        <span>CREAR NUEVO EVENTO</span>
                        <div class="absolute inset-x-4 top-0 h-1/2 bg-white/20 rounded-full blur-sm opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
