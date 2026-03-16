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
        <header class="flex justify-between items-center mb-8 pt-4">
            <div>
                <p class="text-gray-400 text-sm font-medium">¡Hola, bienvenido!</p>
                <h2 class="heading-xl">Explorar Eventos</h2>
            </div>
            <div class="flex gap-3">
                <button class="glass-pill w-12 h-12 flex items-center justify-center text-lg">
                    <i class="fas fa-search"></i>
                </button>
                <a href="admin/" class="glass-pill w-12 h-12 flex items-center justify-center text-lg">
                    <i class="fas fa-user-shield"></i>
                </a>
            </div>
        </header>

        <!-- Categories (Pills) -->
        <div class="flex gap-3 overflow-x-auto pb-6 no-scrollbar">
            <button class="glass-pill px-6 py-2 whitespace-nowrap bg-lime-400 text-black border-none font-semibold">Todos</button>
            <button class="glass-pill px-6 py-2 whitespace-nowrap bg-transparent text-gray-400 font-medium hover:text-white">Música</button>
            <button class="glass-pill px-6 py-2 whitespace-nowrap bg-transparent text-gray-400 font-medium hover:text-white">Festivales</button>
            <button class="glass-pill px-6 py-2 whitespace-nowrap bg-transparent text-gray-400 font-medium hover:text-white">Arte</button>
            <button class="glass-pill px-6 py-2 whitespace-nowrap bg-transparent text-gray-400 font-medium hover:text-white">Teatro</button>
        </div>

        <!-- Featured Section (Horizontal Scroll) -->
        <section class="mb-10">
            <div class="flex justify-between items-end mb-4">
                <h3 class="text-xl font-bold">Destacados</h3>
                <a href="#" class="text-lime-400 text-sm font-semibold">Ver todos</a>
            </div>
            
            <div class="flex gap-4 overflow-x-auto pb-4 no-scrollbar">
                <?php if (!empty($events)): ?>
                    <?php foreach (array_slice($events, 0, 3) as $event): ?>
                        <div class="event-card-modern min-w-[280px]">
                            <div class="event-image-container">
                                <?php if ($event['image_url']): ?>
                                    <img src="<?php echo SITE_URL . '/' . $event['image_url']; ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gray-800 flex items-center justify-center">
                                        <i class="fas fa-image text-4xl text-gray-600"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="event-overlay">
                                    <h4 class="text-lg font-bold mb-1"><?php echo htmlspecialchars($event['title']); ?></h4>
                                    <div class="flex items-center gap-2 text-xs text-gray-300">
                                        <i class="fas fa-calendar-alt text-blue-400"></i>
                                        <span><?php echo formatDate($event['date_event'], 'd M, Y'); ?></span>
                                    </div>
                                </div>
                                <a href="buy.php?id=<?php echo $event['id']; ?>" class="absolute top-4 right-4 w-10 h-10 glass-pill flex items-center justify-center text-white">
                                    <i class="fas fa-arrow-right -rotate-45"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Upcoming Events (Vertical List) -->
        <section class="mb-10">
            <h3 class="text-xl font-bold mb-4">Próximos para ti</h3>
            <div class="space-y-4">
                <?php if (empty($events)): ?>
                    <div class="glass-card p-10 text-center">
                        <i class="fas fa-calendar-times text-4xl text-gray-600 mb-3"></i>
                        <p class="text-gray-400">No hay eventos disponibles</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <div class="glass-card p-3 flex gap-4 items-center">
                            <div class="w-20 h-20 rounded-xl overflow-hidden shadow-lg flex-shrink-0">
                                <?php if ($event['image_url']): ?>
                                    <img src="<?php echo SITE_URL . '/' . $event['image_url']; ?>" alt="" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gray-800 flex items-center justify-center text-gray-600">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-gray-100 truncate"><?php echo htmlspecialchars($event['title']); ?></h4>
                                <p class="text-blue-400 text-xs font-semibold mb-1"><?php echo formatDate($event['date_event'], 'd Octubre'); ?></p>
                                <div class="flex justify-between items-center">
                                    <span class="text-lime-400 font-bold"><?php echo formatCurrency($event['price']); ?></span>
                                    <span class="text-[10px] text-gray-500 font-medium px-2 py-1 glass-pill"><?php echo $event['available_tickets']; ?> Libres</span>
                                </div>
                            </div>
                            <a href="buy.php?id=<?php echo $event['id']; ?>" class="w-10 h-10 flex items-center justify-center bg-gray-800 rounded-full hover:bg-lime-400 hover:text-black transition">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
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
