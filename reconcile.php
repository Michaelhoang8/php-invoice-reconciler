<?php
declare(strict_types=1);
require __DIR__ . '/src/Reconciler.php';

try {
    if ($argc !== 3) {
        throw new InvalidArgumentException('usage: php reconcile.php invoices.csv payments.csv');
    }
    $invoices = Reconciler::readCsv($argv[1], ['invoice_id', 'customer', 'currency', 'amount']);
    $payments = Reconciler::readCsv($argv[2], ['payment_id', 'invoice_id', 'currency', 'amount']);
    echo json_encode(Reconciler::reconcile($invoices, $payments), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}
