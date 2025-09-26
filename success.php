<?php
require_once 'vendor/autoload.php';

$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
\Stripe\Stripe::setApiKey($stripe_secret_key);

$session_id = $_GET['session_id'] ?? '';

if ($session_id) {
    try {
        $session = \Stripe\Checkout\Session::retrieve($session_id);
        $payment_status = $session->payment_status;
        $customer_email = $session->customer_details->email ?? 'N/A';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pagamento Riuscito</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
        .success { color: #28a745; font-size: 24px; }
    </style>
</head>
<body>
    <div class="success">✅</div>
    <h1>Pagamento Completato!</h1>
    
    <?php if ($session_id): ?>
        <p><strong>ID Transazione:</strong> <?php echo htmlspecialchars($session_id); ?></p>
        <p><strong>Stato:</strong> <?php echo $payment_status ?? 'Completato'; ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($customer_email); ?></p>
    <?php endif; ?>
    
    <a href="/" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #5469d4; color: white; text-decoration: none; border-radius: 5px;">Torna al Negozio</a>
</body>
</html>
