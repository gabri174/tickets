<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

$db = new Database();
$allEvents = $db->getActiveEvents();

// Filtrar eventos: Solo mostrar los que tienen imagen (asumiendo que son los "subidos" completamente)
$events = array_filter($allEvents, function($e) {
    return !empty($e['image_url']);
});
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Tu plataforma de tickets</title>
    <meta name="description" content="Vende y compra tus entradas de forma inteligente y rápida con TicketApp. La plataforma líder para experiencias inolvidables.">
    <meta name="keywords" content="tickets, entradas, eventos, conciertos, teatro, festivales, deportes, TicketApp">
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
                    <div class="w-10 h-10 bg-lime-400 rounded-xl flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-black text-xl"></i>
                    </div>
                    <span class="text-2xl font-black tracking-tighter text-white">TICKETAPP</span>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-8">
                    <a href="index.php" class="text-sm font-semibold text-lime-400">Inicio</a>
                    <a href="about.php" class="text-sm font-semibold text-gray-400 hover:text-white transition">Nosotros</a>
                    <a href="contact.php" class="text-sm font-semibold text-gray-400 hover:text-white transition">Contacto</a>
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

    <!-- Hero Section -->
    <section class="relative overflow-hidden pt-16 pb-24 md:pt-32 md:pb-40">
        <!-- Background Accents -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-full opacity-20 pointer-events-none">
            <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-lime-400/30 blur-[120px] rounded-full"></div>
            <div class="absolute bottom-[10%] right-[-10%] w-[40%] h-[40%] bg-blue-500/20 blur-[120px] rounded-full"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-lime-400/10 border border-lime-400/20 text-lime-400 text-xs font-bold uppercase tracking-widest mb-6 animate-fade-in">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-lime-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-lime-400"></span>
                    </span>
                    La evolución de los tickets digitales
                </div>
                <h2 class="text-5xl md:text-7xl font-black text-white leading-[1.1] mb-8 tracking-tighter">
                    Vende tus entradas de forma <span class="text-gradient">inteligente y rápida</span>.
                </h2>
                <p class="text-lg md:text-xl text-gray-400 mb-10 leading-relaxed">
                    TicketApp es la plataforma premium diseñada para organizadores que buscan simplicidad, seguridad y una experiencia inolvidable para sus asistentes. Tickets digitales con QR, gestión en tiempo real y más.
                </p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="#eventos" class="btn-modern bg-lime-400 text-black px-10 py-4 text-lg font-bold flex items-center justify-center gap-2 group">
                        Explorar Eventos
                        <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </a>
                    <a href="admin/" class="btn-modern bg-white/5 border border-white/10 text-white px-10 py-4 text-lg font-bold hover:bg-white/10 transition flex items-center justify-center gap-2">
                        Gestionar mis Eventos
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Advantages Section -->
    <section class="py-24 bg-white/[0.02] border-y border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <!-- Advantage 1 -->
                <div class="flex flex-col gap-5">
                    <div class="w-14 h-14 bg-lime-400/10 rounded-2xl flex items-center justify-center text-lime-400 text-2xl border border-lime-400/20">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-bold text-white mb-2">QR de Seguridad Único</h4>
                        <p class="text-gray-400 leading-relaxed text-sm">Cada entrada es única y segura. Olvídate de falsificaciones con nuestro sistema de validación dinámica por código QR.</p>
                    </div>
                </div>
                <!-- Advantage 2 -->
                <div class="flex flex-col gap-5">
                    <div class="w-14 h-14 bg-blue-500/10 rounded-2xl flex items-center justify-center text-blue-400 text-2xl border border-blue-500/20">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-bold text-white mb-2">Envío por WhatsApp</h4>
                        <p class="text-gray-400 leading-relaxed text-sm">Tus asistentes reciben sus tickets directamente en su móvil. Instantáneo, ecológico y siempre a mano.</p>
                    </div>
                </div>
                <!-- Advantage 3 -->
                <div class="flex flex-col gap-5">
                    <div class="w-14 h-14 bg-purple-500/10 rounded-2xl flex items-center justify-center text-purple-400 text-2xl border border-purple-500/20">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-bold text-white mb-2">Control en Tiempo Real</h4>
                        <p class="text-gray-400 leading-relaxed text-sm">Monitoriza tus ventas y el acceso al evento desde nuestro panel de administración premium para organizadores.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="eventos" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 gap-8">
            <div class="max-w-2xl">
                <p class="text-lime-400 text-sm font-bold uppercase tracking-widest mb-3">Próximas experiencias</p>
                <h3 class="text-4xl md:text-5xl font-black text-white leading-tight">Encuentra tu próximo <br><span class="text-gradient">momento inolvidable</span></h3>
            </div>
            
            <!-- Search Bar -->
            <div class="relative w-full md:w-80 group">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-lime-400 transition-colors"></i>
                <input type="text" id="eventSearch" placeholder="Buscar eventos..." class="w-full bg-white/5 border border-white/10 rounded-2xl py-4 pl-12 pr-4 text-white outline-none focus:border-lime-400/50 transition-all placeholder:text-gray-600">
            </div>
        </div>

        <!-- Categories (Pills) -->
        <div class="flex gap-3 overflow-x-auto pb-8 no-scrollbar mb-10 border-b border-white/5" id="categoryPills">
            <button data-category="todos" class="category-pill active px-8 py-3 rounded-full bg-lime-400 text-black font-bold text-sm transition-all hover:shadow-[0_0_20px_rgba(218,251,113,0.3)]">Todos</button>
            <button data-category="musica" class="category-pill px-8 py-3 rounded-full bg-white/5 border border-white/10 text-gray-400 font-bold text-sm hover:text-white hover:bg-white/10 transition-all">Música</button>
            <button data-category="conciertos" class="category-pill px-8 py-3 rounded-full bg-white/5 border border-white/10 text-gray-400 font-bold text-sm hover:text-white hover:bg-white/10 transition-all">Conciertos</button>
            <button data-category="teatro" class="category-pill px-8 py-3 rounded-full bg-white/5 border border-white/10 text-gray-400 font-bold text-sm hover:text-white hover:bg-white/10 transition-all">Teatro</button>
            <button data-category="festivales" class="category-pill px-8 py-3 rounded-full bg-white/5 border border-white/10 text-gray-400 font-bold text-sm hover:text-white hover:bg-white/10 transition-all">Festivales</button>
        </div>

        <!-- Events Section (Grid) -->
        <section class="mb-20">
            <div class="event-grid">
                <?php if (!empty($events)): ?>
                    <?php foreach ($events as $event): ?>
                        <div class="event-card-modern" data-category="<?php echo htmlspecialchars($event['category']); ?>" data-title="<?php echo htmlspecialchars(strtolower($event['title'])); ?>">
                            <div class="event-image-container group">
                                <?php if ($event['image_url']): ?>
                                    <img src="<?php echo SITE_URL . '/' . $event['image_url']; ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="transition-transform duration-500 group-hover:scale-110 w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gray-800 flex items-center justify-center">
                                        <i class="fas fa-image text-5xl text-gray-700"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="event-overlay">
                                    <div class="flex justify-between items-end">
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-xl font-bold mb-1 truncate"><?php echo htmlspecialchars($event['title']); ?></h4>
                                            <div class="flex items-center gap-2 text-sm text-gray-300">
                                                <i class="fas fa-calendar-alt text-blue-400"></i>
                                                <span><?php echo formatDate($event['date_event'], 'd M, Y'); ?></span>
                                            </div>
                                        </div>
                                        <div class="text-right flex-shrink-0 ml-4">
                                            <p class="text-lime-400 font-bold text-lg mb-0"><?php echo formatCurrency($event['price']); ?></p>
                                            <p class="text-[10px] text-gray-400 uppercase tracking-tighter"><?php echo $event['available_tickets']; ?> disponibles</p>
                                        </div>
                                    </div>
                                </div>
                                <a href="buy.php?id=<?php echo $event['id']; ?>" class="absolute top-4 right-4 w-12 h-12 glass-pill flex items-center justify-center text-white scale-0 group-hover:scale-100 transition-transform bg-lime-400/20 z-20">
                                    <i class="fas fa-shopping-cart text-lg text-lime-400"></i>
                                </a>
                                <a href="buy.php?id=<?php echo $event['id']; ?>" class="absolute inset-0 z-10"></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </div>

    <!-- Footer -->
    <footer class="bg-[#0A0E14] border-t border-white/5 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="flex items-center justify-center gap-2 mb-6">
                <i class="fas fa-ticket-alt text-lime-400"></i>
                <span class="text-xl font-bold text-white tracking-tighter">TICKETAPP</span>
            </div>
            <p class="text-gray-500 text-sm mb-8">La plataforma líder para tus entradas digitales.</p>
            <div class="flex justify-center gap-6 mb-8 text-gray-400">
                <a href="#" class="hover:text-white transition"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="hover:text-white transition"><i class="fab fa-twitter"></i></a>
                <a href="#" class="hover:text-white transition"><i class="fab fa-instagram"></i></a>
            </div>
            <div class="pt-8 border-t border-white/5 text-gray-600 text-[10px] uppercase tracking-widest font-bold">
                &copy; <?php echo date('Y'); ?> TicketApp. Todos los derechos reservados.
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pills = document.querySelectorAll('.category-pill');
            const cards = document.querySelectorAll('.event-card-modern');
            const searchInput = document.getElementById('eventSearch');
            let currentCategory = 'todos';

            function filterEvents() {
                const searchTerm = searchInput.value.toLowerCase();
                
                cards.forEach(card => {
                    const cardCategory = card.dataset.category;
                    const cardTitle = card.dataset.title;
                    const matchesCategory = currentCategory === 'todos' || cardCategory === currentCategory;
                    const matchesSearch = cardTitle.includes(searchTerm);

                    if (matchesCategory && matchesSearch) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }

            pills.forEach(pill => {
                pill.addEventListener('click', () => {
                    // Reset pills UI
                    pills.forEach(p => {
                        p.classList.remove('bg-lime-400', 'text-black', 'active');
                        p.classList.add('bg-white/5', 'text-gray-400');
                    });
                    
                    // Activate current pill
                    pill.classList.remove('bg-white/5', 'text-gray-400');
                    pill.classList.add('bg-lime-400', 'text-black', 'active');
                    
                    currentCategory = pill.dataset.category;
                    filterEvents();
                });
            });

            searchInput.addEventListener('input', filterEvents);

            // Smooth scroll for anchors
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        });
    </script>
</body>
</html>
