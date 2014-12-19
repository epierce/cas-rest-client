<?php
namespace epierce;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class CasRestClient {

    private $guzzle_client;
    private $verify_ssl = TRUE;
    private $ca_bundle_path;
    private $cas_server;
    private $cas_ticket_url = '/cas/v1/tickets';
    private $cas_username;
    private $cas_password;
    
    private $tgt_location;

    public function __construct()
    {
        $this->guzzle_client = new Client();
    }

    public function setCasServer($server){
        $this->cas_server = $server;
    }

    public function setCredentials($username, $password){
        $this->cas_username = $username;
        $this->cas_password = $password;
    }

    public function setTicketUrl($ticket_url){
        $this->cas_ticket_url = $ticket_url;
    }

    public function verifySSL($value = TRUE){
        $this->verify_ssl = $value;
    }

    public function getGuzzleClient(){
        return $this->guzzle_client;
    }

    public function getTGT()
    {
        // Make sure a TGT exists
        $this->checkTgtExists();

        return substr(strrchr($this->tgt_location, '/'), 1);
    }

    public function login(){

        if ((! $this->cas_server) || (! $this->cas_password) || (! $this->cas_username)) {
            throw new \Exception ('CAS server and credentials must be set before calling login()', 1);
        }

        $request = $this->guzzle_client->createRequest( 'POST',
            $this->cas_server.$this->cas_ticket_url,
            [
                'verify' => $this->verify_ssl,
                'body' => [
                    'username' => $this->cas_username,
                    'password' => $this->cas_password
                ]
            ]);

        try {
            $response = $this->guzzle_client->send ($request);
            $response_headers = $response->getHeaders ();
        } catch (ClientException $e) {
            // Bad username or password.
            if ($e->getCode() == 400) {
                return FALSE;
            // Unsupported Media Type
            } elseif($e->getCode() == 415) {
                return FALSE;
            } else {
                throw new \Exception ($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new \Exception ($e->getMessage(), $e->getCode());
        }

        if (isset($response_headers['Location'][0])){
            $this->tgt_location = $response_headers['Location'][0];
        }

        return TRUE;
    }

    public function logout(){
        // Make sure a TGT exists
        $this->checkTgtExists();

        $this->guzzle_client->delete($this->tgt_location);
        $this->tgt_location = NULL;

        return TRUE;
    }

    public function get($service){
        // Make sure a TGT exists
        $this->checkTgtExists();

        $service_ticket = $this->getServiceTicket($service);

        // Append the ticket to the end of the service's parameters
        if (strpos($service,'?') === false) {
            $final_service = $service . '?ticket=' . $service_ticket;
        } else {
            $final_service = $service . '&ticket=' . $service_ticket;
        }
        // Now call the service
        return $this->guzzle_client->get($final_service,['cookies'=> TRUE]);

    }

    public function post($service, $fields = []){
        // Make sure a TGT exists
        $this->checkTgtExists();

        $service_ticket = $this->getServiceTicket($service);

        // Append the ticket to the end of the service's parameters
        if (strpos($service,'?') === false) {
            $final_service = $service . '?ticket=' . $service_ticket;
        } else {
            $final_service = $service . '&ticket=' . $service_ticket;
        }

        // Now call the service
        return $this->guzzle_client->post($final_service, [body => $fields]);

    }

    private function checkTgtExists() {
        if (empty($this->tgt_location)) {
            throw new \Exception ('You must use the login method before calling logout', 400);
        }
    }

    //TODO: Handle expired TGTs - login again
    private function getServiceTicket($service){
        $request = $this->guzzle_client->createRequest( 'POST',
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
            // Bad username or password.
            if ($e->getCode() == 400) {
                return FALSE;
                // Unsupported Media Type
            } elseif($e->getCode() == 415) {
                return FALSE;
            } else {
                throw new \Exception ($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new \Exception ($e->getMessage(), $e->getCode());
        }
    }
}