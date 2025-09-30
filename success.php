<?php
require_once 'vendor/autoload.php';

// Connessione al database
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
    die("Errore connessione DB: " . $e->getMessage());
}

// Recupera session_id da URL
$session_id = $_GET['session_id'] ?? null;

if (!$session_id) {
    die("Session ID mancante.");
}

// Recupera i dati della transazione
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE session_id = :sid LIMIT 1");
$stmt->execute([':sid' => $session_id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    die("Nessuna transazione trovata.");
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Pagamento completato</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            padding: 40px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            color: #28a745;
        }
        .info {
            margin: 20px 0;
            font-size: 16px;
        }
        .info strong {
            display: inline-block;
            width: 150px;
            text-align: right;
            margin-right: 10px;
            color: #333;
        }
        .back-btn {
            margin-top: 20px;
            display: inline-block;
            background: #5469d4;
            color: white;
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s;
        }
        .back-btn:hover {
            background: #3a4fc4;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Pagamento completato!</h1>
        <p>Grazie per il tuo acquisto.</p>

        <div class="info">
            <p><strong>Ordine #:</strong> <?php echo htmlspecialchars($transaction['id']); ?></p>
            <p><strong>Prodotto:</strong> <?php echo htmlspecialchars($transaction['product_name']); ?></p>
            <p><strong>Importo:</strong> €<?php echo number_format($transaction['amount'] / 100, 2); ?></p>
            <p><strong>Valuta:</strong> <?php echo htmlspecialchars(strtoupper($transaction['currency'])); ?></p>
            <p><strong>Email Cliente:</strong> <?php echo htmlspecialchars($transaction['customer_email'] ?? 'N/D'); ?></p>
            <p><strong>Stato:</strong> <?php echo htmlspecialchars($transaction['status']); ?></p>
            <p><strong>Payment Intent:</strong> <?php echo htmlspecialchars($transaction['stripe_payment_intent'] ?? 'N/D'); ?></p>
        </div>

        <a href="index.php" class="back-btn">⬅ Torna al negozio</a>
    </div>
</body>
</html>

