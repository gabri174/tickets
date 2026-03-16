<?php
require_once '../includes/config/config.php';
require_once '../includes/functions/functions.php';
require_once '../includes/classes/Database.php';

$db = new Database();
$events = $db->getActiveEvents();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
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
                    <a href="#" class="text-sm font-semibold text-gray-400 hover:text-white transition">Eventos</a>
                    <a href="#" class="text-sm font-semibold text-gray-400 hover:text-white transition">Mis Tickets</a>
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

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-12">
            <p class="text-lime-400 text-sm font-bold uppercase tracking-widest mb-2">¡Bienvenido!</p>
            <h2 class="text-4xl sm:text-5xl font-black text-white leading-tight">Explorar Últimos <span class="text-gradient">Eventos</span></h2>
        </div>

        <!-- Categories (Pills) -->
        <div class="flex gap-3 overflow-x-auto pb-6 no-scrollbar">
            <button class="glass-pill px-6 py-2 whitespace-nowrap bg-lime-400 text-black border-none font-semibold">Todos</button>
            <button class="glass-pill px-6 py-2 whitespace-nowrap bg-transparent text-gray-400 font-medium hover:text-white">Música</button>
            <button class="glass-pill px-6 py-2 whitespace-nowrap bg-transparent text-gray-400 font-medium hover:text-white">Festivales</button>
            <button class="glass-pill px-6 py-2 whitespace-nowrap bg-transparent text-gray-400 font-medium hover:text-white">Arte</button>
            <button class="glass-pill px-6 py-2 whitespace-nowrap bg-transparent text-gray-400 font-medium hover:text-white">Teatro</button>
        </div>

        <!-- Featured Section (Grid) -->
        <section class="mb-14">
            <div class="flex justify-between items-end mb-6">
                <h3 class="text-2xl font-bold">Eventos Destacados</h3>
                <a href="#" class="text-lime-400 text-sm font-semibold">Ver todos</a>
            </div>
            
            <div class="event-grid">
                <?php if (!empty($events)): ?>
                    <?php foreach ($events as $event): ?>
                        <div class="event-card-modern">
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

        <!-- Upcoming Events Removed/Integrated into Grid for broader layout -->
    </div>

        <!-- Upcoming Events Removed/Integrated into Grid for broader layout -->
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
        // Simple smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>
