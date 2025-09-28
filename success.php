<?php
require_once 'vendor/autoload.php';

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
    die("Errore connessione database: " . $e->getMessage());
}

// Funzione per verificare lo stato del sistema
function getSystemStatus($pdo) {
    $status = [];
    
    // Verifica environment variables
    $status['stripe_key'] = !empty(getenv('STRIPE_SECRET_KEY'));
    $status['webhook_secret'] = !empty(getenv('STRIPE_WEBHOOK_SECRET'));
    $status['database'] = !empty(getenv('DB_HOST'));
    
    // Verifica connessione database
    try {
        $version = $pdo->query("SELECT version()")->fetchColumn();
        $status['db_connection'] = true;
        $status['db_version'] = $version;
        
        // Statistiche database
        $transactions_count = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
        $status['transactions_count'] = $transactions_count;
        
        $webhook_events_count = $pdo->query("SELECT COUNT(*) FROM webhook_logs")->fetchColumn();
        $status['webhook_events_count'] = $webhook_events_count;
        
    } catch (Exception $e) {
        $status['db_connection'] = false;
        $status['db_error'] = $e->getMessage();
    }
    
    return $status;
}

$session_id = $_GET['session_id'] ?? '';

if (!$session_id) {
    die("ID sessione non specificato");
}

// Recupera i dettagli della transazione dal database
function getTransactionDetails($pdo, $session_id) {
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            COUNT(wl.id) as webhook_events
        FROM transactions t
        LEFT JOIN webhook_logs wl ON t.session_id = wl.session_id
        WHERE t.session_id = ?
        GROUP BY t.id
    ");
    $stmt->execute([$session_id]);
    return $stmt->fetch();
}

// Recupera la cronologia webhook per questa sessione
function getWebhookHistory($pdo, $session_id) {
    $stmt = $pdo->prepare("
        SELECT event_type, status, details, created_at 
        FROM webhook_logs 
        WHERE session_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$session_id]);
    return $stmt->fetchAll();
}

$transaction = getTransactionDetails($pdo, $session_id);
$webhook_history = getWebhookHistory($pdo, $session_id);
$system_status = getSystemStatus($pdo);

