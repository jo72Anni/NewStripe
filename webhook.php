<?php
require_once 'vendor/autoload.php';

// Log per debugging
error_log("Webhook chiamato: " . date('Y-m-d H:i:s'));

// Configurazione
$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
$webhook_secret = getenv('STRIPE_WEBHOOK_SECRET');
\Stripe\Stripe::setApiKey($stripe_secret_key);

// Configurazione Database
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
    error_log("Database error: " . $e->getMessage());
    exit('Database connection failed');
}

// Crea tabella per i log webhook (se non esiste)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS webhook_logs (
        id SERIAL PRIMARY KEY,
        event_id VARCHAR(255) UNIQUE NOT NULL,
        event_type VARCHAR(255) NOT NULL,
        session_id VARCHAR(255),
        status VARCHAR(50) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Funzione per loggare eventi webhook
function logWebhookEvent($pdo, $event_id, $event_type, $session_id, $status, $details = '') {
    $sql = "INSERT INTO webhook_logs (event_id, event_type, session_id, status, details) 
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT (event_id) DO UPDATE SET 
            status = EXCLUDED.status, details = EXCLUDED.details";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$event_id, $event_type, $session_id, $status, $details]);
}

// Funzione per aggiornare le transazioni
function updateTransaction($pdo, $session_id, $status, $customer_email = null, $payment_intent = null) {
    $sql = "UPDATE transactions 
            SET status = ?, customer_email = ?, stripe_payment_intent = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE session_id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$status, $customer_email, $payment_intent, $session_id]);
}

// Gestione Webhook
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

error_log("Payload ricevuto: " . strlen($payload) . " bytes");
error_log("Signature header: " . $sig_header);

try {
    if (empty($webhook_secret)) {
        throw new Exception('Webhook secret not configured');
    }

    // Verifica la firma del webhook
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $webhook_secret
    );

    error_log("Evento verificato: " . $event->type);

} catch (\UnexpectedValueException $e) {
    // Payload non valido
    http_response_code(400);
    error_log('Webhook error: Invalid payload - ' . $e->getMessage());
    logWebhookEvent($pdo, 'invalid_payload', 'error', null, 'failed', $e->getMessage());
    exit('Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Firma non valida
    http_response_code(400);
    error_log('Webhook error: Invalid signature - ' . $e->getMessage());
    logWebhookEvent($pdo, 'invalid_signature', 'error', null, 'failed', $e->getMessage());
    exit('Invalid signature');
} catch (Exception $e) {
    http_response_code(400);
    error_log('Webhook error: ' . $e->getMessage());
    exit('Webhook error');
}

// Elabora l'evento
http_response_code(200);

try {
    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            error_log("Processing checkout.session.completed for session: " . $session->id);
            
            $customer_email = null;
            if ($session->customer) {
                try {
                    $customer = \Stripe\Customer::retrieve($session->customer);
                    $customer_email = $customer->email;
                } catch (Exception $e) {
                    error_log("Error retrieving customer: " . $e->getMessage());
                }
            }
            
            // Aggiorna il database
            $updated = updateTransaction(
                $pdo, 
                $session->id, 
                'completed', 
                $customer_email,
                $session->payment_intent
            );
            
            if ($updated) {
                logWebhookEvent($pdo, $event->id, $event->type, $session->id, 'processed', 
                    "Customer: " . ($customer_email ?: 'unknown'));
                error_log("✅ Payment completed for session: " . $session->id);
            } else {
                logWebhookEvent($pdo, $event->id, $event->type, $session->id, 'update_failed',
                    "Transaction not found in database");
                error_log("❌ Transaction not found for session: " . $session->id);
            }
            break;

        case 'checkout.session.expired':
            $session = $event->data->object;
            error_log("Processing checkout.session.expired for session: " . $session->id);
            
            $updated = updateTransaction($pdo, $session->id, 'expired');
            
            if ($updated) {
                logWebhookEvent($pdo, $event->id, $event->type, $session->id, 'processed');
                error_log("✅ Session expired: " . $session->id);
            } else {
                logWebhookEvent($pdo, $event->id, $event->type, $session->id, 'not_found');
                error_log("❌ Session not found: " . $session->id);
            }
            break;

        case 'payment_intent.succeeded':
            $paymentIntent = $event->data->object;
            error_log("Payment intent succeeded: " . $paymentIntent->id);
            logWebhookEvent($pdo, $event->id, $event->type, null, 'processed', 
                "Payment Intent: " . $paymentIntent->id);
            break;

        case 'payment_intent.payment_failed':
            $paymentIntent = $event->data->object;
            error_log("Payment intent failed: " . $paymentIntent->id);
            
            // Trova la session associata al payment intent
            try {
                $sessions = \Stripe\Checkout\Session::all([
                    'payment_intent' => $paymentIntent->id,
                    'limit' => 1
                ]);
                
                if (!empty($sessions->data)) {
                    $session = $sessions->data[0];
                    $updated = updateTransaction($pdo, $session->id, 'failed', null, $paymentIntent->id);
                    
                    if ($updated) {
                        logWebhookEvent($pdo, $event->id, $event->type, $session->id, 'processed',
                            "Payment failed: " . $paymentIntent->last_payment_error->message ?? 'Unknown error');
                        error_log("✅ Payment failed updated for session: " . $session->id);
                    }
                }
                
            } catch (Exception $e) {
                error_log("Error finding session for failed payment: " . $e->getMessage());
            }
            break;

        default:
            logWebhookEvent($pdo, $event->id, $event->type, null, 'unhandled', 
                "Unhandled event type");
            error_log('⚠️ Received unhandled event type: ' . $event->type);
    }

    echo '✅ Webhook processed successfully: ' . $event->type;

} catch (Exception $e) {
    error_log("Error processing webhook: " . $e->getMessage());
    logWebhookEvent($pdo, $event->id ?? 'unknown', $event->type ?? 'unknown', null, 'error', 
        "Processing error: " . $e->getMessage());
    http_response_code(500);
    echo '❌ Error processing webhook';
}
?>
