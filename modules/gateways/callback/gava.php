<?php
/**
 * Gava WHMCS Callback File
 *
 * For more information, please refer to the online documentation.
 *
 * @see http://sam.co.zw/paynow-whmcs-module
 *
 * @copyright Copyright (c) Sam Takunda 2016
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

require_once __DIR__ . '/../gava/vendor/autoload.php';

$apiUrl = $gatewayParams['apiUrl'];
$secret = $gatewayParams['secret'];
$gava = new Gava\Gava($apiUrl, $secret);

$checkout = $gava->processWebhook();

$invoiceId = $checkout->reference;
$transactionId = $checkout->checkoutId;

//Because the Gava PHP Client would have thrown if the chekcout was not paid
$transactionStatus = 'Paid';

$transactionAmount = $checkout->amount;

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 */
checkCbTransID($transactionId);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($gatewayParams['name'], (array) $checkout, $transactionStatus);

/**
 * Add Invoice Payment.
 *
 * Applies a payment transaction entry to the given invoice ID.
 *
 * @param int $invoiceId         Invoice ID
 * @param string $transactionId  Transaction ID
 * @param float $paymentAmount   Amount paid (defaults to full balance)
 * @param float $paymentFee      Payment fee (optional)
 * @param string $gatewayModule  Gateway module name
 */
addInvoicePayment(
    $invoiceId,
    $transactionId,
    $transactionAmount,
    null,
    $gatewayModuleName
);
