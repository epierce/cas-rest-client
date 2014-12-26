<?php
namespace epierce;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Class CasRestClient
 * @package epierce
 */
class CasRestClient
{

    /**
     * @var string version
     */
    private $version = '0.2.0';
    /**
     * @var Client Guzzle REST client
     */
    private $guzzle_client;
    /**
     * @var bool Verify SSL certificate or not
     */
    private $verify_ssl = TRUE;
    /**
     * @var string CAS server URL (https://host)
     */
    private $cas_server;
    /**
     * @var string CAS server path (default: /cas/v1/tickets)
     */
    private $cas_rest_context = '/cas/v1/tickets';
    /**
     * @var string Username for accessing CAS-protected resources
     */
    private $cas_username;
    /**
     * @var string Password for accessing CAS-protected resources
     */
    private $cas_password;
    /**
     * @var string Ticket-Granting Ticket
     */
    private $tgt;
    /**
     * @var string URL for the TGT on the CAS server
     */
    private $tgt_location;
    /**
     * @var string File that TGT data will be stored in
     */
    private $tgt_storage_location;


    /**
     *  Class constructor.
     */
    public function __construct()
    {
        $this->guzzle_client = new Client();
    }

    /**
     *  Set the CAS server URL
     *
     * @param string $server
     */
    public function setCasServer($server)
    {
        $this->cas_server = $server;
    }

    /**
     * Set the username and password for accessing CAS services
     *
     * @param string $username
     * @param string $password
     */
    public function setCredentials($username, $password)
    {
        $this->cas_username = $username;
        $this->cas_password = $password;
    }

    /**
     * Set the starting path for CAS REST requests.
     *
     * @param string $context
     */
    public function setCasRestContext($context)
    {
        $this->cas_rest_context = $context;
    }

    /**
     * Verify the SSL certificate of the CAS server
     *
     * @param bool $value
     */
    public function verifySSL($value = TRUE)
    {
        $this->verify_ssl = $value;
    }

    /**
     * Return the Guzzle HTTP client
     *
     * @return Client
     */
    public function getGuzzleClient()
    {
        return $this->guzzle_client;
    }

    /**
     * Returns the Ticket-granting Ticket or NULL if one is not set.
     *
     * @return mixed
     */
    public function getTGT()
    {
        return $this->tgt;
    }

    /**
     * Accepts the Ticket-Granting Ticket from the app running the client.
     *
     * @param string $tgt
     */
    public function setTGT($tgt)
    {
        $this->tgt = $tgt;
        $this->tgt_location = $this->cas_server . $this->cas_rest_context . '/' . $tgt;
    }

    /**
     * Logout of the CAS session and destroy the TGT.
     *
     * @return bool
     * @throws \Exception
     */
    public function logout()
    {
        // Make sure a TGT exists
        $this->checkTgtExists();

        $this->guzzle_client->delete($this->tgt_location);
        $this->tgt_location = NULL;
        $this->tgt = NULL;

        // Remove the TGT storage file
        if ($this->tgt_storage_location) unlink($this->tgt_storage_location);

        return TRUE;
    }

