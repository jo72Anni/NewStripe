<?php
require_once 'vendor/autoload.php';

// Config Stripe
$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
$stripe_webhook_secret = getenv('STRIPE_WEBHOOK_SECRET');
\Stripe\Stripe::setApiKey($stripe_secret_key);

// Config DB
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASSWORD');
$db_port = getenv('DB_PORT');

try {
    $pdo = new PDO(
        "pgsql:host=$db_host;port=$db_port;dbname=$db_name",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit("DB Connection failed: " . $e->getMessage());
}

// Leggi payload e firma
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $stripe_webhook_secret
    );
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Webhook signature verification failed.');
} catch (Exception $e) {
    http_response_code(400);
    exit('Invalid payload.');
}

// Gestisci l'evento
if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;

    $session_id = $session->id;
    $customer_email = $session->customer_details->email ?? null;
    $payment_intent = $session->payment_intent ?? null;

    // Aggiorna la transazione nel DB
    $stmt = $pdo->prepare("
        UPDATE transactions
        SET status = 'completed',
            customer_email = ?,
            stripe_payment_intent = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE session_id = ?
    ");
    $stmt->execute([
        $customer_email,
        $payment_intent,
        $session_id
    ]);
}

// Risposta OK
http_response_code(200);
echo json_encode(['status' => 'ok']);
