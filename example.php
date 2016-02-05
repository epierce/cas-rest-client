<?php
namespace epierce;

require_once('vendor/autoload.php');

$client = new CasRestClient();

$client->setCasServer('https://webauth.usf.edu');
$client->setCasRestContext('/v1/tickets');
$client->setCredentials("username","password");

$client->login('/tmp/cas_tgt.json');

$response = $client->get("https://someservice");

print_r(json_decode($response->getBody(), true));

$client->logout();