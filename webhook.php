<?php
require_once 'vendor/autoload.php';

// Prendi la chiave segreta e la chiave webhook (dal tuo Stripe Dashboard)
$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET');

\Stripe\Stripe::setApiKey($stripe_secret_key);

// Recupera il body e la firma inviata da Stripe
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    // Payload non valido
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    // Firma non valida
    http_response_code(400);
    exit();
}

// Connessione al DB
try {
    $pdo = new PDO(
        "pgsql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME'),
        getenv('DB_USER'),
        getenv('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("Errore connessione DB: " . $e->getMessage());
    http_response_code(500);
    exit();
}

// Gestione eventi Stripe
switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object;

        // Aggiorna la transazione
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'paid',
                customer_email = :email,
                stripe_payment_intent = :pi,
                updated_at = NOW()
            WHERE session_id = :sid
        ");
        $stmt->execute([
            ':email' => $session->customer_details->email ?? null,
            ':pi'    => $session->payment_intent ?? null,
            ':sid'   => $session->id
        ]);

        break;

    // Puoi aggiungere altri eventi se servono
    case 'payment_intent.payment_failed':
        $intent = $event->data->object;
        error_log("Pagamento fallito: " . $intent->id);
        break;
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
