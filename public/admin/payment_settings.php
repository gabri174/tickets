<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';

checkAdminSession();

$db = new Database();
$adminId = $_SESSION['admin_id'];
$message = '';
$error = '';

// Obtener configuración actual del admin
$stmt = $db->getConnection()->prepare("SELECT preferred_payment_method, payment_config FROM admins WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

$paymentMethod = $admin['preferred_payment_method'] ?? 'finassets';
$paymentConfig = json_decode($admin['payment_config'] ?? '{}', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad (CSRF). Por favor, intenta de nuevo.';
    } else {
        $paymentMethod = cleanInput($_POST['payment_method']);
        $config = [];
    
    switch ($paymentMethod) {
        case 'finassets':
            $config['url'] = cleanInput($_POST['finassets_url']);
            $config['key'] = cleanInput($_POST['finassets_key']);
            break;
        case 'stripe':
            $config['public_key'] = cleanInput($_POST['stripe_public_key']);
            $config['secret_key'] = cleanInput($_POST['stripe_secret_key']);
            break;
        case 'paypal':
            $config['email'] = cleanInput($_POST['paypal_email']);
            $config['client_id'] = cleanInput($_POST['paypal_client_id']);
            break;
        case 'redsys':
            $config['merchant_code'] = cleanInput($_POST['redsys_merchant_code']);
            $config['terminal'] = cleanInput($_POST['redsys_terminal']);
            $config['secret_key'] = cleanInput($_POST['redsys_secret_key']);
            break;
        case 'checkout':
            $config['public_key'] = cleanInput($_POST['checkout_public_key']);
            $config['secret_key'] = cleanInput($_POST['checkout_secret_key']);
            break;
    }
    
    $configJson = json_encode($config);
    
        try {
            $stmt = $db->getConnection()->prepare("UPDATE admins SET preferred_payment_method = ?, payment_config = ? WHERE id = ?");
            if ($stmt->execute([$paymentMethod, $configJson, $adminId])) {
                $message = 'Configuración de pago actualizada correctamente.';
                $paymentConfig = $config;
            } else {
                $error = 'Error al actualizar la configuración.';
            }
        } catch (Exception $e) {
            $error = 'Error en la base de datos: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Pago - Admin <?php echo SITE_NAME; ?></title>
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
        input, select { background: rgba(255, 255, 255, 0.05) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; color: white !important; }
        input:focus, select:focus { border-color: rgba(218, 251, 113, 0.5) !important; box-shadow: 0 0 15px rgba(218, 251, 113, 0.1) !important; }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen overflow-x-hidden">
    <?php include '../../includes/templates/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-4 lg:p-8 relative">
            <div class="max-w-4xl mx-auto">
                <header class="mb-10">
                    <h2 class="text-3xl font-black tracking-tighter">Configuración de <span class="text-gradient">Pagos</span></h2>
                    <p class="text-gray-500 text-sm">Elige cómo quieres recibir los pagos de tus eventos</p>
                </header>

                <?php if ($message): ?>
                    <div class="bg-lime-500/10 border border-lime-500/20 text-lime-400 p-4 rounded-2xl mb-6 flex items-center gap-3">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl mb-6 flex items-center gap-3">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-8">
                    <?php echo csrf_field(); ?>
                    <div class="glass-card p-8 rounded-[2rem]">
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-4">Método Preferido</label>
                        <select name="payment_method" id="payment_method" class="w-full p-4 rounded-2xl outline-none" onchange="toggleConfig(this.value)">
                            <option value="none" <?php echo $paymentMethod === 'none' ? 'selected' : ''; ?>>Ninguno (Simular pago)</option>
                            <option value="finassets" <?php echo $paymentMethod === 'finassets' ? 'selected' : ''; ?>>Finassets.io (Crypto)</option>
                            <option value="stripe" <?php echo $paymentMethod === 'stripe' ? 'selected' : ''; ?>>Stripe</option>
                            <option value="paypal" <?php echo $paymentMethod === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                            <option value="checkout" <?php echo $paymentMethod === 'checkout' ? 'selected' : ''; ?>>Checkout.com</option>
                            <option value="redsys" <?php echo $paymentMethod === 'redsys' ? 'selected' : ''; ?>>Redsys España</option>
                        </select>
                    </div>

                    <!-- Finassets Config -->
                    <div id="config_finassets" class="payment-config glass-card p-8 rounded-[2rem] <?php echo $paymentMethod !== 'finassets' ? 'hidden' : ''; ?>">
                        <h3 class="font-bold mb-6 flex items-center gap-2"><i class="fas fa-coins text-lime-400"></i> Configuración Finassets.io</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs text-gray-500 font-bold uppercase">URL del API</label>
                                <input type="text" name="finassets_url" class="w-full p-4 mt-2 rounded-xl" value="<?php echo htmlspecialchars($paymentConfig['url'] ?? 'https://demopay.finassets.io'); ?>">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 font-bold uppercase">API Key</label>
                                <input type="text" name="finassets_key" class="w-full p-4 mt-2 rounded-xl" value="<?php echo htmlspecialchars($paymentConfig['key'] ?? ''); ?>" placeholder="Introduce tu API Key">
                            </div>
                        </div>
                    </div>

                    <!-- Stripe Config -->
                    <div id="config_stripe" class="payment-config glass-card p-8 rounded-[2rem] <?php echo $paymentMethod !== 'stripe' ? 'hidden' : ''; ?>">
                        <h3 class="font-bold mb-6 flex items-center gap-2"><i class="fab fa-stripe text-blue-400"></i> Configuración Stripe</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs text-gray-500 font-bold uppercase">Clave Pública (Publishable Key)</label>
                                <input type="text" name="stripe_public_key" class="w-full p-4 mt-2 rounded-xl" value="<?php echo htmlspecialchars($paymentConfig['public_key'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 font-bold uppercase">Clave Secreta (Secret Key)</label>
                                <input type="password" name="stripe_secret_key" class="w-full p-4 mt-2 rounded-xl" value="<?php echo htmlspecialchars($paymentConfig['secret_key'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- PayPal Config -->
                    <div id="config_paypal" class="payment-config glass-card p-8 rounded-[2rem] <?php echo $paymentMethod !== 'paypal' ? 'hidden' : ''; ?>">
                        <h3 class="font-bold mb-6 flex items-center gap-2"><i class="fab fa-paypal text-blue-500"></i> Configuración PayPal</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs text-gray-500 font-bold uppercase">Email de Negocio</label>
                                <input type="email" name="paypal_email" class="w-full p-4 mt-2 rounded-xl" value="<?php echo htmlspecialchars($paymentConfig['email'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 font-bold uppercase">Client ID</label>
                                <input type="text" name="paypal_client_id" class="w-full p-4 mt-2 rounded-xl" value="<?php echo htmlspecialchars($paymentConfig['client_id'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Redsys Config -->
                    <div id="config_redsys" class="payment-config glass-card p-8 rounded-[2rem] <?php echo $paymentMethod !== 'redsys' ? 'hidden' : ''; ?>">
                        <h3 class="font-bold mb-6 flex items-center gap-2"><i class="fas fa-credit-card text-red-400"></i> Configuración Redsys España</h3>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs text-gray-500 font-bold uppercase">FUC (Comercio)</label>
                                    <input type="text" name="redsys_merchant_code" class="w-full p-4 mt-2 rounded-xl" value="<?php echo htmlspecialchars($paymentConfig['merchant_code'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 font-bold uppercase">Terminal</label>
                                    <input type="text" name="redsys_terminal" class="w-full p-4 mt-2 rounded-xl" value="<?php echo htmlspecialchars($paymentConfig['terminal'] ?? '1'); ?>">
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 font-bold uppercase">Clave de Comercio (Secret)</label>
                                <input type="password" name="redsys_secret_key" class="w-full p-4 mt-2 rounded-xl" value="<?php echo htmlspecialchars($paymentConfig['secret_key'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-5 bg-lime-400 text-black rounded-[1.5rem] font-black text-xs hover:shadow-[0_0_30px_rgba(218,251,113,0.3)] transition-all">
                        GUARDAR CONFIGURACIÓN DE PAGO
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
        function toggleConfig(method) {
            document.querySelectorAll('.payment-config').forEach(el => el.classList.add('hidden'));
            const target = document.getElementById('config_' + method);
            if (target) target.classList.remove('hidden');
        }
    </script>
</body>
</html>
