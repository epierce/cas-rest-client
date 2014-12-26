<?php
/**
 * Created by PhpStorm.
 * User: epierce
 * Date: 12/19/14
 * Time: 9:18 AM
 */
namespace epierce;

require_once('vendor/autoload.php');

$client = new CasRestClient();

$client->setCasServer('https://webauth.usf.edu');
$client->setCasRestContext('/v1/tickets');
$client->setCredentials("username","password");

$client->login('/tmp/cas_tgt.json');

$response = $client->get("https://someservice");

print_r($response->json())."\n";

$client->logout();