if (!$transaction) {
    // Se non trova la transazione, prova a cercare direttamente su Stripe
    try {
        $stripe_secret_key = getenv('STRIPE_SECRET_KEY');
        \Stripe\Stripe::setApiKey($stripe_secret_key);
        
        $session = \Stripe\Checkout\Session::retrieve($session_id);
        $payment_intent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
        
        // Crea un array con i dati di Stripe
        $transaction = [
            'session_id' => $session->id,
            'product_name' => 'Prodotto Stripe',
            'amount' => $payment_intent->amount,
            'currency' => strtoupper($payment_intent->currency),
            'status' => $payment_intent->status === 'succeeded' ? 'completed' : $payment_intent->status,
            'stripe_payment_intent' => $session->payment_intent,
            'customer_email' => $session->customer_details->email ?? 'N/A',
            'created_at' => date('Y-m-d H:i:s', $session->created),
            'webhook_events' => 0
        ];
        
    } catch (Exception $e) {
        $stripe_error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Pagamento Riuscito</title>
    <style>
        body { 
            font-family: 'Arial', sans-serif; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .success-icon { 
            color: #28a745; 
            font-size: 64px; 
            text-align: center;
            margin-bottom: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin: 10px 0;
        }
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .webhook-history {
            margin-top: 30px;
        }
        .webhook-event {
            padding: 10px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #6c757d;
        }
        .webhook-success { border-left-color: #28a745; }
        .webhook-error { border-left-color: #dc3545; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .system-status {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }
        .status-item { margin: 5px 0; }
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .debug-panel {
            background: #f8f9fa;
            border: 1px dashed #6c757d;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1 style="text-align: center; color: #28a745;">Pagamento Riuscito!</h1>
        
        <!-- Pannello Diagnostica Sistema -->
        <details class="debug-panel">
            <summary style="cursor: pointer; font-weight: bold;">🔧 Diagnostica Sistema</summary>
            <div class="system-status">
                <h4>Stato Configurazione</h4>
                
                <div class="status-item">
                    Stripe Secret Key: 
                    <span class="<?php echo $system_status['stripe_key'] ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $system_status['stripe_key'] ? '✅ Configurata' : '❌ Mancante'; ?>
                    </span>
                </div>
                
                <div class="status-item">
                    Webhook Secret: 
                    <span class="<?php echo $system_status['webhook_secret'] ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $system_status['webhook_secret'] ? '✅ Configurata' : '❌ Mancante'; ?>
                    </span>
                </div>
                
                <div class="status-item">
                    Connessione Database: 
                    <span class="<?php echo $system_status['db_connection'] ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $system_status['db_connection'] ? '✅ Connesso' : '❌ Errore'; ?>
                    </span>
                </div>
                
                <?php if ($system_status['db_connection']): ?>
                <div class="status-item">
                    Transazioni totali: <strong><?php echo $system_status['transactions_count']; ?></strong>
                </div>
                <div class="status-item">
                    Eventi webhook: <strong><?php echo $system_status['webhook_events_count']; ?></strong>
                </div>
                <div class="status-item">
                    <small>Database: <?php echo htmlspecialchars($system_status['db_version']); ?></small>
                </div>
                <?php endif; ?>
                
                <div style="margin-top: 10px;">
                    <a href="https://dashboard.stripe.com/test/webhooks" target="_blank" style="font-size: 12px;">
                        🔗 Stripe Webhook Dashboard
                    </a>
                </div>
            </div>
        </details>

        <?php if ($transaction): ?>
            <!-- Dettagli Transazione -->
            <div class="info-card">
                <h3>📦 Dettagli Ordine</h3>
                <div class="info-grid">
                    <div>
                        <strong>Prodotto:</strong><br>
                        <?php echo htmlspecialchars($transaction['product_name']); ?>
                    </div>
                    <div>
                        <strong>Importo:</strong><br>
                        €<?php echo number_format($transaction['amount'] / 100, 2); ?>
                        (<?php echo strtoupper($transaction['currency']); ?>)
                    </div>
                    <div>
                        <strong>Stato:</strong><br>
                        <span class="status-badge status-<?php echo $transaction['status']; ?>">
                            <?php 
                            $status_labels = [
                                'completed' => '✅ Completato',
                                'pending' => '⏳ In attesa',
                                'failed' => '❌ Fallito',
                                'expired' => '⏰ Scaduto'
                            ];
                            echo $status_labels[$transaction['status']] ?? ucfirst($transaction['status']);
                            ?>
                        </span>
                    </div>
                    <div>
                        <strong>Data:</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- Informazioni Cliente -->
            <div class="info-card">
                <h3>👤 Informazioni Cliente</h3>
                <div class="info-grid">
                    <div>
                        <strong>Email:</strong><br>
                        <?php echo htmlspecialchars($transaction['customer_email'] ?? 'N/A'); ?>
                    </div>
                    <div>
                        <strong>ID Transazione:</strong><br>
                        <code><?php echo htmlspecialchars(substr($transaction['session_id'], 0, 15) . '...'); ?></code>
                    </div>
                    <div>
                        <strong>Payment Intent:</strong><br>
                        <code><?php echo htmlspecialchars(substr($transaction['stripe_payment_intent'] ?? 'N/A', 0, 15) . '...'); ?></code>
                    </div>
                    <div>
                        <strong>Eventi Webhook:</strong><br>
                        <?php echo $transaction['webhook_events'] ?? 0; ?>
                    </div>
                </div>
            </div>

            <!-- Cronologia Webhook -->
            <?php if (!empty($webhook_history)): ?>
            <div class="webhook-history">
                <h3>📋 Cronologia Eventi Webhook</h3>
                <?php foreach ($webhook_history as $event): ?>
                    <div class="webhook-event <?php echo $event['status'] === 'processed' ? 'webhook-success' : 'webhook-error'; ?>">
                        <strong><?php echo htmlspecialchars($event['event_type']); ?></strong>
                        - <?php echo htmlspecialchars($event['status']); ?>
                        <br>
                        <small>
                            <?php echo date('H:i:s', strtotime($event['created_at'])); ?>
                            <?php if ($event['details']): ?>
                                - <?php echo htmlspecialchars($event['details']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Azioni -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="/" class="btn">🏠 Torna alla Home</a>
                
                <?php if ($transaction['status'] === 'pending'): ?>
                <button onclick="checkStatus()" class="btn" style="background: #ffc107; color: #000;">
                    🔄 Aggiorna Stato
                </button>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Transazione non trovata -->
            <div style="text-align: center; color: #dc3545;">
                <div style="font-size: 48px;">❌</div>
                <h2>Transazione Non Trovata</h2>
                <p>La transazione con ID <code><?php echo htmlspecialchars($session_id); ?></code> non è stata trovata nel database.</p>
                
                <?php if (isset($stripe_error)): ?>
                    <div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <strong>Errore Stripe:</strong> <?php echo htmlspecialchars($stripe_error); ?>
                    </div>
                <?php endif; ?>
                
                <a href="/" class="btn">Torna alla Home</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function checkStatus() {
            // Ricarica la pagina per verificare lo stato aggiornato
            window.location.reload();
        }

        // Auto-aggiornamento se lo stato è pending
        <?php if ($transaction && $transaction['status'] === 'pending'): ?>
        setTimeout(() => {
            window.location.reload();
        }, 5000); // Ricarica ogni 5 secondi
        <?php endif; ?>
    </script>
</body>
</html>
