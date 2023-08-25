<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Module\Registrar\phregistrar\ApiClient;
use WHMCS\Results\ResultsList as TldResultsList;
use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Exception\Module\InvalidConfiguration;

/**
 * @see https://docs.whmcs.com/Additional_Domain_Fields
 * For .ro domain you have to follow https://docs.whmcs.com/Additional_Domain_Fields
 * and to add additional fields in the domain registration form "cnp" for individuals and "reg_com" for companies
 */


/**
 * Define module related metadata
 *
 * Provide some module information including the display name and API Version to
 * determine the method of decoding the input values.
 *
 * @return array
 */
function phregistrar_MetaData()
{
    return array(
        'DisplayName' => 'PidginHost',
        'APIVersion' => '1.1',
    );
}

/**
 * Define registrar configuration options.
 *
 * @return array
 */
function phregistrar_getConfigArray()
{
    return [
        // Friendly display name for the module
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'PidginHost Register Module for WHMCS',
        ],
        'apikey' => [
            'FriendlyName' => 'Token',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter secret value here',
        ],
    ];
}

/**
 * Validate apikey
 * @see https://developers.whmcs.com/domain-registrars/config-options/
 */
function phregistrar_config_validate($params)
{
    $token = $params['apikey'];

    $valid = false;
    try {
        $api = new ApiClient();
        $api->call('GET', 'domain', $token);
    } catch (\Exception $e) {
        throw new InvalidConfiguration($e->getMessage());
    }

    // $response = yourmodulename_apicall('validate_credentials', [
    //     'apikey' => $apiKey,
    // ]);
    // $valid = $response['valid'];
}


/**
 * Register a domain.
 *
 * Attempt to register a domain with the domain registrar.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain registration order
 * * When a pending domain registration order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function phregistrar_RegisterDomain($params)
{
    $token = $params['apikey'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    $contact = array(
        "first_name" => $params["firstname"],
        "last_name" => $params["lastname"],
        "company" => $params["companyname"],
        "address" => $params["address1"],
        "city" => $params["city"],
        "region" => $params["state"],
        "postal_code" => $params["postcode"],
        "country" => $params["countrycode"],
        "email" => $params["email"],
        "phone" => $params["phonenumber"],
        "cif_cnp" => $params["additionalfields"]["CNPFiscalCode"],
        "reg_com" => $params["additionalfields"]["Registration Number"]
    );

    $nameservers = array(); // Create an empty array to store non-empty nameservers

    for ($i = 1; $i <= 5; $i++) {
        $variableName = $params['ns' . $i]; // Generate the variable name
        if (isset($variableName) && !empty($variableName)) {
            $nameservers[] = $variableName; // Add non-empty nameservers to the array
        }
    }

    $nameserverString = implode(',', $nameservers); // Convert the array to a comma-separated string

    // Build post data
    $postfields = array(
        'domain' => $sld . '.' . $tld,
        'years' => $params['regperiod'],
        'nameservers' => $nameserverString,
        'contact' => $contact,
        'technical' => $contact,
        'admin' => $contact,
        'billing' => $contact,
    );

    try {
        // throw new \Exception('Test: '. implode(" ", array_keys($params['additionalfields'])));
        $api = new ApiClient();
        $api->call('POST', 'domain', $token, $postfields);

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Renew a domain.
 *
 * Attempt to renew/extend a domain for a given number of years.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain renewal order
 * * When a pending domain renewal order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function phregistrar_RenewDomain($params)
{
    // user defined configuration values
    $token = $params['apikey'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data.
    $postfields = array(
        'years' => $params['regperiod'],
    );

    try {
        $api = new ApiClient();
        $api->call('POST', "domain/$sld.$tld/renew", $token, $postfields);

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Fetch current nameservers.
 *
 * This function should return an array of nameservers for a given domain.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function phregistrar_GetNameservers($params)
{
    // user defined configuration values
    $token = $params['apikey'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    try {
        $api = new ApiClient();
        $api->call('GET', "domain/$sld.$tld", $token);

        $nameservers = explode(',', $api->getFromResponse('nameservers'));
        $resultArray = array();

        $ns_num = 0;
        foreach ($nameservers as $ns) {
            $ns_num++;
            $variableName = "ns" . $ns_num;
            $resultArray[$variableName] = $ns;
        }
        return $resultArray;

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Save nameserver changes.
 *
 * This function should submit a change of nameservers request to the
 * domain registrar.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function phregistrar_SaveNameservers($params)
{
    // user defined configuration values
    $token = $params['apikey'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    $nameservers = array(); // Create an empty array to store non-empty nameservers

    for ($i = 1; $i <= 5; $i++) {
        $variableName = $params['ns' . $i]; // Generate the variable name
        if (isset($variableName) && !empty($variableName)) {
            $nameservers[] = $variableName; // Add non-empty nameservers to the array
        }
    }

    $nameserverString = implode(',', $nameservers); // Convert the array to a comma-separated string

    // Build post data
    $postfields = array(
        'nameservers' => $nameserverString,
    );

    // try {
    //     throw new \Exception('Test: '. implode(" ", $postfields));
    // } catch (\Exception $e) {
    //     return array(
    //         'error' => $e->getMessage(),
    //     );
    // }
    try {
        $api = new ApiClient();
        $api->call('PATCH', "domain/$sld.$tld", $token, $postfields);

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}


/**
 * 
 * TLD sync pricing.
 * @see https://developers.whmcs.com/domain-registrars/tld-pricing-sync/
 * 
 */
function phregistrar_GetTldPricing(array $params)
{
    // Perform API call to retrieve extension information
    // A connection error should return a simple array with error key and message
    // return ['error' => 'This error occurred',];
    $token = $params['apikey'];
    $results = new TldResultsList;

    $page = 1;
    do {
        // Prepare the postfields for the API request
        $postfields = array(
            'page' => $page,
        );

        try {
            $api = new ApiClient();
            $response = $api->call('GET', 'tld', $token, $postfields);

            // Check if the response has fewer than 20 objects
            if (empty($response['next'])) {
                break; // Exit the loop if 'next' is null or empty
            }

            // Process the data from the current page
            foreach ($response['results'] as $extension) {
                $item = (new ImportItem)
                    ->setExtension($extension['tld'])
                    ->setMinYears(1)
                    ->setMaxYears(5)
                    ->setRegisterPrice($extension['price'])
                    ->setRenewPrice($extension['renew_price'])
                    ->setTransferPrice($extension['transfer_price'] ?? -1)
                    ->setCurrency('EUR');
                    // ->setRedemptionFeeDays($extension['redemptionDays'])
                    // ->setRedemptionFeePrice($extension['redemptionFee'])
                    // ->setEppRequired($extension['transferSecretRequired']);
                $results[] = $item;
            }
            // Increment the page number for the next request
            $page++;

            if ($page == 20) {
                break;
            }

        } catch (\Exception $e) {
            return array(
                'error' => $e->getMessage(),
            );
        }

    } while (true);
    return $results;
}
