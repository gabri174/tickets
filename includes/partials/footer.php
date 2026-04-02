    <!-- Footer -->
    <footer class="bg-[#0A0E14] border-t border-white/5 py-12 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <!-- Logo y descripción -->
                <div class="text-center md:text-left">
                    <div class="flex items-center justify-center md:justify-start gap-2 mb-4">
                        <div class="w-10 h-10 bg-lime-400 rounded-xl flex items-center justify-center">
                            <i class="fas fa-ticket-alt text-black text-xl"></i>
                        </div>
                        <span class="text-xl font-bold text-white tracking-tighter">TICKETAPP</span>
                    </div>
                    <p class="text-gray-400 text-sm">La plataforma líder para la gestión de eventos y ticketing digital.</p>
                </div>

                <!-- Enlaces rápidos -->
                <div class="text-center">
                    <h4 class="text-white font-bold mb-4">Enlaces</h4>
                    <div class="flex flex-col gap-2">
                        <a href="index.php" class="text-gray-400 hover:text-lime-400 text-sm transition">Inicio</a>
                        <a href="about.php" class="text-gray-400 hover:text-lime-400 text-sm transition">Nosotros</a>
                        <a href="contact.php" class="text-gray-400 hover:text-lime-400 text-sm transition">Contacto</a>
                    </div>
                </div>

                <!-- Contacto -->
                <div class="text-center md:text-right">
                    <h4 class="text-white font-bold mb-4">Contacto</h4>
                    <p class="text-gray-400 text-sm">¿Necesitas ayuda?</p>
                    <a href="mailto:admin@tickets.com" class="text-lime-400 hover:text-lime-300 text-sm font-bold transition">admin@tickets.com</a>
                </div>
            </div>

            <div class="pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-gray-600 text-[10px] uppercase tracking-widest font-bold">
                    &copy; <?php echo date('Y'); ?> TicketApp. Todos los derechos reservados.
                </p>
                <div class="flex gap-4">
                    <a href="#" class="text-gray-500 hover:text-lime-400 transition text-sm">Privacidad</a>
                    <a href="#" class="text-gray-500 hover:text-lime-400 transition text-sm">Términos</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
