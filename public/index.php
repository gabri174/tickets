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
    <!-- Splash Screen (Simulado) -->
    <div id="splash" class="fixed inset-0 z-50 bg-black flex items-center justify-center transition-opacity duration-500">
        <div class="text-center">
            <i class="fas fa-ticket-alt text-6xl text-lime-400 mb-4 animate-bounce"></i>
            <h1 class="text-3xl font-bold tracking-tighter">TICKETAPP</h1>
        </div>
    </div>

    <div class="app-container">
        <!-- Header / Greeting -->
        <header class="flex justify-between items-center mb-12 pt-6">
            <div class="flex items-center gap-8">
                <div class="flex items-center gap-2">
                    <i class="fas fa-ticket-alt text-2xl text-lime-400"></i>
                    <h1 class="text-2xl font-bold tracking-tighter">TICKETAPP</h1>
                </div>
                <!-- Desktop Nav -->
                <nav class="hidden md:flex gap-6 mt-1">
                    <a href="index.php" class="text-sm font-medium hover:text-lime-400 transition">Inicio</a>
                    <a href="#" class="text-sm font-medium text-gray-400 hover:text-white transition">Eventos</a>
                    <a href="#" class="text-sm font-medium text-gray-400 hover:text-white transition">Mis Tickets</a>
                </nav>
            </div>
            
            <div class="flex gap-3">
                <div class="hidden md:flex items-center mr-4 bg-white/5 border border-white/10 rounded-full px-4 py-1.5 self-center">
                    <i class="fas fa-search text-gray-500 mr-2 text-xs"></i>
                    <input type="text" placeholder="Buscar eventos..." class="bg-transparent border-none outline-none text-xs w-48 text-white">
                </div>
                <button class="glass-pill w-12 h-12 flex items-center justify-center text-lg md:hidden">
                    <i class="fas fa-search"></i>
                </button>
                <a href="admin/" class="glass-pill w-12 h-12 flex items-center justify-center text-lg">
                    <i class="fas fa-user-shield"></i>
                </a>
            </div>
        </header>
        
        <div class="mb-10">
            <p class="text-gray-400 text-sm font-medium">¡Hola, bienvenido!</p>
            <h2 class="heading-xl">Explorar Eventos</h2>
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

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="index.php" class="nav-item active"><i class="fas fa-home"></i></a>
        <a href="#" class="nav-item"><i class="fas fa-search"></i></a>
        <a href="#" class="nav-item"><i class="fas fa-ticket-alt"></i></a>
        <a href="#" class="nav-item"><i class="fas fa-user"></i></a>
    </nav>

    <script>
        // Splash Screen Hide
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.getElementById('splash').classList.add('hide');
            }, 1000);
        });

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
