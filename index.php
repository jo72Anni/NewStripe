<?php
require_once 'vendor/autoload.php';

// Configura Stripe
$stripe_secret_key = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_...'; // Fallback per test
\Stripe\Stripe::setApiKey($stripe_secret_key);

$stripe_public_key = getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_...';

// Prodotto di esempio
$product = [
    'name' => 'Prodotto di Test',
    'price' => 2000, // 20.00 EUR in centesimi
    'currency' => 'eur'
];

// Crea Checkout Session quando si clicca il pulsante
if ($_POST['checkout'] ?? false) {
    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $product['currency'],
                    'product_data' => [
                        'name' => $product['name'],
                    ],
                    'unit_amount' => $product['price'],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/cancel.php',
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrello Stripe</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .product { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 10px; }
        .price { color: #28a745; font-size: 24px; font-weight: bold; }
        button { background: #5469d4; color: white; border: none; padding: 15px 30px; border-radius: 5px; cursor: pointer; font-size: 18px; }
        button:hover { background: #3a4bc1; }
        .error { color: red; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🛒 Carrello Stripe Checkout</h1>
    
    <?php if (isset($error)): ?>
        <div class="error">Errore: <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="product">
        <h2><?php echo htmlspecialchars($product['name']); ?></h2>
        <p class="price"><?php echo number_format($product['price'] / 100, 2); ?> EUR</p>
        
        <form method="POST">
            <input type="hidden" name="checkout" value="1">
            <button type="submit">Acquista Ora con Stripe</button>
        </form>
    </div>

    <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
        <h3>Stato configurazione:</h3>
        <p>Chiave Stripe: <?php echo $stripe_secret_key ? '✅ Configurata' : '❌ Mancante'; ?></p>
        <p>Public Key: <?php echo substr($stripe_public_key, 0, 12) . '...'; ?></p>
    </div>
</body>
</html>
