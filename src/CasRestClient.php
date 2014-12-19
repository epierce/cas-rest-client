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
     * @var Client
     */
    private $guzzle_client;
    /**
     * @var bool
     */
    private $verify_ssl = TRUE;
    /**
     * @var
     */
    private $cas_server;
    /**
     * @var string
     */
    private $cas_ticket_url = '/cas/v1/tickets';
    /**
     * @var
     */
    private $cas_username;
    /**
     * @var
     */
    private $cas_password;
    /**
     * @var
     */
    private $tgt_storage_location;
    /**
     * @var
     */
    private $tgt_location;
    /**
     * @var
     */
    private $tgt;

    /**
     *
     */
    public function __construct()
    {
        $this->guzzle_client = new Client();
    }

    /**
     * @param $server
     */
    public function setCasServer($server)
    {
        $this->cas_server = $server;
    }

    /**
     * @param $username
     * @param $password
     */
    public function setCredentials($username, $password)
    {
        $this->cas_username = $username;
        $this->cas_password = $password;
    }

    /**
     * @param $ticket_url
     */
    public function setTicketUrl($ticket_url)
    {
        $this->cas_ticket_url = $ticket_url;
    }

    /**
     * @param bool $value
     */
    public function verifySSL($value = TRUE)
    {
        $this->verify_ssl = $value;
    }

    /**
     * @return Client
     */
    public function getGuzzleClient()
    {
        return $this->guzzle_client;
    }

    /**
     * @return mixed
     */
    public function getTGT()
    {
        return $this->tgt;
    }

    /**
     * @param $tgt
     */
    public function setTGT($tgt)
    {
        $this->tgt = $tgt;
        $this->tgt_location = $this->cas_server . $this->cas_ticket_url . '/' . $tgt;
    }

    /**
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
                    throw new Exception('TGT storage file [' . $tgt_storage_location . '] is not readable!', 500);
                }
            }
        }

        $request = $this->guzzle_client->createRequest('POST',
            $this->cas_server . $this->cas_ticket_url,
            [
                'verify' => $this->verify_ssl,
                'body' => [
                    'username' => $this->cas_username,
                    'password' => $this->cas_password
                ]
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
     * @param $service
     * @param array $headers
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     * @throws \Exception
     */
    public function get($service, $headers = [])
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
            'headers' => $headers
        ];
        // Now call the service
        return $this->guzzle_client->get($final_service, $options);

    }

    /**
     * @param $service
     * @param array $body
     * @param array $headers
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     * @throws \Exception
     */
    public function post($service, $body = [], $headers = [])
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
            'headers' => $headers
        ];

        // Now call the service
        return $this->guzzle_client->post($final_service, $options);

    }

    /**
     * @param $service
     * @param array $body
     * @param array $headers
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     * @throws \Exception
     */
    public function put($service, $body = [], $headers = [])
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
            'headers' => $headers
        ];

        // Now call the service
        return $this->guzzle_client->put($final_service, $options);

    }

    /**
     * @param $service
     * @param array $body
     * @param array $headers
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     * @throws \Exception
     */
    public function patch($service, $body = [], $headers = [])
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
            'headers' => $headers
        ];

        // Now call the service
        return $this->guzzle_client->patch($final_service, $options);

    }

    /**
     * @param $service
     * @param array $body
     * @param array $headers
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     * @throws \Exception
     */
    public function options($service, $body = [], $headers = [])
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
            'headers' => $headers
        ];

        // Now call the service
        return $this->guzzle_client->options($final_service, $options);

    }

    /**
     * @param $service
     * @param array $headers
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     * @throws \Exception
     */
    public function delete($service, $headers = [])
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
            'headers' => $headers
        ];

        // Now call the service
        return $this->guzzle_client->delete($final_service, $options);

    }

    /**
     * @param $service
     * @param array $headers
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     * @throws \Exception
     */
    public function head($service, $headers = [])
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
            'headers' => $headers
        ];

        // Now call the service
        return $this->guzzle_client->head($final_service, $options);

    }

    /**
     * @throws \Exception
     */
    private function checkTgtExists()
    {
        if (empty($this->tgt_location)) {
            throw new \Exception ('You must login or provide a valid TGT', 400);
        }
    }

    /**
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
                ]
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
     * @param $tgt_storage_location
     * @param $tgt
     */
    private function writeTGTtoFile($tgt_storage_location, $tgt)
    {
        $tgt_storage_data = [
            'TGT' => $tgt,
            'username' => $this->cas_username,
            'server' => $this->cas_server,
            'context' => $this->cas_ticket_url,
            'saved' => time()
        ];

        file_put_contents($tgt_storage_location, json_encode($tgt_storage_data));
    }

    /**
     * @param $tgt_storage_location
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
            $this->cas_ticket_url = $tgt_storage_data['context'];
        } else {
            throw new \Exception ('TGT storage missing "context" value!', 552);
        }
        if ($tgt_storage_data['TGT']) {
            $this->tgt = $tgt_storage_data['TGT'];
            $this->tgt_location = $this->cas_server . $this->cas_ticket_url . '/' . $this->tgt;
        } else {
            throw new \Exception ('TGT storage missing "TGT" value!', 552);
        }
    }
}