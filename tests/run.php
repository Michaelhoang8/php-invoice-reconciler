<?php
declare(strict_types=1);
require __DIR__ . '/../src/Reconciler.php';
function check(bool $condition, string $name): void {
    if (!$condition) { throw new RuntimeException($name); }
}
$invoices = Reconciler::readCsv(__DIR__ . '/../fixtures/invoices.csv', ['invoice_id', 'customer', 'currency', 'amount']);
$payments = Reconciler::readCsv(__DIR__ . '/../fixtures/payments.csv', ['payment_id', 'invoice_id', 'currency', 'amount']);
$result = Reconciler::reconcile($invoices, $payments);
$byId = array_column($result['invoices'], null, 'invoice_id');
check($byId['INV-1']['status'] === 'paid' && $byId['INV-1']['paid_cents'] === 30, '0.10 + 0.20 exactly matches 0.30');
check($byId['INV-2']['status'] === 'partial' && $byId['INV-2']['balance_cents'] === 7500, 'partial payment');
check($byId['INV-3']['status'] === 'overpaid' && $byId['INV-3']['balance_cents'] === -500, 'overpayment');
check($byId['INV-4']['status'] === 'unpaid', 'wrong currency excluded');
check($byId['INV-5']['status'] === 'paid', 'zero-value invoice');
check(!isset($byId['DUP']), 'duplicate invoice quarantined');
$types = array_count_values(array_column($result['issues'], 'type'));
check($types === ['duplicate_invoice_id' => 1, 'duplicate_payment_id' => 1, 'unmatched_payment' => 1, 'currency_mismatch' => 1], 'issue categories');
foreach (['1.001', '-1.00', '1e2', '01.00', '1000000000.00', '1', 'NaN'] as $bad) {
    $rejected = false;
    try { Reconciler::cents($bad); } catch (InvalidArgumentException) { $rejected = true; }
    check($rejected, 'reject malformed amount: ' . $bad);
}
check(Reconciler::cents('999999999.99') === 99999999999, 'maximum exact amount');
check(Reconciler::reconcile([], []) === ['invoices' => [], 'issues' => []], 'empty inputs');
echo "invoice reconciliation tests passed\n";
