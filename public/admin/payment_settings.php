<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/StripeConnectRepository.php';

checkAdminSession();

$repo = new StripeConnectRepository();
$adminId = (int) $_SESSION['admin_id'];
$state = $repo->getStripeState($adminId) ?: [];
$status = (string) ($_GET['stripe'] ?? ($state['stripe_onboarding_status'] ?? 'not_started'));
$chargesEnabled = !empty($state['stripe_charges_enabled']);
$payoutsEnabled = !empty($state['stripe_payouts_enabled']);

$labels = [
    'not_started' => ['Sin conectar', 'Conecta tu cuenta Stripe para poder cobrar entradas de pago.'],
    'pending' => ['Onboarding pendiente', 'Completa la información solicitada por Stripe.'],
    'restricted' => ['Revisión pendiente', 'Stripe necesita completar o revisar algunos requisitos.'],
    'active' => ['Conectado y listo', 'Tu cuenta puede recibir pagos y realizar payouts.'],
    'error' => ['No se pudo conectar', 'Revisa la configuración de Stripe y vuelve a intentarlo.'],
];
$stateLabel = $labels[$status] ?? $labels['pending'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagos - <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{background:#0A0E14;color:#fff;font-family:Arial,sans-serif}.card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08)}</style>
</head>
<body class="min-h-screen">
<?php include '../../includes/templates/sidebar.php'; ?>
<main class="lg:ml-64 p-6 lg:p-10">
    <div class="max-w-3xl mx-auto">
        <header class="mb-10">
            <p class="text-xs uppercase tracking-[.25em] text-gray-500">Cuenta de organizador</p>
            <h1 class="text-4xl font-black mt-2">Cobros con <span class="text-lime-300">Stripe Connect</span></h1>
            <p class="text-gray-400 mt-3">No guardamos claves secretas de Stripe de los organizadores. Cada organizador conecta su propia cuenta mediante el onboarding seguro de Stripe.</p>
        </header>

        <section class="card rounded-3xl p-8">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <div class="text-sm text-gray-500 uppercase tracking-widest">Estado</div>
                    <h2 class="text-2xl font-bold mt-2"><?php echo htmlspecialchars($stateLabel[0], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="text-gray-400 mt-2"><?php echo htmlspecialchars($stateLabel[1], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="w-3 h-3 rounded-full <?php echo $status === 'active' ? 'bg-lime-300' : 'bg-yellow-400'; ?> mt-2"></div>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-8">
                <div class="bg-black/20 rounded-2xl p-5">
                    <div class="text-xs text-gray-500 uppercase">Cobros</div>
                    <div class="font-bold mt-1"><?php echo $chargesEnabled ? 'Habilitados' : 'Pendientes'; ?></div>
                </div>
                <div class="bg-black/20 rounded-2xl p-5">
                    <div class="text-xs text-gray-500 uppercase">Payouts</div>
                    <div class="font-bold mt-1"><?php echo $payoutsEnabled ? 'Habilitados' : 'Pendientes'; ?></div>
                </div>
            </div>

            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <form method="POST" action="stripe-connect.php">
                    <?php echo csrf_field('stripe_connect'); ?>
                    <button type="submit" class="px-6 py-3 rounded-2xl bg-lime-300 text-black font-black hover:brightness-105 transition">
                        <?php echo $status === 'active' ? 'Revisar cuenta Stripe' : 'Conectar con Stripe'; ?>
                    </button>
                </form>
                <?php if (!empty($state['stripe_account_id']) && $status === 'active'): ?>
                    <a href="stripe-connect.php?action=dashboard" class="px-6 py-3 rounded-2xl border border-white/10 font-bold hover:bg-white/5 transition">Abrir Stripe Express</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="card rounded-3xl p-8 mt-6">
            <h3 class="font-bold text-lg">Cómo funciona</h3>
            <ol class="mt-5 space-y-4 text-gray-400 text-sm">
                <li><span class="text-lime-300 font-bold">1.</span> Conectas tu cuenta Stripe desde aquí.</li>
                <li><span class="text-lime-300 font-bold">2.</span> Stripe realiza el onboarding y la verificación de identidad.</li>
                <li><span class="text-lime-300 font-bold">3.</span> Cuando Stripe habilita cobros y payouts, podrás publicar eventos de pago.</li>
                <li><span class="text-lime-300 font-bold">4.</span> Los pagos se procesarán mediante la cuenta conectada, no mediante una clave secreta guardada por el organizador.</li>
            </ol>
        </section>
    </div>
</main>
</body>
</html>
