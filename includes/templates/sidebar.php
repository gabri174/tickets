<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Mobile Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside id="adminSidebar" class="glass-sidebar w-72 flex flex-col fixed inset-y-0 left-0 z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 lg:static lg:h-screen">
    <div class="p-8">
        <div class="flex items-center justify-between mb-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-lime-400 rounded-xl flex items-center justify-center shadow-lg shadow-lime-400/20">
                    <i class="fas fa-ticket-alt text-black text-xl"></i>
                </div>
                <span class="text-xl font-black tracking-tighter text-white">TICKET<span class="text-lime-400">APP</span></span>
            </div>
            <button class="lg:hidden text-gray-400 hover:text-white" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <nav class="space-y-2">
            <a href="dashboard.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm <?php echo $current_page === 'dashboard.php' ? 'active' : 'text-gray-500 hover:text-white hover:bg-white/5'; ?>">
                <i class="fas fa-grid-2 text-lg"></i><span>Dashboard</span>
            </a>
            <a href="events.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm <?php echo $current_page === 'events.php' ? 'active' : 'text-gray-500 hover:text-white hover:bg-white/5'; ?>">
                <i class="fas fa-calendar-alt text-lg"></i><span>Gestionar Eventos</span>
            </a>
            <a href="tickets.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm <?php echo $current_page === 'tickets.php' ? 'active' : 'text-gray-500 hover:text-white hover:bg-white/5'; ?>">
                <i class="fas fa-ticket-alt text-lg"></i><span>Ventas & Tickets</span>
            </a>
            <a href="payment_settings.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm <?php echo $current_page === 'payment_settings.php' ? 'active' : 'text-gray-500 hover:text-white hover:bg-white/5'; ?>">
                <i class="fas fa-wallet text-lg"></i><span>Métodos de Pago</span>
            </a>
            <a href="profile.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm <?php echo $current_page === 'profile.php' ? 'active' : 'text-gray-500 hover:text-white hover:bg-white/5'; ?>">
                <i class="fas fa-user-circle text-lg"></i><span>Mi Perfil</span>
            </a>
            
            <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
            <a href="settings.php" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold text-sm <?php echo $current_page === 'settings.php' ? 'active' : 'text-gray-500 hover:text-white hover:bg-white/5'; ?>">
                <i class="fas fa-cog text-lg"></i><span>Configuración</span>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <div class="mt-auto p-6 border-t border-white/5">
        <div class="glass-card p-4 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 bg-white/5 rounded-full flex items-center justify-center border border-white/10 overflow-hidden">
                <?php if (isset($_SESSION['admin_photo']) && $_SESSION['admin_photo']): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['admin_photo']); ?>" alt="Profile" class="w-full h-full object-cover">
                <?php else: ?>
                    <i class="fas fa-user-circle text-gray-400"></i>
                <?php endif; ?>
            </div>
            <div class="flex-1 overflow-hidden">
                <p class="text-xs font-black truncate text-white"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                <p class="text-[10px] text-gray-500 truncate"><?php echo htmlspecialchars($_SESSION['admin_email']); ?></p>
            </div>
            <a href="logout.php" class="text-gray-500 hover:text-red-400 transition-colors p-2">
                <i class="fas fa-power-off"></i>
            </a>
        </div>
        <div class="mt-4 text-center">
            <p class="text-[9px] text-gray-600 font-bold uppercase tracking-widest">
                Created by Creative Technologies<br>by Gabriel Guerra
            </p>
        </div>
    </div>
</aside>

<!-- Mobile Hamburger Button -->
<button class="lg:hidden fixed bottom-6 right-6 w-14 h-14 bg-lime-400 text-black rounded-2xl shadow-2xl z-40 flex items-center justify-center text-xl shadow-lime-400/20" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}
</script>
