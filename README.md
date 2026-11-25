# Invoice and payment reconciler

Match invoice records with payments using integer cents. The report separates paid, unpaid, partial and overpaid invoices and retains issues that need review.

```sh
php tests/run.php
php reconcile.php fixtures/invoices.csv fixtures/payments.csv
```

Inputs are CSV files with these exact headers:

- Invoices: `invoice_id,customer,currency,amount`
- Payments: `payment_id,invoice_id,currency,amount`

Amounts require exactly two decimal places and range from 0.00 to 999999999.99. Payments aggregate by invoice ID only when their currency matches. Currency values require three uppercase letters; the program does not perform foreign-exchange conversion or validate a complete ISO currency registry.

Repeated invoice IDs quarantine all occurrences. Repeated payment IDs likewise contribute nothing, even when the rows are identical. Unmatched payments and currency mismatches remain in a separate issue list. Balances can be negative for overpayments. Duplicate invoice IDs are omitted from the invoice result table and reported as issues.

The fixtures include exact 0.10 + 0.20 arithmetic, partial payments, overpayment, zero-value invoices, duplicate IDs and wrong-currency payments. Native tests exercise those rules and reject malformed amounts. Tests and the CLI passed with PHP 8.3.6 in an Ubuntu container. The CLI report was independently checked for five invoices and four retained issues. Ubuntu CI runs syntax checks, the tests and the CLI.

Requires 64-bit PHP 8.2 or newer, with no Composer packages. This is a reconciliation report, not a posting or settlement system.
