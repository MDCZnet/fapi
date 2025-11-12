<?php
declare(strict_types=1);

/**
 * Class SuperfakturaSyncExpenses
 *
 * Loads expenses data from Google Sheets, enriches it with supplier details by VAT ID,
 * and synchronizes expenses with the Superfaktura API.
 *
 * @version 1.0.0
 * @license Proprietary - ONAI internal use only. Redistribution or external use is prohibited.
 * @author  Martin Dittrich <https://MDCZ.net>
 *
 * Changelog:
 * 2025-09-25: Initial version: createExpense, addExpensePayment mehods
 */
final class SuperfakturaSyncExpenses
{
    private SuperfakturaApiEndpoints $apiEndpoints;
    private SuperfakturaSyncPayments $paymentsSync;

    /**
     * Constructor initializes the Superfaktura API client
     */
    public function __construct()
    {
        $apiClient = new SuperfakturaApiClient();
        $this->apiEndpoints = $apiClient->endpoints;

        $payments = new SuperfakturaSyncPayments($this->apiEndpoints);
        $this->paymentsSync = $payments;
    }

    /**
     * Run the synchronization process
     */
    public function sync(): array
    {
        $skipped = [];
        
        // Fetch data from Google Sheets
        $sheetId = GOOGLE_SHEETS_BUSINESS_ID_S;
        $suppliersRange = GOOGLE_SHEETS_BUSINESS_SUPPLIERS_RANGE;
        $expensesRange = (defined('SUPERFAKTURA_IS_SANDBOX') && SUPERFAKTURA_IS_SANDBOX)
            ? GOOGLE_SHEETS_SANDBOX_BUSINESS_EXPENSES_RANGE
            : GOOGLE_SHEETS_BUSINESS_EXPENSES_RANGE;
        
        $expenses   = new GetSheetData($sheetId, $expensesRange)->get();
        $suppliers  = new GetSheetData($sheetId, $suppliersRange)->get();

        // Index suppliers by VAT ID for quick lookup
        $suppliersByVatId = [];
        foreach ($suppliers as $supplier) {
            if (!empty($supplier['vat_id'])) {
                $suppliersByVatId[$supplier['vat_id']] = $supplier;
            }
        }

        // 🪲 Debugging output
        /* 
        echo '┌──────────┐' . PHP_EOL;
        echo '| Expenses |' . PHP_EOL;
        echo '└──────────┘' . PHP_EOL;
        print_r($expenses[0]) . PHP_EOL;
        echo '┌───────────┐' . PHP_EOL;
        echo '| Suppliers |' . PHP_EOL;
        echo '└───────────┘' . PHP_EOL;
        print_r($suppliers[0]) . PHP_EOL;
        exit; */

        foreach ($expenses as $expense) {
            // Skip if already imported
            if (!empty(trim((string)($expense['superfaktura'] ?? '')))) {
                continue;
            }
            // Map & validate
            $mapped = $this->map($expense, $suppliersByVatId);
            if (!$this->validateExpense($mapped, $skipped, $expense)) {
                continue;
            }

            // Create expense in Superfaktura, add payment for this expense and write import date to sheet
            $apiResponse = $this->apiEndpoints->createExpense($mapped);
            $this->paymentsSync->addPayment($expense, $mapped, $apiResponse);
            $this->writeImportDateToSheet($expense, $sheetId, $expensesRange);
        }

        $result = ['skipped' => $skipped ?: null];
        return $result;
    }

