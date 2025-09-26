<?php
require_once 'vendor/autoload.php';

$stripe_secret_key = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_...';
\Stripe\Stripe::setApiKey($stripe_secret_key);

// ✅ CORREGGI: Usa l'URL fisso del tuo sito Render
$base_url = 'https://newstripe.onrender.com';

$product = [
    'name' => 'Prodotto di Test',
    'price' => 2000,
    'currency' => 'eur'
];

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
            // ✅ CORRETTO: URL fissi con il tuo dominio Render
            'success_url' => $base_url . '/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $base_url . '/cancel.php',
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
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
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
