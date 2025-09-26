<?php
require_once 'vendor/autoload.php';

$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
\Stripe\Stripe::setApiKey($stripe_secret_key);

header('Content-Type: application/json');

try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => $_POST['currency'],
                'product_data' => [
                    'name' => $_POST['product_name'],
                ],
                'unit_amount' => $_POST['amount'],
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://your-app-url.onrender.com/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://your-app-url.onrender.com/cancel.php',
    ]);

    echo json_encode(['id' => $session->id]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
