<?php
/**
 * WHMCS Gava Payment Gateway Module
 *
 * @see http://sam.co.zw/gava-whmcs-module
 *
 * @copyright Copyright (c) Sam Takunda 2016
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require 'gava/vendor/autoload.php';

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see http://docs.whmcs.com/Gateway_Module_Meta_Data_Parameters
 *
 * @return array
 */
function gava_MetaData()
{
    return array(
        'DisplayName' => 'Gava',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Gateway configuration options.
 *
 * @return array
 */
function gava_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Gava',
        ),
        // a text field type allows for single line text input
        'apiUrl' => array(
            'FriendlyName' => 'API URL',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Gava installation\'s URL here',
        ),
        // a password field type allows for masked text input
        'secret' => array(
            'FriendlyName' => 'Secret',
            'Type' => 'password',
            'Size' => '100',
            'Default' => '',
            'Description' => 'Enter your Gava secret key here',
        ),
        // the yesno field type displays a single checkbox option
        'debugMode' => array(
            'FriendlyName' => 'Debug mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable debug mode.',
        ),
    );

}

/**
 * Payment link.
 *
 * Defines the HTML output displayed on an invoice.
 * Will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see http://docs.whmcs.com/Payment_Gateway_Module_Parameters
 *
 * @return string
 */
function gava_link($params)
{
    // Gateway Configuration Parameters
    $apiUrl = $params['apiUrl'];

    if (!filter_var($url, FILTER_VALIDATE_URL) === false) {
        return 'Invalid API url. Please check your Gava settings';
    }

    $secret = $params['secret'];

    if (!strlen($secret)) return 'Gava API secret not set';

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // System Parameters    
    $moduleName = $params['paymentmethod'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];

    $gava = new Gava\Gava($apiUrl, $secret);

    try
    {

        $checkoutUrl = $gava->createCheckout(
            $reference = $invoiceId,
            $amount = $amount,
            $returnUrl = $returnUrl,
            $cancelUrl = $returnUrl
        );

    } catch(Gava\Exceptions\CheckoutCreationException $e) {
        return gava_error_with_refresh_option($e, $params);
    }

    return gava_payment_button($checkoutUrl, $langPayNow);    
}

function gava_payment_button($url, $langPayNow)
{
    return "<div><a class='btn btn-primary' href='".$url."'>" . $langPayNow . "</a></div>";
}

function gava_error_with_refresh_option($e, $params)
{
    $output = '';
    $output .= '<div>There was an error initiating your payment. Click the button to retry</div>';

    if ($params['debugMode'] === 'on') {
        $output .= '<div>Error: '.$e->getMessage().'</div>';
    }

    $output .= "<div><a class='btn btn-primary' href='".$params['returnurl']."'>Retry</a></div>";
    return $output;
}
