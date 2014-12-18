<?php
namespace epierce\cas;

use GuzzleHttp\Client;

class CasRestClient {

    private $guzzle_client;
    private $debug = FALSE;
    private $verify_ssl = TRUE;
    private $ca_bundle_path;
    private $cas_server;
    private $cas_ticket_url = '/cas/v1/tickets';
    private $cas_username;
    private $cas_password;

    public function __construct()
    {
        $this->guzzle_client = new Client();
    }

    public function setDebug(boolean $value = TRUE)
    {
        $this->debug = $value;
    }

    public function setCasServer(string $server){
        $this->cas_server = $server;
    }

    public function setCredentials(string $username, string $password){
        $this->cas_username = $username;
        $this->cas_password = $password;
    }

    public function setTicketUrl(string $ticket_url){
        $this->cas_ticket_url = $ticket_url;
    }

    public function verifySSL(boolean $value, string $ca_bundle_path = ''){
        $this->verify_ssl = $value;

        if (($ca_bundle_path) && (is_readable($ca_bundle_path))){
            $this->ca_bundle_path = $ca_bundle_path;
        }
    }

    public function login(){

        if ((! $this->cas_server) || (! $this->cas_password) || (! $this->cas_username)) {
            throw new \Exception ('CAS server and credentials must be set before calling login()', 1);
        }

        $request = $this->guzzle_client->createRequest('POST', $this->cas_server.$this->cas_ticket_url);

        $postBody = $request->getBody();
        $postBody->setField('username', $this->cas_username);
        $postBody->setField('password', $this->cas_password);

        $response = $this->guzzle_client->send($request);
        $response_headers = $response->getHeaders();
        $response_body = $response->getBody();

        if($this->debug){
            echo "=======================\n";
            echo " login() \n";
            echo "=======================\n";
            echo "TGT Request: POST URL\n";
            echo $this->cas_server.$this->cas_ticket_url."\n";
            echo "TGT Request: POST body\n";
            echo json_encode($postBody->getFields())."\n";
            echo "TGT Response: Headers\n";
            echo json_encode($response_headers)."\n";
            echo "TGT Response: Body\n";
            echo $response_body."\n";
            echo "\n=======================\n";
        }


    }


}