<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';
require_once '../../includes/classes/StripeConnectService.php';
require_once '../../includes/classes/StripeConnectRepository.php';

checkAdminSession();

$adminId = (int) $_SESSION['admin_id'];
$db = new Database();
$repo = new StripeConnectRepository();
$action = (string) ($_GET['action'] ?? '');

try {
    $state = $repo->getStripeState($adminId) ?: [];
    $accountId = (string) ($state['stripe_account_id'] ?? '');

    if ($action === 'return') {
        if ($accountId === '') {
            header('Location: payment_settings.php?stripe=not_started');
            exit();
        }

        $stripe = new StripeConnectService();
        $account = $stripe->retrieveAccount($accountId);
        $chargesEnabled = !empty($account['charges_enabled']);
        $payoutsEnabled = !empty($account['payouts_enabled']);
        $detailsSubmitted = !empty($account['details_submitted']);
        $status = ($chargesEnabled && $payoutsEnabled) ? 'active' : ($detailsSubmitted ? 'restricted' : 'pending');
        $repo->saveStripeAccount($adminId, $accountId, $status, $chargesEnabled, $payoutsEnabled);

        header('Location: payment_settings.php?stripe=' . urlencode($status));
        exit();
    }

    if ($action === 'onboarding') {
        if ($accountId === '') {
            header('Location: payment_settings.php?stripe=not_started');
            exit();
        }
        $stripe = new StripeConnectService();
        $link = $stripe->createOnboardingLink($accountId);
        header('Location: ' . $link['url']);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Método no permitido.');
    }

    require_valid_csrf('stripe_connect', 'csrf_token', true);

    $stripe = new StripeConnectService();

    if ($accountId === '') {
        $admin = $db->getAdminById($adminId);
        $email = $admin['email'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El email del organizador no es válido.');
        }

        $account = $stripe->createExpressAccount($email, 'ES');
        $accountId = (string) ($account['id'] ?? '');
        if ($accountId === '') throw new RuntimeException('Stripe no devolvió el ID de la cuenta conectada.');
        $repo->saveStripeAccount($adminId, $accountId, 'pending', false, false);
    }

    $link = $stripe->createOnboardingLink($accountId);
    if (empty($link['url'])) throw new RuntimeException('Stripe no devolvió el enlace de onboarding.');
    header('Location: ' . $link['url']);
    exit();
} catch (Throwable $e) {
    qLog('[ERROR] Stripe Connect onboarding: ' . $e->getMessage());
    header('Location: payment_settings.php?stripe=error');
    exit();
}
