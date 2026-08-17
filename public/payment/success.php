<?php
require_once '../../includes/config/config.php';
require_once '../../includes/classes/PaymentOrderRepository.php';

$token = trim((string)($_GET['order'] ?? ''));
$repo = new PaymentOrderRepository();
$order = $token !== '' ? $repo->getOrderByToken($token) : null;

if (!$order) {
    http_response_code(404);
    exit('Pedido no encontrado.');
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pago recibido</title></head>
<body style="font-family:system-ui;max-width:680px;margin:80px auto;padding:24px;text-align:center">
<h1>Pago recibido</h1>
<?php if (($order['status'] ?? '') === 'fulfilled'): ?>
<p>Tu pago se ha confirmado y tus entradas han sido emitidas.</p>
<?php else: ?>
<p>El pago ha sido recibido. Estamos terminando de emitir tus entradas.</p>
<p>Recibirás la confirmación cuando finalice el proceso.</p>
<?php endif; ?>
</body>
</html>
