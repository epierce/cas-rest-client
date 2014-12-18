<?php
namespace epierce;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class CasRestClient {

    private $guzzle_client;
    private $debug = FALSE;
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

    public function setDebug($value = TRUE)
    {
        $this->debug = $value;
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

    public function login(){

        if ((! $this->cas_server) || (! $this->cas_password) || (! $this->cas_username)) {
            throw new \Exception ('CAS server and credentials must be set before calling login()', 1);
        }

        $request = $this->guzzle_client->createRequest( 'POST',
                                                        $this->cas_server.$this->cas_ticket_url,
                                                        [
                                                            'cookies' => TRUE,
                                                            'verify' => $this->verify_ssl,
                                                            'body' => [
                                                                'username' => $this->cas_username,
                                                                'password' => $this->cas_password
                                                            ]
                                                        ]);

        try {
            $response = $this->guzzle_client->send ($request);
            $response_headers = $response->getHeaders ();
            $response_body = $response->getBody ();
        } catch (ClientException $e) {
            // Bad username or password.
            if ($e->getCode() == 400){
                if($this->debug) echo "Login Failed!  Bad username or password!\n";
                return FALSE;
            } else {
                throw new \Exception ($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new \Exception ($e->getMessage(), $e->getCode());
        }

        if($this->debug){
            echo "=======================\n";
            echo " login() \n";
            echo "=======================\n";
            echo "TGT Request: POST URL\n";
            echo $this->cas_server.$this->cas_ticket_url."\n";
            echo "TGT Request: POST body\n";
            print_r($request->getBody()->getFields());
            echo "\nTGT Response: Headers\n";
            print_r($response_headers);
            echo "\nTGT Response: Body\n";
            echo $response_body."\n";
            echo "\n=======================\n";
        }

        if (isset($response_headers['Location'])){
            $this->tgt_location;
        }


    }


}