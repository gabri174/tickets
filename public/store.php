<?php
require_once '../includes/config/config.php';
require_once '../includes/classes/Database.php';

$db = new Database();

// Obtener ID del organizador desde la URL
$organizerId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$organizerId) {
    header('Location: ../');
    exit();
}

// Obtener datos del organizador
$organizer = $db->getAdminById($organizerId);
if (!$organizer) {
    header('Location: ../');
    exit();
}

// Obtener eventos activos del organizador
$events = $db->getActiveEventsByOrganizer($organizerId);

// Obtener categorías únicas
$categories = array_unique(array_column($events, 'category'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($organizer['username']); ?> - Eventos</title>
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

        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(218, 251, 113, 0.3);
            transform: translateY(-4px);
        }

        .text-gradient {
            background: linear-gradient(to right, #DAFB71, #60A5FA);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-primary {
            background: linear-gradient(135deg, #DAFB71 0%, #a3d94a 100%);
            color: #000;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(218, 251, 113, 0.3);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-[#0A0E14]/90 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-lime-400 rounded-xl flex items-center justify-center">
                    <i class="fas fa-ticket-alt text-black text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black"><?php echo htmlspecialchars($organizer['username']); ?></h1>
                    <p class="text-xs text-gray-500">Eventos disponibles</p>
                </div>
            </div>
            <a href="../" class="px-4 py-2 rounded-full bg-white/5 border border-white/10 text-xs font-bold text-gray-300 hover:text-white hover:bg-white/10 transition">
                <i class="fas fa-home mr-2"></i>Inicio
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative py-16 px-4 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-lime-400/5 to-transparent pointer-events-none"></div>
        <div class="max-w-7xl mx-auto text-center relative z-10">
            <h2 class="text-4xl md:text-6xl font-black mb-4">
                <span class="text-gradient">Eventos</span> Disponibles
            </h2>
            <p class="text-gray-400 text-lg max-w-2xl mx-auto">
                Explora todos los eventos organizados por <?php echo htmlspecialchars($organizer['username']); ?>
            </p>
        </div>
    </section>

    <!-- Events Grid -->
    <section class="max-w-7xl mx-auto px-4 pb-20">
        <?php if (empty($events)): ?>
            <div class="text-center py-20">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white/5 rounded-full mb-6">
                    <i class="fas fa-calendar-times text-4xl text-gray-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-500 mb-2">No hay eventos disponibles</h3>
                <p class="text-gray-600">Este organizador aún no ha publicado eventos</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($events as $event): ?>
                    <?php
                    $eventDate = date('d/m/Y', strtotime($event['date_event']));
                    $eventTime = date('H:i', strtotime($event['date_event']));
                    $availableTickets = $event['available_tickets'];
                    $isSoldOut = $availableTickets <= 0;
                    ?>
                    <div class="glass-card rounded-[2rem] overflow-hidden group">
                        <!-- Event Image -->
                        <div class="relative h-48 overflow-hidden">
                            <?php if ($event['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($event['image_url']); ?>"
                                     alt="<?php echo htmlspecialchars($event['title']); ?>"
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-lime-400/20 to-blue-500/20 flex items-center justify-center">
                                    <i class="fas fa-calendar-alt text-6xl text-white/20"></i>
                                </div>
                            <?php endif; ?>

                            <?php if ($isSoldOut): ?>
                                <div class="absolute inset-0 bg-black/70 flex items-center justify-center">
                                    <span class="px-4 py-2 bg-red-500 text-white font-black text-sm rounded-full">SOLD OUT</span>
                                </div>
                            <?php endif; ?>

                            <!-- Category Badge -->
                            <div class="absolute top-4 right-4">
                                <span class="px-3 py-1 bg-black/60 backdrop-blur-sm rounded-full text-xs font-bold text-white">
                                    <?php echo htmlspecialchars($event['category']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Event Info -->
                        <div class="p-6">
                            <h3 class="text-xl font-bold mb-3 line-clamp-2 group-hover:text-lime-400 transition-colors">
                                <?php echo htmlspecialchars($event['title']); ?>
                            </h3>

                            <p class="text-gray-400 text-sm mb-4 line-clamp-2">
                                <?php echo htmlspecialchars($event['description']); ?>
                            </p>

                            <!-- Event Details -->
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <i class="fas fa-calendar text-lime-400"></i>
                                    <span><?php echo $eventDate; ?></span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <i class="fas fa-clock text-lime-400"></i>
                                    <span><?php echo $eventTime; ?></span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <i class="fas fa-map-marker-alt text-lime-400"></i>
                                    <span><?php echo htmlspecialchars($event['location']); ?></span>
                                </div>
                            </div>

                            <!-- Price and CTA -->
                            <div class="flex items-center justify-between pt-4 border-t border-white/10">
                                <div>
                                    <span class="text-2xl font-black text-lime-400">
                                        <?php echo number_format($event['price'], 2); ?>€
                                    </span>
                                    <p class="text-xs text-gray-500">por entrada</p>
                                </div>
                                <a href="event.php?id=<?php echo $event['id']; ?>"
                                   class="btn-primary px-6 py-3 rounded-full text-sm <?php echo $isSoldOut ? 'opacity-50 pointer-events-none' : ''; ?>">
                                    <?php echo $isSoldOut ? 'Agotado' : 'Comprar'; ?>
                                    <?php if (!$isSoldOut): ?>
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    <?php endif; ?>
                                </a>
                            </div>

                            <!-- Available Tickets -->
                            <div class="mt-3 text-center">
                                <span class="text-xs text-gray-500">
                                    <?php echo $availableTickets; ?> entradas disponibles
                                </span>
                                <div class="w-full bg-gray-800 rounded-full h-1.5 mt-2 overflow-hidden">
                                    <?php
                                    $maxTickets = $event['max_tickets'] ?? $availableTickets;
                                    $percentage = $maxTickets > 0 ? ($availableTickets / $maxTickets) * 100 : 0;
                                    ?>
                                    <div class="h-full bg-gradient-to-r from-lime-400 to-green-500 rounded-full transition-all duration-500"
                                         style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="border-t border-white/5 py-8 text-center">
        <p class="text-gray-600 text-sm">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($organizer['username']); ?>. Todos los derechos reservados.
        </p>
    </footer>
</body>
</html>
