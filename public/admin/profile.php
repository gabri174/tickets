<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

checkAdminSession();

$db = new Database();
$adminId = $_SESSION['admin_id'];
$message = '';
$error = '';

// Obtener datos actuales
$admin = $db->getAdminById($adminId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad (CSRF). Por favor, intenta de nuevo.';
    } else {
        $data = [
            'username' => cleanInput($_POST['username']),
            'email' => cleanInput($_POST['email']),
            'company_name' => cleanInput($_POST['company_name']),
            'company_vat' => cleanInput($_POST['company_vat']),
            'company_address' => cleanInput($_POST['company_address']),
            'company_phone' => cleanInput($_POST['company_phone'])
        ];

        // Procesar foto de perfil
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $photoName = uploadImage($_FILES['profile_photo'], UPLOADS_PATH);
            if ($photoName) {
                $data['profile_photo'] = 'uploads/' . $photoName;
                
                // Eliminar foto anterior si existe
                if ($admin['profile_photo'] && file_exists(ROOT_PATH . '/public/' . $admin['profile_photo'])) {
                    unlink(ROOT_PATH . '/public/' . $admin['profile_photo']);
                }
                
                // Actualizar sesión inmediatamente
                $_SESSION['admin_photo'] = $data['profile_photo'];
            }
        }

        if ($db->updateAdminProfile($adminId, $data)) {
            $message = "Perfil actualizado correctamente.";
            // Actualizar sesión
            $_SESSION['admin_username'] = $data['username'];
            $_SESSION['admin_email'] = $data['email'];
            if (isset($data['profile_photo'])) {
                $_SESSION['admin_photo'] = $data['profile_photo'];
            }
            // Recargar datos
            $admin = $db->getAdminById($adminId);
        } else {
            $error = "Error al actualizar el perfil.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Admin <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0A0E14; color: white; font-family: 'Outfit', sans-serif; min-height: 100vh; }
        .glass-sidebar { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border-right: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-card { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); transition: all 0.3s ease; }
        .nav-link { transition: all 0.2s ease; position: relative; }
        .nav-link.active { background: rgba(218, 251, 113, 0.1); color: #DAFB71; }
        .nav-link.active::before { content: ''; position: absolute; left: 0; top: 20%; bottom: 20%; width: 3px; background: #DAFB71; border-radius: 0 4px 4px 0; }
        .text-gradient { background: linear-gradient(to right, #DAFB71, #60A5FA); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        input, textarea { background: rgba(255, 255, 255, 0.05) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; color: white !important; }
        input:focus, textarea:focus { border-color: rgba(218, 251, 113, 0.5) !important; box-shadow: 0 0 15px rgba(218, 251, 113, 0.1) !important; }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen overflow-x-hidden">
    <?php include '../../includes/templates/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-4 lg:p-8 relative">
        <div class="max-w-4xl mx-auto">
            <header class="mb-10">
                <h2 class="text-3xl font-black tracking-tighter">Mi <span class="text-gradient">Perfil</span></h2>
                <p class="text-gray-500 text-sm italic">Gestiona tu identidad y datos de facturación</p>
            </header>

            <?php if ($message): ?>
                <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 p-4 rounded-2xl mb-6 flex items-center gap-3">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <?php echo csrf_field(); ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Foto de Perfil -->
                    <div class="glass-card p-8 rounded-[2rem] flex flex-col items-center justify-center text-center">
                        <div class="relative group cursor-pointer" onclick="document.getElementById('photoInput').click()">
                            <div class="w-32 h-32 rounded-full overflow-hidden border-4 border-white/5 group-hover:border-lime-400/50 transition-all">
                                <?php if ($admin['profile_photo']): ?>
                                    <img src="../<?php echo htmlspecialchars($admin['profile_photo']); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full bg-white/5 flex items-center justify-center">
                                        <i class="fas fa-user text-4xl text-gray-600"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="absolute inset-0 bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <i class="fas fa-camera text-2xl text-white"></i>
                            </div>
                            <input type="file" name="profile_photo" id="photoInput" class="hidden" accept="image/*">
                        </div>
                        <h3 class="mt-6 font-black text-lg"><?php echo htmlspecialchars($admin['username']); ?></h3>
                        <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mt-1"><?php echo htmlspecialchars($admin['role']); ?></p>
                    </div>

                    <!-- Datos de Usuario -->
                    <div class="md:col-span-2 glass-card p-8 rounded-[2rem] space-y-4">
                        <h3 class="font-bold text-sm uppercase tracking-widest text-lime-400 mb-4">Información de Acceso</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Usuario</label>
                                <input type="text" name="username" class="w-full p-4 rounded-xl outline-none" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Email</label>
                                <input type="email" name="email" class="w-full p-4 rounded-xl outline-none" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Datos de Empresa -->
                <div class="glass-card p-8 rounded-[2rem] space-y-6">
                    <h3 class="font-bold text-sm uppercase tracking-widest text-lime-400">Datos de Empresa / Facturación</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-1">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Nombre Comercial / Empresa</label>
                            <input type="text" name="company_name" class="w-full p-4 rounded-xl outline-none" value="<?php echo htmlspecialchars($admin['company_name'] ?? ''); ?>" placeholder="Ej: Mi Evento S.L.">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">CIF / NIF / VAT</label>
                            <input type="text" name="company_vat" class="w-full p-4 rounded-xl outline-none" value="<?php echo htmlspecialchars($admin['company_vat'] ?? ''); ?>" placeholder="Ej: B12345678">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Teléfono de contacto</label>
                            <input type="text" name="company_phone" class="w-full p-4 rounded-xl outline-none" value="<?php echo htmlspecialchars($admin['company_phone'] ?? ''); ?>" placeholder="+34 ...">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1">Dirección Fiscal</label>
                            <textarea name="company_address" class="w-full p-4 rounded-xl outline-none" rows="1"><?php echo htmlspecialchars($admin['company_address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-10 py-4 bg-lime-400 text-black rounded-2xl font-black text-sm hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all">
                        GUARDAR CAMBIOS
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Preview de imagen
        document.getElementById('photoInput').onchange = function (evt) {
            const [file] = this.files;
            if (file) {
                const img = document.querySelector('.group img') || document.querySelector('.group .w-full');
                if (img.tagName === 'IMG') {
                    img.src = URL.createObjectURL(file);
                } else {
                    const newImg = document.createElement('img');
                    newImg.src = URL.createObjectURL(file);
                    newImg.className = 'w-full h-full object-cover';
                    img.parentNode.replaceChild(newImg, img);
                }
            }
        }
    </script>
</body>
</html>
