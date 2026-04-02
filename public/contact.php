<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

$currentPage = 'contact';
$pageTitle = 'Contacto - ' . SITE_NAME;
require_once '../includes/partials/header.php';
?>

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

<?php require_once '../includes/partials/footer.php'; ?>
