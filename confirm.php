<?php
session_start();

require_once __DIR__ . '/.helpers/Validate.php';
require_once __DIR__ . '/.helpers/ExchCzkEur.php';

// Define prices in CZK
define('PRICE_COFFEE', 40);
define('PRICE_SUGAR', 5);
define('PRICE_MILK', 5);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$name       = trim($_POST['name'] ?? '');
$seat       = trim($_POST['seat'] ?? '');
$telPrefix  = $_POST['tel_prefix'] ?? '';
$telNumber  = preg_replace('/\D+/', '', $_POST['tel_number'] ?? '');
$email      = trim($_POST['email'] ?? '');

$errors = [];

if (!Validate::name($name)) {
    $errors[] = 'name';
}

if (!Validate::seat($seat)) {
    $errors[] = 'seat';
}

if (!Validate::telNumber($telNumber === '' ? null : (int)$telNumber)) {
    $errors[] = 'tel_number';
}

if (!Validate::email($email)) {
    $errors[] = 'email';
}

if ($errors) {
    $_SESSION['form_old'] = [
        'name'       => $name,
        'seat'       => $seat,
        'tel_prefix' => $telPrefix,
        'tel_number' => $telNumber,
        'email'      => $email,
    ];
    $_SESSION['form_errors'] = array_fill_keys($errors, true);
    header('Location: index.php');
    exit;
}

$isValidRequest = true;
$tel = $telPrefix . $telNumber;
$coffees = [];
$totalPrice = 0;
$coffeeNumber = 1;

while (isset($_POST["sugar_$coffeeNumber"])) {
    $sugar = htmlspecialchars($_POST["sugar_$coffeeNumber"]);
    $milk = htmlspecialchars($_POST["milk_$coffeeNumber"]);

    $coffeePrice = PRICE_COFFEE;
    if ($sugar === 's_cukrem') {
        $coffeePrice += PRICE_SUGAR;
    }
    if ($milk === 's_mlekem') {
        $coffeePrice += PRICE_MILK;
    }

    $coffees[] = [
        'number' => $coffeeNumber,
        'sugar' => $sugar === 's_cukrem' ? 's cukrem' : 'bez cukru',
        'milk' => $milk === 's_mlekem' ? 's mlékem' : 'bez mléka',
        'price' => $coffeePrice,
        'price_eur' => ExchCzkEur::convert($coffeePrice)
    ];

    $totalPrice += $coffeePrice;
    $coffeeNumber++;
}

$totalPriceEur = ExchCzkEur::convert($totalPrice);

$eurRate = ExchCzkEur::rate();
$vatRate = 0.21;
$taxBaseCzk = round($totalPrice / (1 + $vatRate), 2);
$vatAmountCzk = round($totalPrice - $taxBaseCzk, 2);
$taxBaseEur = ExchCzkEur::convert($taxBaseCzk);
$vatAmountEur = ExchCzkEur::convert($vatAmountCzk);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potvrzení objednávky kávy</title>
    <link rel="stylesheet" href=".assets/style.css">
</head>
<body>
    <div class="container">
        <?php if ($isValidRequest): ?>
            <h1>Potvrzení objednávky kávy</h1>

            <h2>Osobní údaje</h2>
            <p><strong>Jméno a příjmení:</strong> <?= $name ?></p>
            <p><strong>Číslo sedačky:</strong> <?= $seat ?></p>
            <p><strong>Telefon:</strong> <?= $tel ?></p>
            <p><strong>E-mail:</strong> <?= $email ?></p>
            
            <h2>Objednané kávy</h2>
            <?php foreach ($coffees as $coffee): ?>
                <p>
                    <strong><?= $coffee['number'] ?>. Káva:</strong> 
                    <?= $coffee['sugar'] ?>, <?= $coffee['milk'] ?>
                    ... <strong><?= $coffee['price'] ?> Kč / €<?= number_format($coffee['price_eur'], 2, ',', ' ') ?></strong>
                </p>
            <?php endforeach; ?>

            <h2>Celková cena: <?= $totalPrice ?> Kč / €<?= number_format($totalPriceEur, 2, ',', ' ') ?></h2>

            <p>
                Kurz €: <?= number_format($eurRate, 2, ',', ' ') ?> Kč<br>
                Základ daně: <?= $taxBaseCzk ?> Kč / €<?= number_format($taxBaseEur, 2, ',', ' ') ?><br>
                DPH (21 %): <?= $vatAmountCzk ?> Kč / €<?= number_format($vatAmountEur, 2, ',', ' ') ?>
            </p>

            <p><a href="index.php">Nová objednávka</a></p>
            
        <?php else: ?>
            <h1>Objednávkový formulář</h1>
            <p><a href="index.php">Objednat kávu</a></p>
        <?php endif; ?>
    </div>
</body>
</html>