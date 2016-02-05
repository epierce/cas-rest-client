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
    private $version = '0.2.1';
    /**
     * @var Client Guzzle REST client
     */
    private $gozzleClient;
    /**
     * @var bool Verify SSL certificate or not
     */
    private $verifySSL = true;
    /**
     * @var string CAS server URL (https://host)
     */
    private $casServer;
    /**
     * @var string CAS server path (default: /cas/v1/tickets)
     */
    private $casRESTcontext = '/cas/v1/tickets';
    /**
     * @var string Username for accessing CAS-protected resources
     */
    private $casUsername;
    /**
     * @var string Password for accessing CAS-protected resources
     */
    private $casPassword;
    /**
     * @var string Ticket-Granting Ticket
     */
    private $tgt;
    /**
     * @var string URL for the TGT on the CAS server
     */
    private $tgtLocation;
    /**
     * @var string File that TGT data will be stored in
     */
    private $tgtStorageLocation;


    /**
     *  Class constructor.
     */
    public function __construct()
    {
        $this->gozzleClient = new Client();
    }

    /**
     *  Set the CAS server URL
     *
     * @param string $server
     */
    public function setCasServer($server)
    {
        $this->casServer = $server;
    }

    /**
     * Set the username and password for accessing CAS services
     *
     * @param string $username
     * @param string $password
     */
    public function setCredentials($username, $password)
    {
        $this->casUsername = $username;
        $this->casPassword = $password;
    }

    /**
     * Set the starting path for CAS REST requests.
     *
     * @param string $context
     */
    public function setCasRestContext($context)
    {
        $this->casRESTcontext = $context;
    }

    /**
     * Verify the SSL certificate of the CAS server
     *
     * @param bool $value
     */
    public function verifySSL($value = true)
    {
        $this->verifySSL = $value;
    }

    /**
     * Return the Guzzle HTTP client
     *
     * @return Client
     */
    public function getGuzzleClient()
    {
        return $this->gozzleClient;
    }

    /**
     * Replace the Guzzle HTTP client
     *
     * @param Client $client
     */
    public function setGuzzleClient(Client $client)
    {
        $this->gozzleClient = $client;
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
        $this->tgtLocation = $this->casServer . $this->casRESTcontext . '/' . $tgt;
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

        $this->gozzleClient->delete($this->tgtLocation);
        $this->tgtLocation = null;
        $this->tgt = null;

        // Remove the TGT storage file
        if ($this->tgtStorageLocation) {
            unlink($this->tgtStorageLocation);
        }

        return true;
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
        if (empty($this->tgtLocation)) {
            throw new \Exception('You must login or provide a valid TGT', 400);
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

        $serviceTicket = $this->getServiceTicket($service);

        // Append the ticket to the end of the service's parameters
        if (strpos($service, '?') === false) {
            $finalService = $service . '?ticket=' . $serviceTicket;
        } else {
            $finalService = $service . '&ticket=' . $serviceTicket;
        }

        $options = [
            'cookies' => true,
            'body' => $body,
            'headers' => $this->setGuzzleHeaders($headers)
        ];

        switch ($method) {
            case 'GET':
                $result = $this->gozzleClient->get($finalService, $options);
                break;

            case 'HEAD':
                $result = $this->gozzleClient->head($finalService, $options);
                break;

            case 'POST':
                $result = $this->gozzleClient->post($finalService, $options);
                break;

            case 'PUT':
                $result = $this->gozzleClient->put($finalService, $options);
                break;

            case 'PATCH':
                $result = $this->gozzleClient->patch($finalService, $options);
                break;

            case 'DELETE':
                $result = $this->gozzleClient->delete($finalService, $options);
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
        $request = $this->gozzleClient->createRequest(
            'POST',
            $this->tgtLocation,
            [
                'verify' => $this->verifySSL,
                'body' => [
                    'service' => $service
                ],
                'headers' => $this->setGuzzleHeaders([])
            ]
        );

        try {
            $response = $this->gozzleClient->send($request);
            return $response->getBody();
        } catch (ClientException $e) {
            // Bad TGT - login again
            if ($e->getCode() == 404) {
                // Force authentication and save the TGT
                $this->login($this->tgtStorageLocation, true);
                return $this->getServiceTicket($service);
                // Unsupported Media Type
            } elseif ($e->getCode() == 415) {
                return false;
            } else {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Validate credentials against the CAS server and retrieve a Ticket-Granting Ticket.  If a tgtStorageLocation is
     * specified, the fle is read and the saved TGT is used instead of validating credentials.  If force_auth is TRUE,
     * always validate credentials.
     *
     * @param string $tgtStorageLocation
     * @param bool $forceAuth
     * @return bool
     * @throws \Exception
     */
    public function login($tgtStorageLocation = '', $forceAuth = false)
    {

        if ((!$this->casServer) || (!$this->casPassword) || (!$this->casUsername)) {
            throw new \Exception('CAS server and credentials must be set before calling login()', 1);
        }

        $this->tgtStorageLocation = $tgtStorageLocation;

        // Try to load the TGT from the storage file
        if (!$forceAuth && $tgtStorageLocation) {
            if (file_exists($tgtStorageLocation)) {
                if (is_readable($tgtStorageLocation)) {
                    $this->loadTGTfromFile($tgtStorageLocation);
                    return true;
                } else {
                    throw new \Exception('TGT storage file [' . $tgtStorageLocation . '] is not readable!', 500);
                }
            }
        }

        $request = $this->gozzleClient->createRequest(
            'POST',
            $this->casServer . $this->casRESTcontext,
            [
                'verify' => $this->verifySSL,
                'body' => [
                    'username' => $this->casUsername,
                    'password' => $this->casPassword
                ],
                'headers' => $this->setGuzzleHeaders([])
            ]
        );

        try {
            $response = $this->gozzleClient->send($request);
            $responseHeaders = $response->getHeaders();
        } catch (ClientException $e) {
            // Bad username or password.
            if ($e->getCode() == 400) {
                return false;
                // Unsupported Media Type
            } elseif ($e->getCode() == 415) {
                return false;
            } else {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        if (isset($responseHeaders['Location'][0])) {
            $this->tgtLocation = $responseHeaders['Location'][0];
            $this->tgt = substr(strrchr($this->tgtLocation, '/'), 1);
        }

        // Save the TGT to a storage file.
        if ($tgtStorageLocation) {
            $this->writeTGTtoFile($tgtStorageLocation, $this->tgt);
        }

        return true;
    }

    /**
     * Read the TGT data from a file
     *
     * @param string $tgtStorageLocation
     * @throws \Exception
     */
    private function loadTGTfromFile($tgtStorageLocation)
    {
        $tgtStorageData = json_decode(file_get_contents($tgtStorageLocation), true);

        if ($tgtStorageData['username']) {
            $this->casUsername = $tgtStorageData['username'];
        } else {
            throw new \Exception('TGT storage missing "username" value!', 551);
        }
        if ($tgtStorageData['server']) {
            $this->casServer = $tgtStorageData['server'];
        } else {
            throw new \Exception('TGT storage missing "server" value!', 552);
        }
        if ($tgtStorageData['context']) {
            $this->casRESTcontext = $tgtStorageData['context'];
        } else {
            throw new \Exception('TGT storage missing "context" value!', 552);
        }
        if ($tgtStorageData['TGT']) {
            $this->tgt = $tgtStorageData['TGT'];
            $this->tgtLocation = $this->casServer . $this->casRESTcontext . '/' . $this->tgt;
        } else {
            throw new \Exception('TGT storage missing "TGT" value!', 552);
        }
    }

    /**
     * Save the TGT data to a local file
     *
     * @param string $tgtStorageLocation
     * @param string $tgt
     */
    private function writeTGTtoFile($tgtStorageLocation, $tgt)
    {
        $tgtStorageData = [
            'TGT' => $tgt,
            'username' => $this->casUsername,
            'server' => $this->casServer,
            'context' => $this->casRESTcontext,
            'saved' => time()
        ];

        file_put_contents($tgtStorageLocation, json_encode($tgtStorageData));
    }

    /**
     * Combine the custom headers with class defaults
     *
     * @param array $customHeaders
     * @return array
     */
    private function setGuzzleHeaders(array $customHeaders)
    {

        $defaultHeaders = [
            'User-Agent' => 'PHP/CasRestClient/' . $this->version.'/'.$this->gozzleClient->getDefaultUserAgent(),
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip, deflate'
        ];

        return array_merge($defaultHeaders, $customHeaders);
    }
}