    /**
     * Request a Service Ticket for the CAS server and perform a HTTP GET operation.
     *
     * @param $service
     * @param array $headers
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    public function get($service, $headers = [], $body = [])
    {
        return $this->callRestService('GET', $service, $headers, $body);
    }

    /**
     * Request a Service Ticket for the CAS server and perform a HTTP POST operation.
     *
     * @param $service
     * @param array $headers
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    public function post($service, $headers = [], $body = [])
    {
        return $this->callRestService('POST', $service, $headers, $body);
    }

    /**
     * Request a Service Ticket for the CAS server and perform a HTTP PATCH operation.
     *
     * @param $service
     * @param array $headers
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    public function patch($service, $headers = [], $body = [])
    {
        return $this->callRestService('PATCH', $service, $headers, $body);
    }

    /**
     * Request a Service Ticket for the CAS server and perform a HTTP HEAD operation.
     *
     * @param $service
     * @param array $headers
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    public function head($service, $headers = [], $body = [])
    {
        return $this->callRestService('HEAD', $service, $headers, $body);
    }

    /**
     * Request a Service Ticket for the CAS server and perform a HTTP PUT operation.
     *
     * @param $service
     * @param array $headers
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    public function put($service, $headers = [], $body = [])
    {
        return $this->callRestService('PUT', $service, $headers, $body);
    }

    /**
     * Request a Service Ticket for the CAS server and perform a HTTP OPTIONS operation.
     *
     * @param $service
     * @param array $headers
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    public function options($service, $headers = [], $body = [])
    {
        return $this->callRestService('OPTIONS', $service, $headers, $body);
    }

    /**
     * Request a Service Ticket for the CAS server and perform a HTTP DELETE operation.
     *
     * @param $service
     * @param array $headers
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    public function delete($service, $headers = [], $body = [])
    {
        return $this->callRestService('DELETE', $service, $headers, $body);
    }

    /**
     * Verifies a Ticket-Granting Ticket exists before proceeding
     *
     * @throws \Exception
     */
    private function checkTgtExists()
    {
        if (empty($this->tgt_location)) {
            throw new \Exception ('You must login or provide a valid TGT', 400);
        }
    }

    /**
     * Set up and execute a REST request.
     *
     * @param $method
     * @param $service
     * @param array $headers
     * @param array $body
     * @return mixed|null
     * @throws \Exception
     */
    private function callRestService($method, $service, $headers = [], $body = [])
    {
        // Make sure a TGT exists
        $this->checkTgtExists();

        $service_ticket = $this->getServiceTicket($service);

        // Append the ticket to the end of the service's parameters
        if (strpos($service, '?') === false) {
            $final_service = $service . '?ticket=' . $service_ticket;
        } else {
            $final_service = $service . '&ticket=' . $service_ticket;
        }

        $options = [
            'cookies' => TRUE,
            'body' => $body,
            'headers' => $this->setGuzzleHeaders($headers)
        ];

        switch ($method) {
            case 'GET':
                $result = $this->guzzle_client->get($final_service, $options);
                break;

            case 'HEAD':
                $result = $this->guzzle_client->head($final_service, $options);
                break;

            case 'POST':
                $result = $this->guzzle_client->post($final_service, $options);
                break;

            case 'PUT':
                $result = $this->guzzle_client->put($final_service, $options);
                break;

            case 'PATCH':
                $result = $this->guzzle_client->patch($final_service, $options);
                break;

            case 'DELETE':
                $result = $this->guzzle_client->delete($final_service, $options);
                break;

            default:
                throw new \Exception('Unsupported HTTP method: ' . $method, 500);
        }

        return $result;
    }

