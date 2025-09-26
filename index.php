<?php
require_once 'vendor/autoload.php';

// Carica variabili d'ambiente (sicuro su Render.com)
$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
$stripe_public_key = getenv('STRIPE_PUBLISHABLE_KEY');

// Verifica che le chiavi siano presenti
if (!$stripe_secret_key || !$stripe_public_key) {
    die("Errore: Configura le variabili d'ambiente STRIPE_SECRET_KEY e STRIPE_PUBLISHABLE_KEY su Render.com");
}

// Configura Stripe con la chiave segreta
\Stripe\Stripe::setApiKey($stripe_secret_key);

// Prodotto di esempio per il carrello
$products = [
    [
        'id' => 1,
        'name' => 'Prodotto 1',
        'price' => 2000, // 20.00 EUR in centesimi
        'currency' => 'eur'
    ],
    [
        'id' => 2, 
        'name' => 'Prodotto 2',
        'price' => 3500, // 35.00 EUR
        'currency' => 'eur'
    ]
];

// Gestisci aggiunta al carrello
if ($_POST['action'] ?? '' === 'add_to_cart') {
    $product_id = $_POST['product_id'];
    // Qui gestiresti la sessione del carrello
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrello Checkout - Stripe</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .product { border: 1px solid #ddd; padding: 20px; margin: 10px 0; border-radius: 5px; }
        .price { color: #28a745; font-weight: bold; }
        button { background: #5469d4; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        button:hover { background: #3a4bc1; }
    </style>
</head>
<body>
    <h1>🛒 Il Mio Carrello</h1>
    <p>Configurazione Stripe: <strong><?php echo $stripe_public_key ? '✅ Attiva' : '❌ Mancante'; ?></strong></p>
    
    <h2>Prodotti disponibili:</h2>
    
    <?php foreach ($products as $product): ?>
    <div class="product">
        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
        <p class="price"><?php echo number_format($product['price'] / 100, 2); ?> EUR</p>
        
        <form action="checkout.php" method="POST">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
            <input type="hidden" name="amount" value="<?php echo $product['price']; ?>">
            <input type="hidden" name="currency" value="<?php echo $product['currency']; ?>">
            <input type="hidden" name="product_name" value="<?php echo $product['name']; ?>">
            
            <button type="submit">Acquista Ora</button>
        </form>
    </div>
    <?php endforeach; ?>

    <footer>
        <p>Powered by Stripe - Chiave pubblica: <?php echo substr($stripe_public_key, 0, 12) . '...'; ?></p>
    </footer>
</body>
</html>
