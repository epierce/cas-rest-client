<?php

use epierce\CasRestClient;

require_once('vendor/autoload.php');

$client = new CasRestClient();

// Configure CAS client
$client->setCasServer('https://cas.exmaple.edu');
$client->setCasRestContext('/v1/tickets');
$client->setCredentials("username", "password");

// Login and save TGT to a file
$client->login('/tmp/cas_tgt.json');

// Make a webservice call
$response = $client->get("https://someservice");
print_r(json_decode($response->getBody(), true));

// Make another call using the same SSO session
$headers = ['User-Agent' => 'testing/1.0'];
$post_params = ['param1' => 'foo', param2 => 'bar'];
$response = $client->post("https://someotherservice", $headers, '', $post_params);
print_r(json_decode($response->getBody(), true));

$client->logout();
