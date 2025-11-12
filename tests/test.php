<?php
require_once __DIR__ . '/../.helpers/Validate.php';

if (php_sapi_name() !== 'cli') {
    echo <<<'HTML'
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Testy PHP validace</title>
        <link rel="stylesheet" href="../.assets/style.css">
    </head>
    <body>
        <div class="container">
            <h1>Testy PHP validace</h1>
            <pre>
    HTML;
}

function assertTrue(bool $condition, string $description): void {
    if ($condition) {
        echo "✅ PASS - $description" . PHP_EOL;
    } else {
        echo "❌FAIL: $description" . PHP_EOL;
        exit(1);
    }
}

assertTrue(Validate::name('Jan Novak'), 'VALID NAME / Jan Novak');
assertTrue(!Validate::name('12'), 'INVALID NAME / 12');

assertTrue(Validate::email('jan@example.com'), 'VALID EMAIL / jan@example.com');
assertTrue(!Validate::email('jan@@example.com'), 'INVALID EMAIL / jan@@example.com');

assertTrue(Validate::seat('A1234'), 'VALID SEAT / A1234');
assertTrue(!Validate::seat('12345'), 'INVALID SEAT / 12345');

assertTrue(Validate::telNumber(724123456), 'VALID PHONE / 724123456');
assertTrue(!Validate::telNumber(12345), 'INVALID PHONE / 12345');


if (php_sapi_name() !== 'cli') {
    echo <<<'HTML'
            </pre>
        </div>
    </body>
    </html>
    HTML;
}