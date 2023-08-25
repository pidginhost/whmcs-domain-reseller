<?php

namespace WHMCS\Module\Registrar\Phregistrar;


/**
 * Sample Registrar Module Simple API Client.
 *
 * A simple API Client for communicating with an external API endpoint.
 */
class ApiClient
{
    const API_URL = 'https://redesign.pidginhost.com/api/';

    protected $results = array();

    /**
     * Make external API call to registrar API.
     *
     * @param string $action
     * @param array $postfields
     *
     * @throws \Exception Connection error
     * @throws \Exception Bad API response
     *
     * @return array
     */
    public function call($method, $url, $token, $postfields = array())
    {
        // $headers = array(
        //     'Authorization' => "Token $token"
        // );

        $headers = [
            "Authorization: Token $token",
            // Replace with your Bearer Token
            'Content-Type: application/json', // Replace with the appropriate Content-Type header
        ];

        $ch = curl_init();
        // Set the target URL
        curl_setopt($ch, CURLOPT_URL, self::API_URL . $url . '/');
        // Set the headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);

        // Set the HTTP method
        switch ($method) {
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                // You can add support for other HTTP methods if needed
                break;
        }
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception('Connection Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
        }

        if (curl_error($ch)) {
            throw new \Exception('Api error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->results = $this->processResponse($response);

        logModuleCall(
            'Phregister',
            "$method $url",
            $postfields,
            $response,
        );

        if ($httpCode == 400) {
            $data = json_decode($response, true);
            if (isset($data['extra']['fields']['non_field_errors'])) {
                throw new \Exception($data['extra']['fields']['non_field_errors'][0]);
            }
            if (isset($data['extra']['fields'])) {
                $firstKey = array_key_first($data['extra']['fields']);
                throw new \Exception(json_encode($data['extra']['fields']));
            }
            throw new \Exception($data["message"]);
        } elseif ($httpCode != 200) {
            throw new \Exception($this->processResponse($response)['message']);
        }


        if ($this->results === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Bad response received from API' . $response);
        }

        return $this->results;
    }

    /**
     * Process API response.
     *
     * @param string $response
     *
     * @return array
     */
    public function processResponse($response)
    {
        return json_decode($response, true);
    }

    /**
     * Get from response results.
     *
     * @param string $key
     *
     * @return string
     */
    public function getFromResponse($key)
    {
        return isset($this->results[$key]) ? $this->results[$key] : '';
    }
}