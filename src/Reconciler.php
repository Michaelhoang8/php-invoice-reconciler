<?php
declare(strict_types=1);

final class Reconciler
{
    public static function cents(string $amount): int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,8})\.([0-9]{2})$/D', $amount, $parts)) {
            throw new InvalidArgumentException("Amount must be nonnegative with two decimals, at most 999999999.99: $amount");
        }
        return ((int)$parts[1]) * 100 + (int)$parts[2];
    }

    public static function readCsv(string $path, array $columns): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open $path");
        }
        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if ($header !== false && isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            if ($header !== $columns) {
                throw new InvalidArgumentException('Unexpected CSV columns: ' . $path);
            }
            $rows = [];
            for ($line = 2; ($values = fgetcsv($handle, null, ',', '"', '')) !== false; $line++) {
                if (count($values) !== count($columns) || in_array(null, $values, true)) {
                    throw new InvalidArgumentException("$path row $line: missing or extra fields");
                }
                $row = array_combine($columns, $values);
                foreach ($row as $key => $value) {
                    if (trim($value) === '') {
                        throw new InvalidArgumentException("$path row $line: blank $key");
                    }
                }
                if (!preg_match('/^[A-Z]{3}$/D', $row['currency'])) {
                    throw new InvalidArgumentException("$path row $line: currency requires three uppercase letters");
                }
                $row['cents'] = self::cents($row['amount']);
                $rows[] = $row;
            }
            if (!feof($handle)) {
                throw new RuntimeException("Read failed: $path");
            }
            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** Duplicate identifiers quarantine every occurrence, even identical rows. */
    private static function uniqueRows(array $rows, string $key, array &$issues): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row[$key]][] = $row;
        }
        $unique = [];
        foreach ($groups as $id => $group) {
            if (count($group) > 1) {
                $issues[] = ['type' => 'duplicate_' . $key, 'id' => (string)$id, 'rows' => count($group)];
            } else {
                $unique[$id] = $group[0];
            }
        }
        return $unique;
    }

    public static function reconcile(array $invoices, array $payments): array
    {
        if (PHP_INT_SIZE < 8) {
            throw new RuntimeException('64-bit PHP is required for integer-cent accounting');
        }
        $issues = [];
        $validInvoices = self::uniqueRows($invoices, 'invoice_id', $issues);
        $validPayments = self::uniqueRows($payments, 'payment_id', $issues);
        $totals = [];
        foreach ($validPayments as $payment) {
            $id = $payment['invoice_id'];
            if (!isset($validInvoices[$id])) {
                $issues[] = ['type' => 'unmatched_payment', 'id' => $payment['payment_id'], 'invoice_id' => $id];
                continue;
            }
            if ($payment['currency'] !== $validInvoices[$id]['currency']) {
                $issues[] = ['type' => 'currency_mismatch', 'id' => $payment['payment_id'], 'invoice_id' => $id];
                continue;
            }
            $old = $totals[$id] ?? 0;
            if ($payment['cents'] > PHP_INT_MAX - $old) {
                throw new OverflowException('Payment total exceeds 64-bit integer range');
            }
            $totals[$id] = $old + $payment['cents'];
        }
        ksort($validInvoices, SORT_STRING);
        $results = [];
        foreach ($validInvoices as $id => $invoice) {
            $paid = $totals[$id] ?? 0;
            $due = $invoice['cents'] - $paid;
            $status = $due === 0 ? 'paid' : ($paid === 0 ? 'unpaid' : ($due > 0 ? 'partial' : 'overpaid'));
            $results[] = ['invoice_id' => (string)$id, 'customer' => $invoice['customer'],
                'currency' => $invoice['currency'], 'invoice_cents' => $invoice['cents'],
                'paid_cents' => $paid, 'balance_cents' => $due, 'status' => $status];
        }
        return ['invoices' => $results, 'issues' => $issues];
    }
}
