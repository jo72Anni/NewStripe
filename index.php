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

// Informazioni di connessione per debug
$connection_info = [
    'DB Host' => $db_host,
    'DB Name' => $db_name,
    'DB User' => $db_user,
    'DB Port' => $db_port,
    'Base URL' => $base_url,
    'Stripe Key' => substr($stripe_secret_key, 0, 20) . '...' // Mostra solo i primi 20 caratteri per sicurezza
];

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
    
    // Test query per verificare la connessione
    $stmt = $pdo->query("SELECT version() as postgres_version, current_database() as db_name, current_user as db_user");
    $db_info = $stmt->fetch();
    $connection_info['PostgreSQL Version'] = $db_info['postgres_version'];
    $connection_info['Connected Database'] = $db_info['db_name'];
    $connection_info['Connected User'] = $db_info['db_user'];
    
} catch (PDOException $e) {
    $connection_error = "Errore connessione database: " . $e->getMessage();
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
if (isset($pdo)) {
    createTransactionsTable($pdo);
}

// Funzione per ottenere statistiche del database
function getDatabaseStats($pdo) {
    $stats = [];
    
    // Conta le transazioni
    $stmt = $pdo->query("SELECT COUNT(*) as total_transactions FROM transactions");
    $stats['total_transactions'] = $stmt->fetchColumn();
    
    // Dimensione del database
    $stmt = $pdo->query("SELECT pg_size_pretty(pg_database_size(current_database())) as db_size");
    $stats['database_size'] = $stmt->fetchColumn();
    
    // Liste delle tabelle
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $stats['tables'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $stats;
}

// Ottieni statistiche se connesso
if (isset($pdo)) {
    try {
        $db_stats = getDatabaseStats($pdo);
    } catch (Exception $e) {
        $db_stats_error = $e->getMessage();
    }
}

$product = [
    'name' => 'Prodotto di Test',
    'price' => 2000,
    'currency' => 'eur'
];

// Gestione checkout
if (($_POST['checkout'] ?? false) && isset($pdo)) {
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

// Verifica estensioni PHP caricate
$php_extensions = get_loaded_extensions();
$important_extensions = array_filter($php_extensions, function($ext) {
    return stripos($ext, 'pdo') !== false || 
           stripos($ext, 'pgsql') !== false || 
           stripos($ext, 'curl') !== false ||
           stripos($ext, 'json') !== false;
});
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Carrello Stripe - Debug Info</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; }
        .product { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 10px; }
        .debug-info { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 10px; border-left: 4px solid #007bff; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        button { background: #5469d4; color: white; border: none; padding: 15px 30px; border-radius: 5px; cursor: pointer; margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .section { margin: 30px 0; }
    </style>
</head>
<body>
    <h1>🛒 Carrello Stripe Checkout</h1>
    
    <!-- Sezione Debug Informazioni -->
    <div class="debug-info">
        <h2>🔧 Informazioni di Sistema e Connessione</h2>
        
        <div class="section">
            <h3>📊 Informazioni PHP</h3>
            <table>
                <tr><th>PHP Version</th><td><?php echo phpversion(); ?></td></tr>
                <tr><th>Server Software</th><td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td></tr>
            </table>
        </div>

        <div class="section">
            <h3>🔌 Estensioni PHP Caricate</h3>
            <p><?php echo implode(', ', $important_extensions); ?></p>
        </div>

        <div class="section">
            <h3>📡 Configurazione Connessione</h3>
            <table>
                <?php foreach ($connection_info as $key => $value): ?>
                <tr>
                    <th><?php echo htmlspecialchars($key); ?></th>
                    <td><?php echo htmlspecialchars($value); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h3>🗄️ Stato Database</h3>
            <?php if (isset($connection_error)): ?>
                <p class="error"><?php echo htmlspecialchars($connection_error); ?></p>
            <?php elseif (isset($db_stats)): ?>
                <table>
                    <tr><th>Transazioni Totali</th><td><?php echo $db_stats['total_transactions']; ?></td></tr>
                    <tr><th>Dimensione Database</th><td><?php echo $db_stats['database_size']; ?></td></tr>
                    <tr><th>Tabelle</th><td><?php echo implode(', ', $db_stats['tables']); ?></td></tr>
                </table>
            <?php else: ?>
                <p class="warning">Impossibile recuperare statistiche database</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h3>🔄 Test Connessioni</h3>
            <form method="POST">
                <button type="submit" name="test_db" value="1">Test Connessione DB</button>
                <button type="submit" name="test_stripe" value="1">Test Connessione Stripe</button>
            </form>
            
            <?php
            if ($_POST['test_db'] ?? false) {
                if (isset($pdo)) {
                    echo "<p class='success'>✅ Connessione database funzionante!</p>";
                } else {
                    echo "<p class='error'>❌ Connessione database fallita</p>";
                }
            }
            
            if ($_POST['test_stripe'] ?? false) {
                try {
                    $balance = \Stripe\Balance::retrieve();
                    echo "<p class='success'>✅ Connessione Stripe funzionante!</p>";
                } catch (Exception $e) {
                    echo "<p class='error'>❌ Connessione Stripe fallita: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
            ?>
        </div>
    </div>

    <!-- Sezione Errori -->
    <?php if (isset($error)): ?>
        <div style="color: red; padding: 15px; background: #f8d7da; border-radius: 5px;">
            <strong>Errore Checkout:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Prodotto -->
    <div class="product">
        <h2><?php echo htmlspecialchars($product['name']); ?></h2>
        <p style="color: #28a745; font-size: 24px; font-weight: bold;">
            <?php echo number_format($product['price'] / 100, 2); ?> EUR
        </p>
        
        <?php if (isset($pdo)): ?>
            <form method="POST">
                <input type="hidden" name="checkout" value="1">
                <button type="submit">Acquista Ora con Stripe</button>
            </form>
        <?php else: ?>
            <p class="error">⚠️ Impossibile procedere con l'acquisto - Database non connesso</p>
        <?php endif; ?>
    </div>
</body>
</html>
