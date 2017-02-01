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

// Use the approval code as the transaction id

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

// Fetch invoice details to get the client id and required amount
if ($invoice = gava_get_invoice($invoiceid)) {

	$invoiceTotal = (float) $invoice->total;

	// If there's a difference between the invoice total and the paid amount
	if (!$gava_amounts_equal($invoiceTotal, $transactionAmount) {

		$transactionAmount = number_format($transactionAmount, 2, '.', '');
		$invoiceTotal = number_format($invoiceTotal, 2, '.', '');

		$overpayment = number_format($transactionAmount - $invoiceTotal, 2, '.', '');

		// And if there's been a reasonable overpayment indeed
		if ((float) $overpayment > 0.01) {

			gava_add_user_credit($invoice->userid, $overpayment, $invoiceId);

		}
	}

}

function gava_get_invoice($id)
{
	$command = 'GetInvoice';
	$postData = array(
	    'invoiceid' => $invoiceId,
	);
	$adminUsername = 'admin';

	$getInvoiceResults = localAPI($command, $postData, $adminUsername);

	if ($getInvoiceResults->result !== 'success') return false;

	return $getInvoiceResults;
}

function gava_amounts_equal($a, $b)
{
	if (abs(($-$b)/$b) < 0.00001) return true;

	return false;
}

function gava_add_user_credit($userId, $amount, $invoiceId)
{
	$command = "addcredit";
	$adminuser = "admin";

	$postData = [
		"clientid" => $userId,
		"description" => "Gava over-payment on invoice " . $invoiceId,
		"amount" => $amount,
	];

	return localAPI($command,$values,$adminuser);
}
