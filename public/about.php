<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nosotros y Comisiones - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Main Header / Navbar -->
    <header class="sticky top-0 z-50 bg-[#0A0E14]/80 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <a href="index.php" class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-lime-400 rounded-xl flex items-center justify-center">
                            <i class="fas fa-ticket-alt text-black text-xl"></i>
                        </div>
                        <span class="text-2xl font-black tracking-tighter text-white">TICKETAPP</span>
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-8">
                    <a href="index.php" class="text-sm font-semibold text-gray-400 hover:text-white transition">Inicio</a>
                    <a href="about.php" class="text-sm font-semibold text-lime-400">Nosotros</a>
                    <a href="contact.php" class="text-sm font-semibold text-gray-400 hover:text-white transition">Contacto</a>
                    <div class="w-px h-6 bg-white/10 mx-2"></div>
                    <a href="admin/" class="flex items-center gap-2 text-sm font-semibold text-gray-300 hover:text-white transition px-4 py-2 rounded-full bg-white/5 border border-white/10">
                        <i class="fas fa-user-shield text-xs"></i>
                        Administración
                    </a>
                </nav>

                <!-- Mobile Menu Button -->
                <button class="md:hidden text-gray-400 hover:text-white" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Drawer -->
        <div id="mobileMenu" class="fixed inset-0 z-[60] hidden md:hidden">
            <!-- Overlay -->
            <div class="absolute inset-0 bg-[#0A0E14]/95 backdrop-blur-2xl" onclick="toggleMobileMenu()"></div>
            
            <!-- Menu Content -->
            <nav class="relative h-full flex flex-col items-center justify-center gap-8 p-8">
                <button class="absolute top-8 right-8 text-gray-400 hover:text-white text-2xl" onclick="toggleMobileMenu()">
                    <i class="fas fa-times"></i>
                </button>
                
                <a href="index.php" class="text-3xl font-bold text-white hover:text-lime-400 transition" onclick="toggleMobileMenu()">Inicio</a>
                <a href="about.php" class="text-3xl font-bold text-lime-400" onclick="toggleMobileMenu()">Nosotros</a>
                <a href="contact.php" class="text-3xl font-bold text-white hover:text-lime-400 transition" onclick="toggleMobileMenu()">Contacto</a>
                
                <div class="w-full h-px bg-white/10 my-4"></div>
                
                <a href="admin/" class="flex items-center gap-3 text-2xl font-bold text-gray-300 hover:text-white transition" onclick="toggleMobileMenu()">
                    <i class="fas fa-user-shield text-xl text-lime-400"></i>
                    Administración
                </a>
            </nav>
        </div>
    </header>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
            document.body.style.overflow = menu.classList.contains('hidden') ? 'auto' : 'hidden';
        }
    </script>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center mb-20 animate-fade-in">
            <p class="text-lime-400 text-sm font-bold uppercase tracking-widest mb-4">Conoce TicketApp</p>
            <h1 class="text-5xl md:text-6xl font-black text-white leading-tight tracking-tighter">Sobre <span class="text-gradient">Nosotros</span></h1>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center mb-32">
            <div class="space-y-6">
                <h2 class="text-3xl font-bold text-white">Nuestra Misión</h2>
                <p class="text-gray-400 leading-relaxed text-lg">
                    En TicketApp, transformamos la forma en que los organizadores gestionan sus eventos. Nacimos con la visión de eliminar la fricción entre la creación de un evento y la entrega de la entrada al asistente.
                </p>
                <div class="space-y-4">
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-lime-400/10 flex items-center justify-center text-lime-400 flex-shrink-0 mt-1">
                            <i class="fas fa-check text-xs"></i>
                        </div>
                        <p class="text-gray-300">Entradas digitales 100% seguras con tecnología QR.</p>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-lime-400/10 flex items-center justify-center text-lime-400 flex-shrink-0 mt-1">
                            <i class="fas fa-check text-xs"></i>
                        </div>
                        <p class="text-gray-300">Gestión simplificada para organizadores de todos los niveles.</p>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 rounded-lg bg-lime-400/10 flex items-center justify-center text-lime-400 flex-shrink-0 mt-1">
                            <i class="fas fa-check text-xs"></i>
                        </div>
                        <p class="text-gray-300">Soporte directo y personalizado para tus eventos.</p>
                    </div>
                </div>
            </div>
            <div class="relative group">
                <div class="absolute -inset-1 bg-gradient-to-r from-lime-400 to-blue-500 rounded-3xl blur opacity-10 group-hover:opacity-20 transition duration-1000"></div>
                <div class="relative bg-white/5 border border-white/10 rounded-3xl p-8 backdrop-blur-xl">
                    <i class="fas fa-users text-6xl text-lime-400 mb-6"></i>
                    <h3 class="text-2xl font-bold text-white mb-4">Un equipo comprometido</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Somos expertos en tecnología y gestión de eventos trabajando para que tu única preocupación sea el éxito de tu convocatoria.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white/5 border border-white/10 rounded-[2.5rem] p-12 md:p-16 mb-24 relative overflow-hidden group">
            <div class="absolute top-0 right-0 -translate-y-1/2 translate-x-1/2 w-96 h-96 bg-lime-400/5 blur-[100px] rounded-full pointer-events-none"></div>
            
            <div class="text-center mb-16 px-4">
                <h2 class="text-4xl font-black text-white mb-4 tracking-tighter">Nuestras <span class="text-gradient">Comisiones</span></h2>
                <p class="text-gray-400 max-w-2xl mx-auto">Transparencia total. Sin costos ocultos, solo pagas cuando vendes.</p>
            </div>

            <div class="md:px-20 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="glass-card p-8 border-lime-400/20 bg-lime-400/[0.02]">
                    <p class="text-lime-400 font-bold mb-2 uppercase tracking-widest text-xs">Evento Estándar</p>
                    <div class="flex items-baseline gap-2 mb-4">
                        <span class="text-5xl font-black text-white">5%</span>
                        <span class="text-gray-400 text-lg">por ticket</span>
                    </div>
                    <ul class="space-y-3 text-sm text-gray-500">
                        <li><i class="fas fa-check text-lime-400 mr-2"></i> Gestión de QR</li>
                        <li><i class="fas fa-check text-lime-400 mr-2"></i> Confirmación por Email</li>
                        <li><i class="fas fa-check text-lime-400 mr-2"></i> Soporte 24/7</li>
                    </ul>
                </div>
                <div class="glass-card p-8 border-blue-500/20 bg-blue-500/[0.02]">
                    <p class="text-blue-400 font-bold mb-2 uppercase tracking-widest text-xs">Eventos Gratuitos</p>
                    <div class="flex items-baseline gap-2 mb-4">
                        <span class="text-5xl font-black text-white">0%</span>
                        <span class="text-gray-400 text-lg">siempre gratis</span>
                    </div>
                    <ul class="space-y-3 text-sm text-gray-500">
                        <li><i class="fas fa-check text-blue-400 mr-2"></i> Todas las funciones</li>
                        <li><i class="fas fa-check text-blue-400 mr-2"></i> Distribución masiva</li>
                        <li><i class="fas fa-check text-blue-400 mr-2"></i> Sin cobro mínimo</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-[#0A0E14] border-t border-white/5 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="flex items-center justify-center gap-2 mb-6">
                <i class="fas fa-ticket-alt text-lime-400"></i>
                <span class="text-xl font-bold text-white tracking-tighter">TICKETAPP</span>
            </div>
            <p class="text-gray-500 text-sm mb-8">La plataforma líder para tus entradas digitales.</p>
            <div class="pt-8 border-t border-white/5 text-gray-600 text-[10px] uppercase tracking-widest font-bold">
                &copy; <?php echo date('Y'); ?> TicketApp. Todos los derechos reservados.
            </div>
        </div>
    </footer>
</body>
</html>
