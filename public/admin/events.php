<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

checkAdminSession();

$db = new Database();
$message = '';
$error = '';

// ─── CATEGORÍAS DISPONIBLES ───────────────────────────────
$categories = [
    'conciertos'   => '🎸 Conciertos',
    'musica'       => '🎵 Música',
    'teatro'       => '🎭 Teatro',
    'festivales'   => '🎉 Festivales',
    'deportes'     => '⚽ Deportes',
    'gastronomia'  => '🍷 Gastronomía',
    'arte'         => '🎨 Arte & Cultura',
    'otros'        => '📌 Otros',
];

// ─── FUNCIÓN SEO AUTOMÁTICO ───────────────────────────────
function generateSeo($title, $description, $location, $dateEvent) {
    $date = date('d/m/Y', strtotime($dateEvent));
    $seoTitle = mb_substr("$title en $location | Entradas y Tickets", 0, 60);
    $desc = !empty($description) ? mb_substr($description, 0, 100) : "$title se celebra el $date en $location.";
    $seoDescription = mb_substr("$desc Compra tus entradas online de forma segura.", 0, 160);
    $words = array_unique(array_filter(array_map('trim', explode(' ', strtolower("$title $location tickets entradas evento")))));
    $seoKeywords = implode(', ', array_slice($words, 0, 8));
    return [$seoTitle, $seoDescription, $seoKeywords];
}

// ─── PROCESAR ACCIONES ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        if (!file_exists(UPLOADS_PATH)) {
            mkdir(UPLOADS_PATH, 0777, true);
        }
        
        $title       = cleanInput($_POST['title']);
        $description = cleanInput($_POST['description']);
        $dateEvent   = cleanInput($_POST['date_event']);
        $location    = cleanInput($_POST['location']);
        $price       = floatval($_POST['price']);
        $maxTickets  = intval($_POST['max_tickets']);
        $category    = cleanInput($_POST['category'] ?? 'otros');
        
        // SEO — si el usuario los deja vacíos, generamos automáticamente
        $seoTitle       = cleanInput($_POST['seo_title'] ?? '');
        $seoDescription = cleanInput($_POST['seo_description'] ?? '');
        $seoKeywords    = cleanInput($_POST['seo_keywords'] ?? '');
        
        if (empty($seoTitle) || empty($seoDescription)) {
            [$autoTitle, $autoDesc, $autoKw] = generateSeo($title, $description, $location, $dateEvent);
            if (empty($seoTitle)) $seoTitle = $autoTitle;
            if (empty($seoDescription)) $seoDescription = $autoDesc;
            if (empty($seoKeywords)) $seoKeywords = $autoKw;
        }
        
        // Ticket types desde JSON
        $ticketTypesJson = $_POST['ticket_types_data'] ?? '[]';
        $ticketTypes = json_decode($ticketTypesJson, true) ?: [];
        
        if (empty($title) || empty($dateEvent) || empty($location) || $price < 0 || $maxTickets <= 0) {
            $error = 'Por favor completa todos los campos requeridos correctamente';
        } else {
            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageName = uploadImage($_FILES['image'], UPLOADS_PATH);
                if ($imageName) {
                    $imageUrl = 'uploads/' . $imageName;
                }
            }
            
            $adminId = $_SESSION['admin_id'];
            
            if ($action === 'create') {
                if ($db->createEvent($title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $adminId, $category, $seoTitle, $seoDescription, $seoKeywords)) {
                    $newEventId = $db->getLastInsertId();
                    // Guardar tipos de entrada
                    foreach ($ticketTypes as $i => $tt) {
                        if (!empty($tt['name']) && intval($tt['max']) > 0) {
                            $db->createTicketType($newEventId, $tt['name'], $tt['desc'] ?? '', floatval($tt['price']), intval($tt['max']), $i);
                        }
                    }
                    $message = 'Evento creado exitosamente';
                } else {
                    $error = 'Error al crear el evento';
                }
            } elseif ($action === 'update') {
                $id = intval($_POST['id']);
                $adminId2 = ($_SESSION['admin_role'] === 'superadmin') ? null : $adminId;
                $currentEvent = $db->getEventById($id, $adminId2);
                if (!$imageUrl && $currentEvent) $imageUrl = $currentEvent['image_url'];
                
                if ($db->updateEvent($id, $title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $adminId2, $category, $seoTitle, $seoDescription, $seoKeywords)) {
                    // Reemplazar ticket types
                    $db->deleteTicketTypesByEvent($id);
                    foreach ($ticketTypes as $i => $tt) {
                        if (!empty($tt['name']) && intval($tt['max']) > 0) {
                            $db->createTicketType($id, $tt['name'], $tt['desc'] ?? '', floatval($tt['price']), intval($tt['max']), $i);
                        }
                    }
                    $message = 'Evento actualizado exitosamente';
                } else {
                    $error = 'Error al actualizar el evento';
                }
            }
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = intval($_GET['id']);
    $adminId = ($_SESSION['admin_role'] === 'superadmin') ? null : $_SESSION['admin_id'];
    if ($db->deleteEvent($id, $adminId)) {
        $message = 'Evento eliminado exitosamente';
    } else {
        $error = 'Error al eliminar el evento';
    }
}

