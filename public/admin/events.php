<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

checkAdminSession();

$db = new Database();
$message = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = cleanInput($_POST['title']);
        $description = cleanInput($_POST['description']);
        $dateEvent = cleanInput($_POST['date_event']);
        $location = cleanInput($_POST['location']);
        $price = floatval($_POST['price']);
        $maxTickets = intval($_POST['max_tickets']);
        
        // Validaciones
        if (empty($title) || empty($dateEvent) || empty($location) || $price <= 0 || $maxTickets <= 0) {
            $error = 'Por favor completa todos los campos requeridos correctamente';
        } else {
            // Procesar imagen
            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageUrl = uploadImage($_FILES['image'], ROOT_PATH . '/assets/images');
                if ($imageUrl) {
                    $imageUrl = 'assets/images/' . $imageUrl;
                }
            }
            
            $adminId = $_SESSION['admin_id'];
            if ($db->createEvent($title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $adminId)) {
                $message = 'Evento creado exitosamente';
            } else {
                $error = 'Error al crear el evento';
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id']);
        $title = cleanInput($_POST['title']);
        $description = cleanInput($_POST['description']);
        $dateEvent = cleanInput($_POST['date_event']);
        $location = cleanInput($_POST['location']);
        $price = floatval($_POST['price']);
        $maxTickets = intval($_POST['max_tickets']);
        
        if (empty($title) || empty($dateEvent) || empty($location) || $price <= 0 || $maxTickets <= 0) {
            $error = 'Por favor completa todos los campos requeridos correctamente';
        } else {
            // Procesar imagen si se sube una nueva
            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageUrl = uploadImage($_FILES['image'], ROOT_PATH . '/assets/images');
                if ($imageUrl) {
                    $imageUrl = 'assets/images/' . $imageUrl;
                }
            }
            
            $adminId = ($_SESSION['admin_role'] === 'superadmin') ? null : $_SESSION['admin_id'];
            if ($db->updateEvent($id, $title, $description, $dateEvent, $location, $price, $maxTickets, $imageUrl, $adminId)) {
                $message = 'Evento actualizado exitosamente';
            } else {
                $error = 'Error al actualizar el evento';
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

// Obtener evento para editar
$editEvent = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit') {
    $adminId = ($_SESSION['admin_role'] === 'superadmin') ? null : $_SESSION['admin_id'];
    $editEvent = $db->getEventById(intval($_GET['id']), $adminId);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - Admin Tickets</title>
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
                    <a href="events.php" class="flex items-center space-x-3 p-3 rounded-lg bg-white bg-opacity-10">
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
                    <h2 class="text-2xl font-bold text-gray-800">Gestión de Eventos</h2>
                    <button onclick="showCreateModal()" class="btn-primary text-white px-4 py-2 rounded-lg hover:opacity-90 transition">
                        <i class="fas fa-plus mr-2"></i>Nuevo Evento
                    </button>
                </div>
            </header>
            
            <!-- Content -->
            <div class="p-8">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Events Table -->
                <div class="card rounded-lg shadow-sm border">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-3 px-4">Evento</th>
                                        <th class="text-left py-3 px-4">Fecha</th>
                                        <th class="text-left py-3 px-4">Lugar</th>
                                        <th class="text-left py-3 px-4">Precio</th>
                                        <th class="text-left py-3 px-4">Tickets</th>
                                        <th class="text-left py-3 px-4">Estado</th>
                                        <th class="text-left py-3 px-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($events)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-8 text-gray-500">
                                                No hay eventos registrados
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($events as $event): ?>
                                            <tr class="border-b hover:bg-gray-50">
                                                <td class="py-3 px-4">
                                                    <div class="flex items-center">
                                                        <?php if ($event['image_url']): ?>
                                                            <img src="<?php echo SITE_URL . '/' . $event['image_url']; ?>" 
                                                                 alt="" class="w-10 h-10 rounded object-cover mr-3">
                                                        <?php else: ?>
                                                            <div class="w-10 h-10 bg-gray-200 rounded mr-3 flex items-center justify-center">
                                                                <i class="fas fa-image text-gray-400"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="font-medium"><?php echo htmlspecialchars($event['title']); ?></div>
                                                            <div class="text-sm text-gray-500 line-clamp-1">
                                                                <?php echo htmlspecialchars(substr($event['description'], 0, 50)); ?>...
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="text-sm">
                                                        <?php echo formatDate($event['date_event']); ?>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="text-sm"><?php echo htmlspecialchars($event['location']); ?></div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="font-medium"><?php echo formatCurrency($event['price']); ?></div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="text-sm">
                                                        <?php echo $event['available_tickets']; ?> / <?php echo $event['max_tickets']; ?>
                                                    </div>
                                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo (($event['max_tickets'] - $event['available_tickets']) / $event['max_tickets']) * 100; ?>%"></div>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <?php if ($event['status'] === 'active'): ?>
                                                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Activo</span>
                                                    <?php else: ?>
                                                        <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 px-4">
                                                    <div class="flex space-x-2">
                                                        <button onclick="editEvent(<?php echo $event['id']; ?>)" 
                                                                class="text-blue-600 hover:text-blue-800 transition"
                                                                title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button onclick="deleteEvent(<?php echo $event['id']; ?>)" 
                                                                class="text-red-600 hover:text-red-800 transition"
                                                                title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <a href="../public/buy.php?id=<?php echo $event['id']; ?>" 
                                                           target="_blank"
                                                           class="text-green-600 hover:text-green-800 transition"
                                                           title="Ver en sitio">
                                                            <i class="fas fa-external-link-alt"></i>
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
            </div>
        </main>
    </div>
    
    <!-- Modal Create/Edit -->
    <div id="eventModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold" id="modalTitle">Crear Evento</h3>
                    <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="eventForm">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="eventId">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-medium mb-2">Título del Evento *</label>
                            <input type="text" name="title" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                   id="eventTitle">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-medium mb-2">Descripción</label>
                            <textarea name="description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                      id="eventDescription"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Fecha y Hora *</label>
                            <input type="datetime-local" name="date_event" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                   id="eventDate">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Lugar *</label>
                            <input type="text" name="location" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                   id="eventLocation">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Precio *</label>
                            <input type="number" name="price" step="0.01" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                   id="eventPrice">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Tickets Disponibles *</label>
                            <input type="number" name="max_tickets" min="1" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                   id="eventMaxTickets">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-medium mb-2">Imagen del Evento</label>
                            <input type="file" name="image" accept="image/*"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800">
                            <p class="text-sm text-gray-500 mt-1">Formatos: JPG, PNG, GIF. Máximo 2MB.</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-primary text-white px-4 py-2 rounded-lg hover:opacity-90 transition">
                            <i class="fas fa-save mr-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Datos de eventos para editar
        const eventsData = <?php echo json_encode($events); ?>;
        
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Crear Evento';
            document.getElementById('formAction').value = 'create';
            document.getElementById('eventForm').reset();
            document.getElementById('eventModal').classList.remove('hidden');
        }
        
        function editEvent(id) {
            const event = eventsData.find(e => e.id == id);
            if (!event) return;
            
            document.getElementById('modalTitle').textContent = 'Editar Evento';
            document.getElementById('formAction').value = 'update';
            document.getElementById('eventId').value = event.id;
            document.getElementById('eventTitle').value = event.title;
            document.getElementById('eventDescription').value = event.description;
            document.getElementById('eventDate').value = event.date_event.replace(' ', 'T');
            document.getElementById('eventLocation').value = event.location;
            document.getElementById('eventPrice').value = event.price;
            document.getElementById('eventMaxTickets').value = event.max_tickets;
            
            document.getElementById('eventModal').classList.remove('hidden');
        }
        
        function deleteEvent(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este evento? Esta acción no se puede deshacer.')) {
                window.location.href = 'events.php?action=delete&id=' + id;
            }
        }
        
        function closeModal() {
            document.getElementById('eventModal').classList.add('hidden');
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
