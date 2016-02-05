cas-rest-client
=============

PHP client for the [CAS REST protocol](http://jasig.github.io/cas/4.0.x/protocol/REST-Protocol.html)

System Requirements
-------

You need:

- **PHP >= 5.5.0**, but the latest stable version of PHP is recommended
- Composer

Install
-------

Install `cas-rest-client` using Composer.

```
$ composer require epierce/cas-rest-client
```

Example Usage
-------
```php
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

// Make another call using the same CAS session
$headers = ['User-Agent' => 'testing/1.0'];
$post_params = ['param1' => 'foo', param2 => 'bar'];
$response = $client->post("https://someotherservice", $headers, '', $post_params);
print_r(json_decode($response->getBody(), true));

$client->logout();

?>
```
