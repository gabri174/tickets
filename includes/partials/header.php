<?php
// Header reusable para todas las páginas públicas
// Uso: require_once '../includes/partials/header.php';
// Nota: Asegúrate de que $currentPage esté definido antes de incluir (ej: 'index', 'about', 'contact', 'success')
$currentPage = $currentPage ?? 'index';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $extraHead ?? ''; ?>
    <title><?php echo $pageTitle ?? SITE_NAME; ?></title>
    <?php if (isset($metaDescription)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <?php endif; ?>
    <?php if (isset($metaKeywords)): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($metaKeywords); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: var(--bg-dark); }
        /* Fix para menú móvil - evitar scroll cuando está abierto */
        body.menu-open { overflow: hidden; }
        body.menu-open #mobileMenu { overflow: hidden; }
        /* Asegurar que el overlay del menú esté por encima de todo */
        #mobileMenu > div:first-child { z-index: 61; }
        #mobileMenu nav { z-index: 62; position: relative; }
        <?php echo $extraStyles ?? ''; ?>
    </style>
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
                    <a href="index.php" class="text-sm font-semibold transition <?php echo $currentPage === 'index' ? 'text-lime-400' : 'text-gray-400 hover:text-white'; ?>">Inicio</a>
                    <a href="about.php" class="text-sm font-semibold transition <?php echo $currentPage === 'about' ? 'text-lime-400' : 'text-gray-400 hover:text-white'; ?>">Nosotros</a>
                    <a href="contact.php" class="text-sm font-semibold transition <?php echo $currentPage === 'contact' ? 'text-lime-400' : 'text-gray-400 hover:text-white'; ?>">Contacto</a>
                    <div class="w-px h-6 bg-white/10"></div>
                    <a href="admin/" class="flex items-center gap-2 text-sm font-semibold text-gray-300 hover:text-white transition px-4 py-2 rounded-full bg-white/5 border border-white/10">
                        <i class="fas fa-user-shield text-xs"></i>
                        Administración
                    </a>
                </nav>

                <!-- Mobile Menu Button -->
                <button class="md:hidden text-gray-400 hover:text-white flex-shrink-0" onclick="toggleMobileMenu()" aria-label="Abrir menú">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Drawer -->
        <div id="mobileMenu" class="fixed inset-0 z-[60] hidden md:hidden" role="dialog" aria-modal="true">
            <!-- Overlay -->
            <div class="absolute inset-0 bg-[#0A0E14]/95 backdrop-blur-2xl" onclick="toggleMobileMenu()"></div>

            <!-- Menu Content -->
            <nav class="relative h-full w-full max-w-sm ml-auto flex flex-col items-center justify-center gap-8 p-8 bg-[#0A0E14] border-l border-white/10">
                <button class="absolute top-6 right-6 text-gray-400 hover:text-white text-2xl p-2" onclick="toggleMobileMenu()" aria-label="Cerrar menú">
                    <i class="fas fa-times"></i>
                </button>

                <a href="index.php" onclick="toggleMobileMenu()" class="text-3xl font-bold text-white hover:text-lime-400 transition <?php echo $currentPage === 'index' ? 'text-lime-400' : ''; ?>">Inicio</a>
                <a href="about.php" onclick="toggleMobileMenu()" class="text-3xl font-bold text-white hover:text-lime-400 transition <?php echo $currentPage === 'about' ? 'text-lime-400' : ''; ?>">Nosotros</a>
                <a href="contact.php" onclick="toggleMobileMenu()" class="text-3xl font-bold text-white hover:text-lime-400 transition <?php echo $currentPage === 'contact' ? 'text-lime-400' : ''; ?>">Contacto</a>

                <div class="w-full h-px bg-white/10 my-4"></div>

                <a href="admin/" onclick="toggleMobileMenu()" class="flex items-center gap-3 text-2xl font-bold text-white hover:text-lime-400 transition px-6 py-3 rounded-full bg-white/5 border border-white/10">
                    <i class="fas fa-user-shield text-xl text-lime-400"></i>
                    Administración
                </a>
            </nav>
        </div>
    </header>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const body = document.body;

            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
                body.classList.add('menu-open');
            } else {
                menu.classList.add('hidden');
                body.classList.remove('menu-open');
            }
        }

        // Cerrar menú con tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const menu = document.getElementById('mobileMenu');
                if (!menu.classList.contains('hidden')) {
                    toggleMobileMenu();
                }
            }
        });
    </script>
