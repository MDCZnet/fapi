<?php
session_start();

$old    = $_SESSION['form_old'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_old'], $_SESSION['form_errors']);

$value = fn(string $key) => htmlspecialchars($old[$key] ?? '', ENT_QUOTES);
$hasError = fn(string $key) => isset($errors[$key]) ? ' class="error"' : '';

$prefixes = ['+420', '+421', '+43', '+44', '+45', '+46', '+47', '+48', '+49', '+351', '+352', '+353', '+354', '+355', '+356', '+357', '+358', '+359', '+360', '+371', '+372', '+373', '+374', '+375', '+376', '+377', '+378', '+379', '+380', '+381', '+382', '+385', '+386', '+387', '+389', '+390'];
$defaultPrefix = in_array('+420', $prefixes, true) ? '+420' : ($prefixes[0] ?? '+420');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objednávka kávy</title>
    <link rel="stylesheet" href=".assets/style.css">
</head>
<body>
    <div class="container">
    <a href="tests/js.html" target="_blank">Testy JS validace</a> | <a href="tests/test.php" target="_blank">Testy PHP validace</a><br />

    <h1>Objednávka kávy</h1>

    <form id="orderForm" method="POST" action="confirm.php">
        <label<?= $hasError('name') ?>>Jméno a příjmení:</label>
        <input id="name" type="text" name="name" placeholder="Jan Novák"<?= $hasError('name') ?> value="<?= $value('name') ?>">

        <label<?= $hasError('seat') ?>>Číslo sedačky:</label>
        <input id="seat" type="text" name="seat" maxlength="5" placeholder="A1234"<?= $hasError('seat') ?> value="<?= $value('seat') ?>">

        <label<?= $hasError('tel_number') ?>>Telefon:</label>
        <div class="phone-field">
            <select id="telPrefix" name="tel_prefix">
                <?php foreach ($prefixes as $prefix): ?>
                    <option value="<?= htmlspecialchars($prefix, ENT_QUOTES) ?>" <?= ($old['tel_prefix'] ?? $defaultPrefix) === $prefix ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prefix, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="tel" id="telNumber" name="tel_number" inputmode="numeric" maxlength="12" placeholder="724123456"<?= $hasError('tel_number') ?> value="<?= htmlspecialchars($old['tel_number'] ?? '', ENT_QUOTES) ?>">
        </div>

        <label<?= $hasError('email') ?>>E-mail:</label>
        <input id="email" type="email" name="email" placeholder="jan.novak@example.com"<?= $hasError('email') ?> value="<?= $value('email') ?>">

        <div id="coffeeContainer"></div>

        <a href="#" id="addCoffee">další káva</a>

        <button type="submit">Odeslat</button>
    </form>
    </div>

    <script src=".assets/coffee.js"></script>
    <script src=".assets/validate.js"></script>
</body>
</html>