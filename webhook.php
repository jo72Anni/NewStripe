<?php
require_once 'vendor/autoload.php';

// Configurazione
$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
$webhook_secret = getenv('STRIPE_WEBHOOK_SECRET');
\Stripe\Stripe::setApiKey($stripe_secret_key);

// Database
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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error');
}

// Crea tabella webhook logs
$pdo->exec("
    CREATE TABLE IF NOT EXISTS webhook_logs (
        id SERIAL PRIMARY KEY,
        event_id VARCHAR(255) UNIQUE NOT NULL,
        event_type VARCHAR(255) NOT NULL,
        session_id VARCHAR(255),
        status VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Gestione webhook
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
} catch (Exception $e) {
    http_response_code(400);
    exit();
}

// Elabora evento
http_response_code(200);

function logWebhook($pdo, $event_id, $event_type, $session_id, $status) {
    $stmt = $pdo->prepare("
        INSERT INTO webhook_logs (event_id, event_type, session_id, status) 
        VALUES (?, ?, ?, ?)
        ON CONFLICT (event_id) DO UPDATE SET status = EXCLUDED.status
    ");
    $stmt->execute([$event_id, $event_type, $session_id, $status]);
}

function updateTransaction($pdo, $session_id, $status, $payment_intent = null) {
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET status = ?, stripe_payment_intent = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE session_id = ?
    ");
    return $stmt->execute([$status, $payment_intent, $session_id]);
}

switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object;
        
        $customer_email = null;
        if ($session->customer) {
            try {
                $customer = \Stripe\Customer::retrieve($session->customer);
                $customer_email = $customer->email;
            } catch (Exception $e) {
                // Ignora errore customer
            }
        }
        
        // Aggiorna transazione
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'completed', customer_email = ?, stripe_payment_intent = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE session_id = ?
        ");
        $stmt->execute([$customer_email, $session->payment_intent, $session->id]);
        
        logWebhook($pdo, $event->id, $event->type, $session->id, 'processed');
        break;

    case 'checkout.session.expired':
        $session = $event->data->object;
        updateTransaction($pdo, $session->id, 'expired');
        logWebhook($pdo, $event->id, $event->type, $session->id, 'processed');
        break;

    case 'payment_intent.payment_failed':
        $payment_intent = $event->data->object;
        logWebhook($pdo, $event->id, $event->type, null, 'processed');
        break;

    default:
        logWebhook($pdo, $event->id, $event->type, null, 'unhandled');
}

echo 'Webhook processed';
?>
