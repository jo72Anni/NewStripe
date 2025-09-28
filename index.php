<?php
require_once 'vendor/autoload.php';

// Configurazione
$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
$stripe_publishable_key = getenv('STRIPE_PUBLISHABLE_KEY');
\Stripe\Stripe::setApiKey($stripe_secret_key);

// Configurazione Database
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASSWORD');
$db_port = getenv('DB_PORT');

// Connessione al database
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

// Crea tabelle se non esistono
$pdo->exec("
    CREATE TABLE IF NOT EXISTS transactions (
        id SERIAL PRIMARY KEY,
        session_id VARCHAR(255) UNIQUE NOT NULL,
        customer_email VARCHAR(255),
        product_name VARCHAR(255) NOT NULL,
        amount INTEGER NOT NULL,
        currency VARCHAR(10) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        stripe_payment_intent VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$base_url = rtrim(getenv('BASE_URL'), '/');

$product = [
    'name' => 'Prodotto di Test',
    'price' => 2000,
    'currency' => 'eur'
];

// Gestione checkout
if ($_POST['checkout'] ?? false) {
    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $product['currency'],
                    'product_data' => ['name' => $product['name']],
                    'unit_amount' => $product['price'],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $base_url . '/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $base_url . '/cancel.php',
        ]);

        // Salva la transazione nel database
        $stmt = $pdo->prepare("
            INSERT INTO transactions (session_id, product_name, amount, currency, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $session->id,
            $product['name'],
            $product['price'],
            $product['currency']
        ]);

        header('Location: ' . $session->url);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Carrello Stripe</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 600px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #f8f9fa;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .product-card {
            border: 2px solid #e9ecef;
            padding: 25px;
            margin: 20px 0;
            border-radius: 10px;
            text-align: center;
        }
        .price {
            color: #28a745;
            font-size: 28px;
            font-weight: bold;
            margin: 15px 0;
        }
        .btn {
            background: #5469d4;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #3a4fc4;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .system-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="text-align: center; color: #333;">🛒 Carrello Stripe</h1>
        
        <?php if (isset($error)): ?>
            <div class="error">
                <strong>Errore:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="product-card">
            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
            <div class="price">€<?php echo number_format($product['price'] / 100, 2); ?></div>
            
            <form method="POST">
                <input type="hidden" name="checkout" value="1">
                <button type="submit" class="btn">Acquista Ora con Stripe</button>
            </form>
        </div>

        <details class="system-info">
            <summary style="cursor: pointer; font-weight: bold;">🔍 Info Sistema</summary>
            <div style="margin-top: 10px;">
                <p><strong>Base URL:</strong> <?php echo htmlspecialchars($base_url); ?></p>
                <p><strong>Database:</strong> Connesso ✅</p>
                <p><strong>Stripe:</strong> Configurato ✅</p>
            </div>
        </details>
    </div>
</body>
</html>
