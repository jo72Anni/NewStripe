<?php
require_once 'vendor/autoload.php';

// Le variabili d'ambiente sono già configurate su Render, puoi leggerle direttamente
$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
$stripe_publishable_key = getenv('STRIPE_PUBLISHABLE_KEY');
\Stripe\Stripe::setApiKey($stripe_secret_key);

// Configurazione Database PostgreSQL
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASSWORD');
$db_port = getenv('DB_PORT');

// Base URL - rimuovi lo slash finale se presente
$base_url = rtrim(getenv('BASE_URL'), '/');

// Connessione al database
try {
    $pdo = new PDO(
        "pgsql:host=$db_host;port=$db_port;dbname=$db_name", 
        $db_user, 
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Errore connessione database: " . $e->getMessage());
}

// Crea la tabella se non esiste
function createTransactionsTable($pdo) {
    $sql = "
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
    ";
    $pdo->exec($sql);
}

// Inizializza la tabella
createTransactionsTable($pdo);

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

// ... resto delle funzioni per gestire le transazioni
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Carrello Stripe</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .product { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 10px; }
        button { background: #5469d4; color: white; border: none; padding: 15px 30px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🛒 Carrello Stripe Checkout</h1>
    
    <?php if (isset($error)): ?>
        <div style="color: red;">Errore: <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="product">
        <h2><?php echo htmlspecialchars($product['name']); ?></h2>
        <p style="color: #28a745; font-size: 24px; font-weight: bold;">
            <?php echo number_format($product['price'] / 100, 2); ?> EUR
        </p>
        
        <form method="POST">
            <input type="hidden" name="checkout" value="1">
            <button type="submit">Acquista Ora con Stripe</button>
        </form>
    </div>
</body>
</html>
