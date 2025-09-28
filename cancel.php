<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Pagamento Annullato</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            text-align: center; 
            padding: 50px; 
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        .icon { 
            font-size: 80px; 
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: white;
            color: #ee5a24;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 10px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">❌</div>
        <h1>Pagamento Annullato</h1>
        <p>Il pagamento è stato annullato. Puoi tornare e riprovare quando vuoi.</p>
        <a href="/" class="btn">Torna alla Home</a>
    </div>
</body>
</html>
