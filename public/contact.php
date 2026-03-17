<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - <?php echo SITE_NAME; ?></title>
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
                    <a href="about.php" class="text-sm font-semibold text-gray-400 hover:text-white transition">Nosotros</a>
                    <a href="contact.php" class="text-sm font-semibold text-lime-400">Contacto</a>
                    <div class="w-px h-6 bg-white/10 mx-2"></div>
                    <a href="admin/" class="flex items-center gap-2 text-sm font-semibold text-gray-300 hover:text-white transition px-4 py-2 rounded-full bg-white/5 border border-white/10">
                        <i class="fas fa-user-shield text-xs"></i>
                        Administración
                    </a>
                </nav>

                <!-- Mobile Menu Button -->
                <button class="md:hidden text-gray-400 hover:text-white">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-start">
            
            <!-- Information Side -->
            <div class="animate-fade-in">
                <p class="text-lime-400 text-sm font-bold uppercase tracking-widest mb-4">Estamos aquí para ayudarte</p>
                <h1 class="text-5xl md:text-6xl font-black text-white leading-tight tracking-tighter mb-8">Ponte en <span class="text-gradient">Contacto</span> con nosotros</h1>
                <p class="text-lg text-gray-400 mb-12 leading-relaxed">
                    ¿Tienes dudas sobre cómo organizar tu evento o necesitas soporte técnico? Nuestro equipo está listo para asistirte en cada paso del camino.
                </p>

                <div class="space-y-8">
                    <div class="flex items-center gap-6 group">
                        <div class="w-16 h-16 bg-white/5 border border-white/10 rounded-2xl flex items-center justify-center text-xl text-lime-400 group-hover:bg-lime-400 group-hover:text-black transition-all duration-300">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest mb-1">Escríbenos</p>
                            <p class="text-xl font-bold text-white">soporte@ensupresencia.eu</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 group">
                        <div class="w-16 h-16 bg-white/5 border border-white/10 rounded-2xl flex items-center justify-center text-xl text-blue-400 group-hover:bg-blue-400 group-hover:text-white transition-all duration-300">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-500 uppercase font-bold tracking-widest mb-1">WhatsApp Business</p>
                            <p class="text-xl font-bold text-white">+34 000 000 000</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Side -->
            <div class="relative">
                <div class="absolute -inset-4 bg-lime-400/5 blur-3xl opacity-50 rounded-full pointer-events-none"></div>
                <div class="glass-card relative p-8 md:p-12">
                    <form action="https://formspree.io/f/meerrrpb" method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-widest">Nombre Completo</label>
                                <input type="text" name="name" required placeholder="Tu nombre"
                                       class="w-full bg-white/5 border border-white/10 rounded-xl py-4 px-5 text-white outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-600">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-widest">Email</label>
                                <input type="email" name="_replyto" required placeholder="tu@email.com"
                                       class="w-full bg-white/5 border border-white/10 rounded-xl py-4 px-5 text-white outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-600">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-widest">Asunto</label>
                            <input type="text" name="subject" required placeholder="¿En qué podemos ayudarte?"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-4 px-5 text-white outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-600">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-widest">Mensaje</label>
                            <textarea name="message" required rows="5" placeholder="Cuéntanos más detalles..."
                                      class="w-full bg-white/5 border border-white/10 rounded-xl py-4 px-5 text-white outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-600 resize-none"></textarea>
                        </div>
                        
                        <button type="submit" class="btn-modern bg-lime-400 text-black w-full py-5 text-lg font-bold hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all">
                            Enviar Mensaje <i class="fas fa-paper-plane ml-2"></i>
                        </button>

                        <p class="text-[10px] text-center text-gray-600 uppercase tracking-widest">
                            Respondemos en menos de 24 horas laborales
                        </p>
                    </form>
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
