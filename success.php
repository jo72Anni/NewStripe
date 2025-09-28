<?php
require_once 'vendor/autoload.php';

// Configurazione
header('Cache-Control: no-cache, no-store, must-revalidate');

$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASSWORD');
$db_port = getenv('DB_PORT');

// Connessione database
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

$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    die("ID sessione non specificato");
}

// Funzioni database
function getTransaction($pdo, $session_id) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE session_id = ?");
    $stmt->execute([$session_id]);
    return $stmt->fetch();
}

function getWebhookEvents($pdo, $session_id) {
    $stmt = $pdo->prepare("
        SELECT event_type, status, created_at 
        FROM webhook_logs 
        WHERE session_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$session_id]);
    return $stmt->fetchAll();
}

// Recupera dati
$transaction = getTransaction($pdo, $session_id);
$webhook_events = getWebhookEvents($pdo, $session_id);

// Se transazione non trovata, prova Stripe
if (!$transaction) {
    try {
        $stripe_secret_key = getenv('STRIPE_SECRET_KEY');
        \Stripe\Stripe::setApiKey($stripe_secret_key);
        
        $session = \Stripe\Checkout\Session::retrieve($session_id);
        
        $transaction = [
            'session_id' => $session->id,
            'product_name' => 'Prodotto Stripe',
            'amount' => $session->amount_total,
            'currency' => strtoupper($session->currency),
            'status' => $session->payment_status === 'paid' ? 'completed' : 'pending',
            'stripe_payment_intent' => $session->payment_intent,
            'customer_email' => $session->customer_details->email ?? 'N/A',
            'created_at' => date('Y-m-d H:i:s')
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin: 20px auto;
        }
        .success-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 14px;
        }
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .info-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin: 25px 0;
            border-left: 5px solid #007bff;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }
        .info-item {
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        .webhook-event {
            padding: 12px;
            margin: 8px 0;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #6c757d;
        }
        .debug-panel {
            background: #e9ecef;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-header">
            <div class="success-icon">✅</div>
            <h1 style="color: #28a745; margin: 0;">Pagamento Riuscito!</h1>
            <p style="color: #666; font-size: 18px;">Grazie per il tuo acquisto</p>
        </div>

        <?php if ($transaction): ?>
            <!-- Dettagli Ordine -->
            <div class="info-section">
                <h3>📦 Dettagli Ordine</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Prodotto:</strong><br>
                        <?php echo htmlspecialchars($transaction['product_name']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Importo:</strong><br>
                        €<?php echo number_format($transaction['amount'] / 100, 2); ?>
                    </div>
                    <div class="info-item">
                        <strong>Stato:</strong><br>
                        <span class="status-badge status-<?php echo $transaction['status']; ?>">
                            <?php echo ucfirst($transaction['status']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <strong>Data:</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- Informazioni Cliente -->
            <div class="info-section">
                <h3>👤 Informazioni Cliente</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Email:</strong><br>
                        <?php echo htmlspecialchars($transaction['customer_email'] ?? 'N/A'); ?>
                    </div>
                    <div class="info-item">
                        <strong>ID Transazione:</strong><br>
                        <code><?php echo htmlspecialchars(substr($transaction['session_id'], 0, 12) . '...'); ?></code>
                    </div>
                </div>
            </div>

            <!-- Webhook Events -->
            <?php if (!empty($webhook_events)): ?>
            <div class="info-section">
                <h3>📋 Cronologia Eventi</h3>
                <?php foreach ($webhook_events as $event): ?>
                    <div class="webhook-event">
                        <strong><?php echo htmlspecialchars($event['event_type']); ?></strong>
                        - <em><?php echo htmlspecialchars($event['status']); ?></em>
                        <br>
                        <small><?php echo date('H:i:s', strtotime($event['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Azioni -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="/" class="btn">🏠 Torna alla Home</a>
                
                <?php if ($transaction['status'] === 'pending'): ?>
                <button onclick="location.reload()" class="btn" style="background: #ffc107; color: #000;">
                    🔄 Aggiorna Stato
                </button>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Transazione non trovata -->
            <div style="text-align: center; color: #dc3545;">
                <div style="font-size: 60px;">❌</div>
                <h2>Transazione Non Trovata</h2>
                <p>ID: <code><?php echo htmlspecialchars($session_id); ?></code></p>
                <?php if (isset($stripe_error)): ?>
                    <div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <?php echo htmlspecialchars($stripe_error); ?>
                    </div>
                <?php endif; ?>
                <a href="/" class="btn">Torna alla Home</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if ($transaction && $transaction['status'] === 'pending'): ?>
        setTimeout(() => {
            location.reload();
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
