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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-gray-light: #d9d9d9;
            --color-gray-dark: #363c40;
            --color-gray-medium: #babebf;
            --color-gray-muted: #848b8c;
            --color-black: #202426;
        }
        
        body {
            background-color: var(--color-gray-light);
            color: var(--color-gray-dark);
        }
        
        .bg-primary { background-color: var(--color-gray-dark); }
        .text-primary { color: var(--color-gray-dark); }
        .text-muted { color: var(--color-gray-muted); }
        .border-custom { border-color: var(--color-gray-medium); }
        
        .event-card {
            background: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background-color: var(--color-gray-dark);
            color: white;
            transition: background-color 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--color-black);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="bg-primary text-white shadow-lg">
        <nav class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-ticket-alt text-2xl"></i>
                    <h1 class="text-2xl font-bold">Tickets</h1>
                </div>
                <div class="flex items-center space-x-6">
                    <a href="#events" class="hover:text-gray-300 transition">Eventos</a>
                    <a href="#contact" class="hover:text-gray-300 transition">Contacto</a>
                    <a href="../admin/" class="bg-white text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                        <i class="fas fa-user-shield mr-2"></i>Admin
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-b from-gray-800 to-gray-900 text-white py-20">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-5xl font-bold mb-6">Descubre Eventos Increíbles</h2>
            <p class="text-xl mb-8 text-gray-300">Compra tus entradas de forma segura y rápida</p>
            <a href="#events" class="btn-primary px-8 py-4 rounded-lg text-lg font-semibold inline-block">
                Ver Eventos <i class="fas fa-arrow-down ml-2"></i>
            </a>
        </div>
    </section>

    <!-- Events Section -->
    <section id="events" class="py-16">
        <div class="container mx-auto px-6">
            <h2 class="text-4xl font-bold text-center mb-12 text-primary">Próximos Eventos</h2>
            
            <?php if (empty($events)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-calendar-times text-6xl text-muted mb-4"></i>
                    <p class="text-xl text-muted">No hay eventos disponibles en este momento</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($events as $event): ?>
                        <div class="event-card rounded-lg overflow-hidden shadow-lg border border-custom">
                            <?php if ($event['image_url']): ?>
                                <img src="<?php echo SITE_URL . '/' . $event['image_url']; ?>" 
                                     alt="<?php echo htmlspecialchars($event['title']); ?>" 
                                     class="w-full h-48 object-cover">
                            <?php else: ?>
                                <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                    <i class="fas fa-image text-4xl text-gray-400"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="p-6">
                                <h3 class="text-xl font-bold mb-2 text-primary"><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p class="text-muted mb-4 line-clamp-2"><?php echo htmlspecialchars($event['description']); ?></p>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex items-center text-sm text-muted">
                                        <i class="fas fa-calendar-alt mr-2"></i>
                                        <?php echo formatDate($event['date_event']); ?>
                                    </div>
                                    <div class="flex items-center text-sm text-muted">
                                        <i class="fas fa-map-marker-alt mr-2"></i>
                                        <?php echo htmlspecialchars($event['location']); ?>
                                    </div>
                                    <div class="flex items-center text-sm text-muted">
                                        <i class="fas fa-ticket-alt mr-2"></i>
                                        <?php echo $event['available_tickets']; ?> disponibles
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-2xl font-bold text-primary"><?php echo formatCurrency($event['price']); ?></span>
                                    <?php if ($event['available_tickets'] > 0): ?>
                                        <a href="buy.php?id=<?php echo $event['id']; ?>" 
                                           class="btn-primary px-4 py-2 rounded-lg hover:opacity-90 transition">
                                            Comprar <i class="fas fa-shopping-cart ml-1"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="bg-red-500 text-white px-4 py-2 rounded-lg">
                                            Agotado
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-primary text-white py-8">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos los derechos reservados.</p>
                </div>
                <div class="flex space-x-6">
                    <a href="#" class="hover:text-gray-300 transition">
                        <i class="fab fa-facebook text-xl"></i>
                    </a>
                    <a href="#" class="hover:text-gray-300 transition">
                        <i class="fab fa-twitter text-xl"></i>
                    </a>
                    <a href="#" class="hover:text-gray-300 transition">
                        <i class="fab fa-instagram text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling
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
