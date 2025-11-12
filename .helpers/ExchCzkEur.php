<?php
declare(strict_types=1);

final class ExchCzkEur
{
    private const API   = 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.xml';
    private const CACHE = __DIR__ . '/ExchCzkEur.cache';
    private const TTL   = 86400;
    private const FALLBACK = 25.0;

    private static ?float $rate = null;

    public static function convert(float $czk): float
    {
        return round($czk / self::getRate(), 2);
    }

    public static function rate(): float
    {
        return round(self::getRate(), 2);
    }

    private static function getRate(): float
    {
        if (self::$rate !== null) {
            return self::$rate;
        }

        if (is_file(self::CACHE) && (time() - (filemtime(self::CACHE) ?: 0)) < self::TTL) {
            $cached = (float)file_get_contents(self::CACHE);
            if ($cached > 0) {
                return self::$rate = $cached;
            }
        }

        $xml = @simplexml_load_file(self::API);
        if ($xml) {
            foreach ($xml->xpath('//radek[@kod="EUR"]') as $row) {
                $amount = (int)($row['mnozstvi'] ?? 1) ?: 1;
                $value  = (float)str_replace(',', '.', (string)($row['kurz'] ?? 0));
                if ($value > 0) {
                    self::$rate = $value / $amount;
                    file_put_contents(self::CACHE, (string)self::$rate);
                    return self::$rate;
                }
            }
        }

        return self::$rate = self::FALLBACK;
    }
}