    /**
     * Map a single expense row to Superfaktura API payload
     */
    private function map(array $expense, array $suppliersByVatId): array
    {
        $vatId = $expense['vat_id'] ?? '';
        $supplier = $vatId && isset($suppliersByVatId[$vatId]) ? $suppliersByVatId[$vatId] : [];

        $countryCode = strtolower($supplier['country_code'] ?? '');
        $baseEu = $expense['base_eu'] ?? '';
        $currency = $baseEu !== '' ? strtoupper(substr($baseEu, 0, 3)) : 'CZK';
        
        $reverseCharge = $supplier['reverse_charge'] ?? 0;

        $categoryIds = (defined('SUPERFAKTURA_IS_SANDBOX') && SUPERFAKTURA_IS_SANDBOX)
            ? SUPERFAKTURA_SANDBOX_CATEGORY_IDS
            : SUPERFAKTURA_CATEGORY_IDS;

        $expenseAmounts = [];

        if ($baseEu !== '') {
            // EU expense: use baseEu (without currency prefix) as amount, VAT is always 0
            $expenseAmounts = [
                'amount' => Format::formatFloat((substr($baseEu, 3))),          
                'vat'    => 0
            ];
        } else {
            // CZ expense: collect all nonzero bases and their VAT rates
            $bases = [
                ['base' => Format::formatFloat($expense['base_0'] ?? ''),  'vat' => 0],
                ['base' => Format::formatFloat($expense['base_12'] ?? ''), 'vat' => 12],
                ['base' => Format::formatFloat($expense['base_21'] ?? ''), 'vat' => 21],
            ];
            $i = 1;
            foreach ($bases as $row) {
                if ($row['base'] !== '0' && $row['base'] !== '' && $row['base'] !== '0.00') {
                    $suffix = $i === 1 ? '' : (string)$i;
                    $expenseAmounts['amount' . $suffix] = $row['base'];
                    $expenseAmounts['vat' . $suffix] = $row['vat'];
                    $i++;
                }
            }
            if (empty($expenseAmounts)) {
                $expenseAmounts = ['amount' => '0', 'vat' => 0];
            }
        }

        return [
            'Expense' => array_merge([
                'name'                => 'Náklad ' . ($expense['id'] ?? ''),
                'currency'            => $currency,
                'created'             => Format::formatDate($expense['issued']),
                'delivery'            => Format::formatDate($expense['taxable'] ?? ''),
                'due'                 => Format::formatDate($expense['due']     ?? ''),
                'document_number'     => $expense['invoice'] ?? '',
                'expense_category_id' => $categoryIds[$expense['category']] ?? '',
                'variable'            => $expense['vs'] ?? '',
                'total'               => Format::formatFloat($expense['total'] ?? ''),
                'version'             => 'basic'
            ], $expenseAmounts),
            'ExpenseExtra' => [
                'vat_transfer' => $reverseCharge,
                'rounding'     => 'document'
            ],
            'Client' => [
                'name'               => $expense['supplier']                   ?? '',
                'address'            => $supplier['street']                    ?? '',
                'city'               => $supplier['city']                      ?? '',
                'zip'                => $supplier['zip']                       ?? '',
                'country_id'         => SUPERFAKTURA_COUNTRY_IDS[$countryCode] ?? '',
                'ico'                => $supplier['supplier_id']               ?? '',
                'ic_dph'             => $supplier['vat_id']                    ?? '',
                'update_addressbook' => 1
            ]
        ];
    }

