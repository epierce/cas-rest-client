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
$client->setCredentials();

$client->login('/tmp/cas_tgt.json');

$response = $client->get("https://netid.usf.edu/vip/services/ws_convert.php?submit_type=netid&return_type=mail&return=json&value=epierce");

print_r($response->json())."\n";

$client->logout();