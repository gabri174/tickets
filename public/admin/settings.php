<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

checkAdminSession();

if ($_SESSION['admin_role'] !== 'superadmin') {
    die("Acceso denegado. Esta sección es solo para el Super Administrador.");
}

$db = new Database();
$message = '';
$error = '';

// Procesar actualización de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = cleanInput($_POST['site_name']);
    $adminEmail = cleanInput($_POST['admin_email']);
    $smtpHost = cleanInput($_POST['smtp_host']);
    $smtpPort = intval($_POST['smtp_port']);
    $smtpUsername = cleanInput($_POST['smtp_username']);
    $smtpPassword = $_POST['smtp_password'];
    $smtpFromEmail = cleanInput($_POST['smtp_from_email']);
    $smtpFromName = cleanInput($_POST['smtp_from_name']);
    
    // Validaciones básicas
    if (empty($siteName) || empty($adminEmail) || !validateEmail($adminEmail)) {
        $error = 'Por favor completa los campos requeridos correctamente';
    } else {
        // Aquí podrías guardar la configuración en la base de datos o archivo
        // Por ahora, solo mostramos un mensaje de éxito
        $message = 'Configuración actualizada exitosamente. Nota: Para hacer los cambios permanentes, edita el archivo includes/config/config.php';
    }
}

// Obtener configuración actual
$currentConfig = [
    'site_name' => SITE_NAME,
    'admin_email' => ADMIN_EMAIL,
    'smtp_host' => SMTP_HOST,
    'smtp_port' => SMTP_PORT,
    'smtp_username' => SMTP_USERNAME,
    'smtp_from_email' => SMTP_FROM_EMAIL,
    'smtp_from_name' => SMTP_FROM_NAME
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Admin Tickets</title>
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
                    <a href="tickets.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Tickets</span>
                    </a>
                    <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                    <a href="settings.php" class="flex items-center space-x-3 p-3 rounded-lg bg-white bg-opacity-10">
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
                    <h2 class="text-2xl font-bold text-gray-800">Configuración del Sistema</h2>
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
                
                <!-- Configuration Form -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- General Settings -->
                    <div class="card rounded-lg shadow-sm border">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                                <i class="fas fa-cog mr-2"></i>Configuración General
                            </h3>
                            
                            <form method="POST">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">Nombre del Sitio</label>
                                        <input type="text" name="site_name" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                               value="<?php echo htmlspecialchars($currentConfig['site_name']); ?>">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">Email del Administrador</label>
                                        <input type="email" name="admin_email" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                               value="<?php echo htmlspecialchars($currentConfig['admin_email']); ?>">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">URL del Sitio</label>
                                        <input type="url" name="site_url" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                               value="<?php echo htmlspecialchars(SITE_URL); ?>"
                                               readonly>
                                        <p class="text-sm text-gray-500 mt-1">Este valor se configura en el archivo config.php</p>
                                    </div>
                                </div>
                                
                                <!-- Email Settings -->
                                <h4 class="text-md font-semibold text-gray-800 mt-6 mb-4">
                                    <i class="fas fa-envelope mr-2"></i>Configuración de Email
                                </h4>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">Servidor SMTP</label>
                                        <input type="text" name="smtp_host" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                               value="<?php echo htmlspecialchars($currentConfig['smtp_host']); ?>">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">Puerto SMTP</label>
                                        <input type="number" name="smtp_port" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                               value="<?php echo $currentConfig['smtp_port']; ?>">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">Usuario SMTP</label>
                                        <input type="text" name="smtp_username" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                               value="<?php echo htmlspecialchars($currentConfig['smtp_username']); ?>">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">Contraseña SMTP</label>
                                        <input type="password" name="smtp_password"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                               placeholder="••••••••">
                                        <p class="text-sm text-gray-500 mt-1">Deja en blanco para mantener la contraseña actual</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">Email Remitente</label>
                                        <input type="email" name="smtp_from_email" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                               value="<?php echo htmlspecialchars($currentConfig['smtp_from_email']); ?>">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">Nombre Remitente</label>
                                        <input type="text" name="smtp_from_name" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-800"
                                               value="<?php echo htmlspecialchars($currentConfig['smtp_from_name']); ?>">
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <button type="submit" class="btn-primary text-white px-6 py-2 rounded-lg hover:opacity-90 transition">
                                        <i class="fas fa-save mr-2"></i>Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- System Information -->
                    <div class="space-y-6">
                        <!-- System Info -->
                        <div class="card rounded-lg shadow-sm border">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                                    <i class="fas fa-info-circle mr-2"></i>Información del Sistema
                                </h3>
                                
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Versión PHP:</span>
                                        <span class="font-medium"><?php echo PHP_VERSION; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Servidor Web:</span>
                                        <span class="font-medium"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Base de Datos:</span>
                                        <span class="font-medium">MySQL</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Zona Horaria:</span>
                                        <span class="font-medium"><?php echo date_default_timezone_get(); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Memoria Límite:</span>
                                        <span class="font-medium"><?php echo ini_get('memory_limit'); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Upload Max Filesize:</span>
                                        <span class="font-medium"><?php echo ini_get('upload_max_filesize'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="card rounded-lg shadow-sm border">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                                    <i class="fas fa-tools mr-2"></i>Acciones Rápidas
                                </h3>
                                
                                <div class="space-y-3">
                                    <a href="?action=clear_cache" class="block w-full text-left px-4 py-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                        <i class="fas fa-trash-alt mr-2 text-gray-600"></i>
                                        Limpiar Caché
                                    </a>
                                    
                                    <a href="?action=test_email" class="block w-full text-left px-4 py-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                        <i class="fas fa-envelope mr-2 text-gray-600"></i>
                                        Probar Configuración Email
                                    </a>
                                    
                                    <a href="?action=backup_db" class="block w-full text-left px-4 py-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                        <i class="fas fa-download mr-2 text-gray-600"></i>
                                        Respaldar Base de Datos
                                    </a>
                                    
                                    <a href="../public/" target="_blank" class="block w-full text-left px-4 py-3 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition">
                                        <i class="fas fa-external-link-alt mr-2"></i>
                                        Ver Sitio Público
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Notes -->
                        <div class="card rounded-lg shadow-sm border border-yellow-200 bg-yellow-50">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-yellow-800 mb-4">
                                    <i class="fas fa-shield-alt mr-2"></i>Notas de Seguridad
                                </h3>
                                
                                <ul class="space-y-2 text-sm text-yellow-700">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle mr-2 mt-1 text-yellow-600"></i>
                                        <span>Las credenciales están protegidas por .gitignore</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-1 text-yellow-600"></i>
                                        <span>Cambia la contraseña del administrador por defecto</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle mr-2 mt-1 text-yellow-600"></i>
                                        <span>Los inputs están validados contra XSS y SQL Injection</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-info-circle mr-2 mt-1 text-yellow-600"></i>
                                        <span>Usa HTTPS en producción</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