    /**
     * Validates mapped expense data before payload generation.
     */
    private function validateExpense(array $data, array &$skipped, array $expense): bool
    {
        // Keys that are not required for validation
        $skipKeys = [
            'variable', 'vat', 'amount', 'vat2', 'amount2', 'vat3', 'amount3',
            'zip', 'ico', 'ic_dph'
        ];

        // Check for missing required fields
        foreach (['Expense', 'ExpenseExtra', 'Client'] as $section) {
            foreach (($data[$section] ?? []) as $key => $value) {
                if (in_array($key, $skipKeys, true)) {
                    continue;
                }
                if ($value === null || $value === '') {
                    $skipped[] = ($data['Expense']['name'] ?? '(unnamed)') . ": Missing data ($key)";
                    return false;
                }
            }
        }

        // Check country code consistency
        $icDph = strtolower(substr($data['Client']['ic_dph'] ?? '', 0, 2));
        $countryId = $data['Client']['country_id'] ?? null;
        $countryKey = array_search($countryId, SUPERFAKTURA_COUNTRY_IDS, true);

        if ($countryKey !== false && $countryKey !== $icDph) {
            $skipped[] = ($data['Expense']['name'] ?? '(unnamed)') . ": Country code mismatch | country_id key '$countryKey' != ic_dph prefix '$icDph'";
            return false;
        }

        // Special validation for EU expenses
        $baseEu = $expense['base_eu'] ?? '';
        if ($baseEu !== '') {
            $base0 = floatval($expense['base_0'] ?? 0);
            $total = floatval($expense['total'] ?? 0);
            if (abs($base0 - $total) > 2) {
                $skipped[] = ($data['Expense']['name'] ?? '(unnamed)') . ': Calculation failed (EU) | ' . $base0 . ' != ' . $total;
                return false;
            }
            return true;
        }

        // Validate sum of amounts and VAT
        $amount  = floatval($data['Expense']['amount']   ?? 0);
        $vat     = floatval($data['Expense']['vat']      ?? 0);
        $amount2 = floatval($data['Expense']['amount2']  ?? 0);
        $vat2    = floatval($data['Expense']['vat2']     ?? 0);
        $amount3 = floatval($data['Expense']['amount3']  ?? 0);
        $vat3    = floatval($data['Expense']['vat3']     ?? 0);
        $total   = floatval($data['Expense']['total']    ?? 0);

        $sum = round(
            $amount  * (1 + $vat / 100) +
            $amount2 * (1 + $vat2 / 100) +
            $amount3 * (1 + $vat3 / 100),
            2
        );
        $diff = abs($sum - $total);
        $percentDiff = ($total != 0) ? abs($sum - $total) / abs($total) * 100 : 0;

        if ($percentDiff > 0.005 && $diff > 2) {
            $skipped[] = ($data['Expense']['name'] ?? '(unnamed)') . ': Calculation failed | ' . $sum . ' != ' . $total . ' | ' . round($diff, 2) . ' (' . round($percentDiff, 2) . '%) diff';
            return false;
        }
        return true;
    }

    /**
     * Writes the import date to the Google Sheet for the given expense.
     */
    private function writeImportDateToSheet(array $expense, string $sheetId, string $range): array
    {
        $sheetName  = explode('!', $range)[0];
        $getSheet   = new GetSheetData($sheetId, $sheetName . '!A1:A')->get();
        $rowNumber  = null;

        foreach ($getSheet as $i => $row) {
            $id = $row['id'];
            if ((string)$id === (string)($expense['id'] ?? '')) {
                $rowNumber = $i+2;
                break;
            }
        }

        if ($rowNumber !== null) {
            $now = date('j.n.Y');
            $setSheet = new SetSheetData($sheetId, $sheetName . '!T' . $rowNumber, [[$now]]);
            $setSheet->set();
        }
        return [
            'error' => 'Row not found for expense id: ' . ($expense['id'] ?? '(unknown)')
        ];
    }
}

// --- CLI runner ---
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/../_config.php';
    require_once __DIR__ . '/../.helpers/Format.php';
    require_once __DIR__ . '/../.helpers/Output.php';
    require_once __DIR__ . '/../.helpers/GetSheetData.php';
    require_once __DIR__ . '/../.helpers/SetSheetData.php';
    require_once __DIR__ . '/../google/GoogleAuth.php';
    require_once __DIR__ . '/../google/GoogleApiClient.php';
    require_once __DIR__ . '/SuperfakturaAuth.php';
    require_once __DIR__ . '/SuperfakturaApiClient.php';
    require_once __DIR__ . '/SuperfakturaApiEndpoints.php';
    require_once __DIR__ . '/SuperfakturaSyncPayments.php';

    $output = new Output();
    $output->start();

    try {
        $apiClient = new SuperfakturaApiClient();
        $syncSuperfaktura = new SuperfakturaSyncExpenses();
        $result = $syncSuperfaktura->sync();

        if (!empty($result['skipped'])) {
            $output->warning('New expenses synced successfully, ' . count($result['skipped']) . ' expenses were skipped');
            $output->result($result);
        } else {
            $output->success('All new expenses synced successfully');
        }
        exit(0);
    } catch (\Throwable $e) {
        $output->error('Error: ' . $e->getMessage());
        exit(1);
    }
}