    /**
     * Request Service ticket from CAS server
     *
     * @param $service
     * @return bool|\GuzzleHttp\Stream\StreamInterface|null
     * @throws \Exception
     */
    private function getServiceTicket($service)
    {
        $request = $this->guzzle_client->createRequest('POST',
            $this->tgt_location,
            [
                'verify' => $this->verify_ssl,
                'body' => [
                    'service' => $service
                ],
                'headers' => $this->setGuzzleHeaders([])
            ]);

        try {
            $response = $this->guzzle_client->send($request);
            return $response->getBody();
        } catch (ClientException $e) {
            // Bad TGT - login again
            if ($e->getCode() == 404) {
                // Force authentication and save the TGT
                $this->login($this->tgt_storage_location, TRUE);
                return $this->getServiceTicket($service);
                // Unsupported Media Type
            } elseif ($e->getCode() == 415) {
                return FALSE;
            } else {
                throw new \Exception ($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new \Exception ($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Validate credentials against the CAS server and retrieve a Ticket-Granting Ticket.  If a tgt_storage_location is
     * specified, the fle is read and the saved TGT is used instead of validating credentials.  If force_auth is TRUE,
     * always validate credentials.
     *
     * @param string $tgt_storage_location
     * @param bool $force_auth
     * @return bool
     * @throws \Exception
     */
    public function login($tgt_storage_location = '', $force_auth = FALSE)
    {

        if ((!$this->cas_server) || (!$this->cas_password) || (!$this->cas_username)) {
            throw new \Exception ('CAS server and credentials must be set before calling login()', 1);
        }

        $this->tgt_storage_location = $tgt_storage_location;

        // Try to load the TGT from the storage file
        if (!$force_auth && $tgt_storage_location) {
            if (file_exists($tgt_storage_location)) {
                if (is_readable($tgt_storage_location)) {
                    $this->loadTGTfromFile($tgt_storage_location);
                    return TRUE;
                } else {
                    throw new \Exception('TGT storage file [' . $tgt_storage_location . '] is not readable!', 500);
                }
            }
        }

        $request = $this->guzzle_client->createRequest('POST',
            $this->cas_server . $this->cas_rest_context,
            [
                'verify' => $this->verify_ssl,
                'body' => [
                    'username' => $this->cas_username,
                    'password' => $this->cas_password
                ],
                'headers' => $this->setGuzzleHeaders([])
            ]);

        try {
            $response = $this->guzzle_client->send($request);
            $response_headers = $response->getHeaders();
        } catch (ClientException $e) {
            // Bad username or password.
            if ($e->getCode() == 400) {
                return FALSE;
                // Unsupported Media Type
            } elseif ($e->getCode() == 415) {
                return FALSE;
            } else {
                throw new \Exception ($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new \Exception ($e->getMessage(), $e->getCode());
        }

        if (isset($response_headers['Location'][0])) {
            $this->tgt_location = $response_headers['Location'][0];
            $this->tgt = substr(strrchr($this->tgt_location, '/'), 1);
        }

        // Save the TGT to a storage file.
        if ($tgt_storage_location) {
            $this->writeTGTtoFile($tgt_storage_location, $this->tgt);
        }

        return TRUE;
    }

    /**
     * Read the TGT data from a file
     *
     * @param string $tgt_storage_location
     * @throws \Exception
     */
    private function loadTGTfromFile($tgt_storage_location)
    {
        $tgt_storage_data = json_decode(file_get_contents($tgt_storage_location), true);

        if ($tgt_storage_data['username']) {
            $this->cas_username = $tgt_storage_data['username'];
        } else {
            throw new \Exception ('TGT storage missing "username" value!', 551);
        }
        if ($tgt_storage_data['server']) {
            $this->cas_server = $tgt_storage_data['server'];
        } else {
            throw new \Exception ('TGT storage missing "server" value!', 552);
        }
        if ($tgt_storage_data['context']) {
            $this->cas_rest_context = $tgt_storage_data['context'];
        } else {
            throw new \Exception ('TGT storage missing "context" value!', 552);
        }
        if ($tgt_storage_data['TGT']) {
            $this->tgt = $tgt_storage_data['TGT'];
            $this->tgt_location = $this->cas_server . $this->cas_rest_context . '/' . $this->tgt;
        } else {
            throw new \Exception ('TGT storage missing "TGT" value!', 552);
        }
    }

    /**
     * Save the TGT data to a local file
     *
     * @param string $tgt_storage_location
     * @param string $tgt
     */
    private function writeTGTtoFile($tgt_storage_location, $tgt)
    {
        $tgt_storage_data = [
            'TGT' => $tgt,
            'username' => $this->cas_username,
            'server' => $this->cas_server,
            'context' => $this->cas_rest_context,
            'saved' => time()
        ];

        file_put_contents($tgt_storage_location, json_encode($tgt_storage_data));
    }

    /**
     * Combine the custom headers with class defaults
     *
     * @param array $custom_headers
     * @return array
     */
    private function setGuzzleHeaders(array $custom_headers)
    {

        $default_headers = [
            'User-Agent' => 'PHP/CasRestClient/' . $this->version.'/'.$this->guzzle_client->getDefaultUserAgent(),
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip, deflate'
        ];

        return array_merge($default_headers, $custom_headers);
    }
}