// Obtener eventos
$adminId = ($_SESSION['admin_role'] === 'superadmin') ? null : $_SESSION['admin_id'];
$events = $db->getAllEvents($adminId);

// Cargar ticket types de cada evento para el JS
$allTicketTypes = [];
foreach ($events as $ev) {
    $allTicketTypes[$ev['id']] = $db->getTicketTypesByEvent($ev['id']);
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - Admin <?php echo SITE_NAME; ?></title>
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
        }

        .glass-modal {
            background: rgba(10, 14, 20, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
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

        input, textarea, select {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }

        input:focus, textarea:focus, select:focus {
            border-color: rgba(218, 251, 113, 0.5) !important;
            box-shadow: 0 0 15px rgba(218, 251, 113, 0.1) !important;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
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
                        <i class="fas fa-grid-2 text-lg"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="events.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm">
                        <i class="fas fa-calendar-alt text-lg"></i>
                        <span>Gestionar Eventos</span>
                    </a>
                    <a href="tickets.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5 whitespace-nowrap">
                        <i class="fas fa-ticket-alt text-lg"></i>
                        <span>Ventas & Tickets</span>
                    </a>
                    <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                    <a href="settings.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5 whitespace-nowrap">
                        <i class="fas fa-cog text-lg"></i>
                        <span>Configuración</span>
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
                        <h2 class="text-2xl font-black tracking-tighter">Gestionar <span class="text-gradient">Eventos</span></h2>
                        <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Crea y administra tus funciones</p>
                    </div>
                    <button onclick="showCreateModal()" class="flex items-center gap-2 px-6 py-3 bg-lime-400 text-black rounded-2xl font-black text-xs hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">
                        <i class="fas fa-plus"></i>
                        NUEVO EVENTO
                    </button>
                </div>
            </header>
            
            <div class="p-8 relative z-10">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 animate-fade-in shadow-lg">
                        <i class="fas fa-check-circle"></i>
                        <span class="font-bold text-sm"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 animate-fade-in shadow-lg">
                        <i class="fas fa-exclamation-circle"></i>
                        <span class="font-bold text-sm"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Events Grid/Table -->
                <div class="glass-card rounded-[2.5rem] overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-[10px] text-gray-500 uppercase tracking-widest text-left border-b border-white/5">
                                    <th class="px-8 py-6 font-black">Evento</th>
                                    <th class="px-6 py-6 font-black">Fecha & Lugar</th>
                                    <th class="px-6 py-6 font-black">Categoría</th>
                                    <th class="px-6 py-6 font-black">Precio</th>
                                    <th class="px-6 py-6 font-black">Capacidad</th>
                                    <th class="px-6 py-6 font-black">Estado</th>
                                    <th class="px-8 py-6 font-black text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php if (empty($events)): ?>
                                    <tr>
                                        <td colspan="6" class="px-8 py-20 text-center">
                                            <div class="flex flex-col items-center opacity-20">
                                                <i class="fas fa-calendar-times text-5xl mb-4"></i>
                                                <p class="font-bold uppercase tracking-widest text-xs">No hay eventos registrados</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($events as $event): ?>
                                        <tr class="group hover:bg-white/[0.02] transition-colors">
                                            <td class="px-8 py-5">
                                                <div class="flex items-center gap-4">
                                                    <div class="w-16 h-16 rounded-2xl overflow-hidden bg-white/5 flex-shrink-0 border border-white/10 group-hover:scale-105 transition-transform duration-500">
                                                        <?php if ($event['image_url']): ?>
                                                            <img src="<?php echo SITE_URL . '/' . $event['image_url']; ?>" class="w-full h-full object-cover" alt="">
                                                        <?php else: ?>
                                                            <div class="w-full h-full flex items-center justify-center text-gray-700">
                                                                <i class="fas fa-image text-2xl"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="font-black text-white group-hover:text-lime-400 transition-colors truncate"><?php echo htmlspecialchars($event['title']); ?></p>
                                                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter line-clamp-1 italic mt-0.5">
                                                            ID: #<?php echo str_pad($event['id'], 4, '0', STR_PAD_LEFT); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-5">
                                                <div class="flex flex-col gap-1">
                                                    <div class="flex items-center gap-2 text-xs font-bold text-gray-300">
                                                        <i class="fas fa-calendar-day text-lime-400/50"></i>
                                                        <?php echo formatDate($event['date_event'], 'd M, Y'); ?>
                                                    </div>
                                                    <div class="flex items-center gap-2 text-[10px] text-gray-500 uppercase tracking-tight">
                                                        <i class="fas fa-location-dot"></i>
                                                        <?php echo htmlspecialchars($event['location']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-5">
                                                <?php
                                                $catKey = $event['category'] ?? 'otros';
                                                $catLabels = ['conciertos'=>'🎸','musica'=>'🎵','teatro'=>'🎭','festivales'=>'🎉','deportes'=>'⚽','gastronomia'=>'🍷','arte'=>'🎨','otros'=>'📌'];
                                                $catColors = ['conciertos'=>'text-purple-400 bg-purple-400/10','musica'=>'text-blue-400 bg-blue-400/10','teatro'=>'text-yellow-400 bg-yellow-400/10','festivales'=>'text-lime-400 bg-lime-400/10','deportes'=>'text-green-400 bg-green-400/10','gastronomia'=>'text-orange-400 bg-orange-400/10','arte'=>'text-pink-400 bg-pink-400/10','otros'=>'text-gray-400 bg-gray-400/10'];
                                                $color = $catColors[$catKey] ?? 'text-gray-400 bg-gray-400/10';
                                                ?>
                                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-black border border-white/10 <?php echo $color; ?>">
                                                    <?php echo ($catLabels[$catKey] ?? '📌') . ' ' . ucfirst($catKey); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-5">
                                                <span class="text-sm font-black text-white">
                                                    <?php echo formatCurrency($event['price']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-5">
                                                <div class="w-32">
                                                    <div class="flex justify-between items-center mb-1.5">
                                                        <span class="text-[9px] font-black text-gray-500 uppercase"><?php echo ($event['max_tickets'] - $event['available_tickets']); ?> / <?php echo $event['max_tickets']; ?></span>
                                                        <span class="text-[9px] font-black text-lime-400"><?php echo round(($event['available_tickets'] / $event['max_tickets']) * 100); ?>%</span>
                                                    </div>
                                                    <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                                                        <?php $percent = (($event['max_tickets'] - $event['available_tickets']) / $event['max_tickets']) * 100; ?>
                                                        <div class="h-full bg-lime-400 rounded-full shadow-[0_0_10px_rgba(218,251,113,0.3)]" style="width: <?php echo $percent; ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-5">
                                                <?php if ($event['status'] === 'active'): ?>
                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-lime-400/10 text-lime-400 text-[10px] font-black uppercase tracking-widest border border-lime-400/20">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-lime-400 animate-pulse"></span>
                                                        Activo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/5 text-gray-500 text-[10px] font-black uppercase tracking-widest border border-white/5">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>
                                                        Inactivo
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-8 py-5 text-right">
                                                <div class="flex justify-end gap-2">
                                                    <button onclick="editEvent(<?php echo $event['id']; ?>)" 
                                                            class="w-9 h-9 flex items-center justify-center rounded-xl bg-white/5 text-blue-400 hover:bg-blue-400 hover:text-white transition-all border border-white/5"
                                                            title="Editar">
                                                        <i class="fas fa-edit text-xs"></i>
                                                    </button>
                                                    <button onclick="deleteEvent(<?php echo $event['id']; ?>)" 
                                                            class="w-9 h-9 flex items-center justify-center rounded-xl bg-white/5 text-red-400 hover:bg-red-400 hover:text-white transition-all border border-white/5"
                                                            title="Eliminar">
                                                        <i class="fas fa-trash text-xs"></i>
                                                    </button>
                                                    <a href="../buy.php?id=<?php echo $event['id']; ?>" 
                                                       target="_blank"
                                                       class="w-9 h-9 flex items-center justify-center rounded-xl bg-white/5 text-lime-400 hover:bg-lime-400 hover:text-black transition-all border border-white/5"
                                                       title="Ver Tienda">
                                                        <i class="fas fa-external-link-alt text-xs"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal -->
    <div id="eventModal" class="fixed inset-0 hidden z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="glass-modal w-full max-w-3xl rounded-[3rem] p-10 relative overflow-y-auto max-h-[92vh] animate-fade-in">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h3 class="text-3xl font-black tracking-tighter" id="modalTitle">Nuevo Evento</h3>
                    <p class="text-xs text-lime-400 font-bold uppercase tracking-widest mt-1">Completa los datos de la función</p>
                </div>
                <button onclick="closeModal()" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white/5 text-gray-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data" id="eventForm" class="space-y-6">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="eventId">
                <input type="hidden" name="ticket_types_data" id="ticketTypesData" value="[]">

                <!-- INFO BÁSICA -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2 space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Título del Evento *</label>
                        <input type="text" name="title" required id="eventTitle"
                               class="w-full px-5 py-3.5 rounded-xl transition-all focus:ring-0 outline-none"
                               placeholder="Ej: Gran Concierto de Verano">
                    </div>

                    <div class="md:col-span-2 space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Descripción</label>
                        <textarea name="description" rows="3" id="eventDescription"
                                  class="w-full px-5 py-3.5 rounded-xl transition-all focus:ring-0 outline-none resize-none"
                                  placeholder="Detalla de qué trata tu evento..."></textarea>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Fecha y Hora *</label>
                        <input type="datetime-local" name="date_event" required id="eventDate"
                               class="w-full px-5 py-3.5 rounded-xl transition-all focus:ring-0 outline-none">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Lugar del Evento *</label>
                        <input type="text" name="location" required id="eventLocation"
                               class="w-full px-5 py-3.5 rounded-xl transition-all focus:ring-0 outline-none"
                               placeholder="Ej: Auditorio Central">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Categoría *</label>
                        <select name="category" id="eventCategory" class="w-full px-5 py-3.5 rounded-xl transition-all focus:ring-0 outline-none">
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Aforo Total *</label>
                        <input type="number" name="max_tickets" min="1" required id="eventMaxTickets"
                               class="w-full px-5 py-3.5 rounded-xl transition-all focus:ring-0 outline-none"
                               placeholder="Ej: 500">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Precio Base (si no hay tipos)</label>
                        <div class="relative">
                            <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 font-bold">€</span>
                            <input type="number" name="price" step="0.01" min="0" id="eventPrice"
                                   class="w-full pl-9 pr-5 py-3.5 rounded-xl transition-all focus:ring-0 outline-none"
                                   placeholder="0.00" value="0">
                        </div>
                    </div>

                    <div class="md:col-span-2 space-y-2">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Póster del Evento</label>
                        <div class="hidden border border-white/10 rounded-2xl p-3 bg-white/5 items-center gap-4" id="editImagePreviewContainer">
                            <img src="" id="editImagePreview" class="w-16 h-16 rounded-xl object-cover border border-white/10">
                            <p class="text-xs text-gray-500">Se usará esta imagen si no subes una nueva</p>
                        </div>
                        <input type="file" name="image" accept="image/*"
                               class="w-full text-xs text-gray-500 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-white/10 file:text-white hover:file:bg-white/20 file:transition-all cursor-pointer">
                    </div>
                </div>

                <!-- TIPOS DE ENTRADA -->
                <div class="border-t border-white/5 pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <p class="text-sm font-black">Tipos de Entrada</p>
                            <p class="text-[10px] text-gray-500">Crea diferentes categorías de tickets (VIP, General, etc.)</p>
                        </div>
                        <button type="button" onclick="addTicketType()" class="flex items-center gap-2 px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-xs font-black text-gray-300 hover:text-lime-400 hover:border-lime-400/30 transition-all">
                            <i class="fas fa-plus"></i> Añadir tipo
                        </button>
                    </div>
                    <div id="ticketTypesList" class="space-y-3"></div>
                    <p id="noTypesNote" class="text-[10px] text-gray-600 text-center py-4">Si no añades tipos, se usará el precio base</p>
                </div>

                <!-- SEO -->
                <div class="border-t border-white/5 pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <p class="text-sm font-black">SEO <span class="text-lime-400">✦ Auto</span></p>
                            <p class="text-[10px] text-gray-500">Se genera automáticamente. Puedes editarlo manualmente.</p>
                        </div>
                        <button type="button" onclick="previewSeo()" class="flex items-center gap-2 px-4 py-2 bg-lime-400/10 border border-lime-400/20 rounded-xl text-xs font-black text-lime-400 hover:bg-lime-400/20 transition-all">
                            <i class="fas fa-magic"></i> Previsualizar
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div class="space-y-1">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">SEO Title <span class="text-gray-600">(máx 60 chars)</span></label>
                            <input type="text" name="seo_title" id="seoTitle" maxlength="60"
                                   class="w-full px-5 py-3.5 rounded-xl transition-all focus:ring-0 outline-none"
                                   placeholder="Se genera automáticamente">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Meta Description <span class="text-gray-600">(máx 160 chars)</span></label>
                            <textarea name="seo_description" id="seoDescription" maxlength="160" rows="2"
                                      class="w-full px-5 py-3.5 rounded-xl transition-all focus:ring-0 outline-none resize-none"
                                      placeholder="Se genera automáticamente"></textarea>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Keywords</label>
                            <input type="text" name="seo_keywords" id="seoKeywords"
                                   class="w-full px-5 py-3.5 rounded-xl transition-all focus:ring-0 outline-none"
                                   placeholder="Se genera automáticamente">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal()"
                            class="px-7 py-3.5 rounded-xl text-xs font-black uppercase text-gray-500 hover:text-white transition-colors">
                        Descartar
                    </button>
                    <button type="submit" class="px-8 py-3.5 bg-lime-400 text-black rounded-xl font-black text-xs hover:shadow-[0_0_20px_rgba(218,251,113,0.3)] transition-all">
                        <i class="fas fa-save mr-2"></i>GUARDAR EVENTO
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const eventsData = <?php echo json_encode($events); ?>;
        const allTicketTypes = <?php echo json_encode($allTicketTypes); ?>;
        let ticketTypes = [];

        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Nuevo Evento';
            document.getElementById('formAction').value = 'create';
            document.getElementById('editImagePreviewContainer').style.display = 'none';
            document.getElementById('eventForm').reset();
            ticketTypes = [];
            renderTicketTypes();
            document.getElementById('seoTitle').value = '';
            document.getElementById('seoDescription').value = '';
            document.getElementById('seoKeywords').value = '';
            document.getElementById('eventModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function editEvent(id) {
            const event = eventsData.find(e => e.id == id);
            if (!event) return;
            document.getElementById('modalTitle').textContent = 'Editar Evento';
            document.getElementById('formAction').value = 'update';
            document.getElementById('eventId').value = event.id;
            document.getElementById('eventTitle').value = event.title;
            document.getElementById('eventDescription').value = event.description || '';
            document.getElementById('eventDate').value = (event.date_event || '').replace(' ', 'T');
            document.getElementById('eventLocation').value = event.location;
            document.getElementById('eventPrice').value = event.price;
            document.getElementById('eventMaxTickets').value = event.max_tickets;
            document.getElementById('eventCategory').value = event.category || 'otros';
            document.getElementById('seoTitle').value = event.seo_title || '';
            document.getElementById('seoDescription').value = event.seo_description || '';
            document.getElementById('seoKeywords').value = event.seo_keywords || '';
            if (event.image_url) {
                document.getElementById('editImagePreview').src = '<?php echo SITE_URL; ?>/' + event.image_url;
                document.getElementById('editImagePreviewContainer').style.display = 'flex';
            } else {
                document.getElementById('editImagePreviewContainer').style.display = 'none';
            }
            // Load ticket types
            ticketTypes = (allTicketTypes[id] || []).map(tt => ({name: tt.name, desc: tt.description || '', price: tt.price, max: tt.max_tickets}));
            renderTicketTypes();
            document.getElementById('eventModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function deleteEvent(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este evento?')) {
                window.location.href = 'events.php?action=delete&id=' + id;
            }
        }

        function closeModal() {
            document.getElementById('eventModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // ─── TICKET TYPES ─────────────────────────────────────
        function addTicketType() {
            ticketTypes.push({name: '', desc: '', price: 0, max: 0});
            renderTicketTypes();
        }

        function removeTicketType(i) {
            ticketTypes.splice(i, 1);
            renderTicketTypes();
        }

        function renderTicketTypes() {
            const list = document.getElementById('ticketTypesList');
            const note = document.getElementById('noTypesNote');
            list.innerHTML = '';
            note.style.display = ticketTypes.length === 0 ? 'block' : 'none';
            ticketTypes.forEach((tt, i) => {
                list.innerHTML += `
                <div class="flex gap-2 items-start glass-card rounded-2xl p-4 border border-white/5">
                    <div class="flex-1 grid grid-cols-2 md:grid-cols-4 gap-2">
                        <input type="text" placeholder="Nombre (VIP, General...)" value="${tt.name}"
                            onchange="ticketTypes[${i}].name=this.value; syncTypes()"
                            class="col-span-2 px-4 py-2.5 rounded-xl text-sm outline-none transition-all">
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs">€</span>
                            <input type="number" placeholder="Precio" value="${tt.price}" min="0" step="0.01"
                                onchange="ticketTypes[${i}].price=parseFloat(this.value)||0; syncTypes()"
                                class="w-full pl-7 pr-3 py-2.5 rounded-xl text-sm outline-none transition-all">
                        </div>
                        <input type="number" placeholder="Cantidad" value="${tt.max}" min="1"
                            onchange="ticketTypes[${i}].max=parseInt(this.value)||0; syncTypes()"
                            class="px-4 py-2.5 rounded-xl text-sm outline-none transition-all">
                    </div>
                    <button type="button" onclick="removeTicketType(${i})" class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-xl bg-red-400/10 text-red-400 hover:bg-red-400 hover:text-white transition-all text-xs">
                        <i class="fas fa-times"></i>
                    </button>
                </div>`;
            });
            syncTypes();
        }

        function syncTypes() {
            document.getElementById('ticketTypesData').value = JSON.stringify(ticketTypes);
        }

        // ─── SEO PREVIEW ──────────────────────────────────────
        function previewSeo() {
            const title = document.getElementById('eventTitle').value;
            const location = document.getElementById('eventLocation').value;
            const desc = document.getElementById('eventDescription').value;
            if (!title) { alert('Completa el título del evento primero'); return; }
            const seoTitle = (title + ' en ' + location + ' | Entradas y Tickets').substring(0, 60);
            const snippet = desc ? desc.substring(0, 100) : title + ' - ¡Consigue tus entradas online!';
            const seoDesc = (snippet + ' Compra tus entradas de forma segura.').substring(0, 160);
            const words = [...new Set((title + ' ' + location + ' tickets entradas evento').toLowerCase().split(' ').filter(w => w.length > 2))];
            const keywords = words.slice(0, 8).join(', ');
            document.getElementById('seoTitle').value = seoTitle;
            document.getElementById('seoDescription').value = seoDesc;
            document.getElementById('seoKeywords').value = keywords;
        }
    </script>
</body>
</html>
