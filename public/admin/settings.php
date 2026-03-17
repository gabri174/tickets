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
    <title>Configuración - Admin <?php echo SITE_NAME; ?></title>
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

        input, select {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }

        input:focus, select:focus {
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
                    <a href="events.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5">
                        <i class="fas fa-calendar-alt text-lg"></i>
                        <span>Gestionar Eventos</span>
                    </a>
                    <a href="tickets.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm text-gray-500 hover:text-white hover:bg-white/5">
                        <i class="fas fa-ticket-alt text-lg"></i>
                        <span>Ventas & Tickets</span>
                    </a>
                    <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
                    <a href="settings.php" class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm whitespace-nowrap">
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
                        <h2 class="text-2xl font-black tracking-tighter">Ajustes del <span class="text-gradient">Sistema</span></h2>
                        <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Configuración global y diagnóstico</p>
                    </div>
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

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Configuration Form -->
                    <div class="glass-card rounded-[2.5rem] p-10">
                        <h3 class="text-xl font-black tracking-tighter mb-8 flex items-center gap-3">
                            <i class="fas fa-sliders-h text-lime-400"></i>
                            General & SMTP
                        </h3>
                        
                        <form method="POST" class="space-y-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Nombre del Sitio</label>
                                    <input type="text" name="site_name" required value="<?php echo htmlspecialchars($currentConfig['site_name']); ?>"
                                           class="w-full px-6 py-4 rounded-2xl outline-none transition-all">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Email Administrador</label>
                                    <input type="email" name="admin_email" required value="<?php echo htmlspecialchars($currentConfig['admin_email']); ?>"
                                           class="w-full px-6 py-4 rounded-2xl outline-none transition-all">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">URL del Sitio</label>
                                <input type="url" value="<?php echo htmlspecialchars(SITE_URL); ?>" readonly
                                       class="w-full px-6 py-4 rounded-2xl outline-none transition-all opacity-50 cursor-not-allowed">
                                <p class="text-[9px] text-gray-600 font-bold uppercase mt-1 italic">Editable solo en config.php</p>
                            </div>

                            <div class="pt-6 border-t border-white/5">
                                <h4 class="text-xs font-black uppercase tracking-[0.2em] text-gray-500 mb-6 flex items-center gap-2">
                                    <i class="fas fa-envelope-open-text text-lime-400/50"></i>
                                    CONFIGURACIÓN DE ENVÍO (SMTP)
                                </h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                    <div class="md:col-span-3 space-y-2">
                                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Servidor Host</label>
                                        <input type="text" name="smtp_host" required value="<?php echo htmlspecialchars($currentConfig['smtp_host']); ?>"
                                               class="w-full px-6 py-4 rounded-2xl outline-none transition-all">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Puerto</label>
                                        <input type="number" name="smtp_port" required value="<?php echo $currentConfig['smtp_port']; ?>"
                                               class="w-full px-6 py-4 rounded-2xl outline-none transition-all text-center">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Usuario</label>
                                        <input type="text" name="smtp_username" required value="<?php echo htmlspecialchars($currentConfig['smtp_username']); ?>"
                                               class="w-full px-6 py-4 rounded-2xl outline-none transition-all">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Contraseña</label>
                                        <input type="password" name="smtp_password" placeholder="••••••••"
                                               class="w-full px-6 py-4 rounded-2xl outline-none transition-all">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Email Remitente</label>
                                        <input type="email" name="smtp_from_email" required value="<?php echo htmlspecialchars($currentConfig['smtp_from_email']); ?>"
                                               class="w-full px-6 py-4 rounded-2xl outline-none transition-all">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Nombre Remitente</label>
                                        <input type="text" name="smtp_from_name" required value="<?php echo htmlspecialchars($currentConfig['smtp_from_name']); ?>"
                                               class="w-full px-6 py-4 rounded-2xl outline-none transition-all">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="w-full py-5 bg-lime-400 text-black rounded-[1.5rem] font-black text-xs hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-save"></i>
                                GUARDAR TODA LA CONFIGURACIÓN
                            </button>
                        </form>
                    </div>

                    <!-- Side Info Panels -->
                    <div class="space-y-8">
                        <!-- Diagnostics -->
                        <div class="glass-card rounded-[2.5rem] p-10">
                            <h3 class="text-xl font-black tracking-tighter mb-8 flex items-center gap-3">
                                <i class="fas fa-vial text-lime-400"></i>
                                Diagnóstico
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-white/5 rounded-2xl p-6 border border-white/5">
                                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-1">PHP Version</p>
                                    <p class="text-lg font-black text-lime-400 tracking-tighter"><?php echo PHP_VERSION; ?></p>
                                </div>
                                <div class="bg-white/5 rounded-2xl p-6 border border-white/5">
                                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-1">DB Engine</p>
                                    <p class="text-lg font-black text-white tracking-tighter">MySQL 8.0+</p>
                                </div>
                                <div class="bg-white/5 rounded-2xl p-6 border border-white/5">
                                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-1">Max Upload</p>
                                    <p class="text-lg font-black text-white tracking-tighter"><?php echo ini_get('upload_max_filesize'); ?></p>
                                </div>
                                <div class="bg-white/5 rounded-2xl p-6 border border-white/5">
                                    <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-1">Memory Limit</p>
                                    <p class="text-lg font-black text-white tracking-tighter"><?php echo ini_get('memory_limit'); ?></p>
                                </div>
                            </div>

                            <div class="mt-8 space-y-4">
                                <a href="?action=test_email" class="flex items-center justify-between p-5 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-blue-400/10 flex items-center justify-center text-blue-400">
                                            <i class="fas fa-paper-plane"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-black">TEST ENVÍO EMAIL</p>
                                            <p class="text-[10px] text-gray-500 font-bold italic">Envía correo de prueba</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-700 group-hover:text-white transition-colors"></i>
                                </a>
                                
                                <a href="?action=clear_cache" class="flex items-center justify-between p-5 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-orange-400/10 flex items-center justify-center text-orange-400">
                                            <i class="fas fa-broom"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-black">LIMPIAR CACHÉ</p>
                                            <p class="text-[10px] text-gray-500 font-bold italic">Purga archivos temporales</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-700 group-hover:text-white transition-colors"></i>
                                </a>

                                <a href="?action=backup_db" class="flex items-center justify-between p-5 rounded-2xl bg-lime-400/5 border border-lime-400/10 hover:bg-lime-400/10 transition-all group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-lime-400/10 flex items-center justify-center text-lime-400">
                                            <i class="fas fa-database"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-black">BACKUP DB</p>
                                            <p class="text-[10px] text-gray-500 font-bold italic">Descarga copia de seguridad</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-download text-lime-400 tracking-tighter"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Info Card -->
                        <div class="glass-card rounded-[2.5rem] p-10 bg-lime-400/[0.02]">
                            <h3 class="text-lg font-black tracking-tighter mb-4 text-lime-400/80 italic">Aviso de Seguridad</h3>
                            <ul class="space-y-4">
                                <li class="flex items-start gap-4">
                                    <div class="mt-1 w-5 h-5 rounded-full bg-lime-400/10 flex items-center justify-center text-[8px] text-lime-400 font-bold border border-lime-400/20">01</div>
                                    <p class="text-xs text-gray-400 leading-relaxed">Los cambios SMTP afectan a la recuperación de contraseñas y envío de tickets.</p>
                                </li>
                                <li class="flex items-start gap-4">
                                    <div class="mt-1 w-5 h-5 rounded-full bg-lime-400/10 flex items-center justify-center text-[8px] text-lime-400 font-bold border border-lime-400/20">02</div>
                                    <p class="text-xs text-gray-400 leading-relaxed">Asegúrate de que el servidor SMTP permita conexiones desde esta IP.</p>
                                </li>
                                <li class="flex items-start gap-4">
                                    <div class="mt-1 w-5 h-5 rounded-full bg-lime-400/10 flex items-center justify-center text-[8px] text-lime-400 font-bold border border-lime-400/20">03</div>
                                    <p class="text-xs text-gray-400 leading-relaxed">Usa exclusivamente SSL/TLS para entornos de producción seguros.</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
