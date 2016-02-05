<?php
namespace epierce;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

class CasRestClientTest extends \PHPUnit_Framework_TestCase {

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage CAS server and credentials must be set before calling login()
     */
    public function testRequireCasServer() {
        $client = new CasRestClient();

        $client->login();
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage CAS server and credentials must be set before calling login()
     */
    public function testRequireCasCredentials() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');

        $client->login();
    }

    /**
     *
     */
    public function testBadCredentials() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user', 'blah');

        // Create a mock subscriber and response.
        $mock = new MockHandler([
            new Response(400, [], "HTTP/1.1 400 Bad Request\r\n\Content-Length: 0\r\n\r\n")
        ]);

        $handler = HandlerStack::create($mock);

        // Add the mock subscriber to the client.
        $client->setGuzzleClient(new Client(['base_uri' => 'https://example.org', 'handler' => $handler]));
        $this->assertFalse($client->login());
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessageRegExp /Client error.*Not Found/
     */
    public function testBadUrl() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user', 'blah');

        // Create a mock subscriber and response.
        $mock = new MockHandler([
            new Response(404, [], "HTTP/1.1 404 Not Found\r\n\Content-Length: 0\r\n\r\n")
        ]);

        $handler = HandlerStack::create($mock);

        // Add the mock subscriber to the client.
        $client->setGuzzleClient(new Client(['base_uri' => 'https://example.org', 'handler' => $handler]));

        $this->assertFalse($client->login());
    }

    public function testGoodCredentials() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user', 'secret');

        // Create a mock subscriber and response.
        $mock = new MockHandler([
            new Response(201, ['Location' => 'https://example.org/cas/v1/tickets/TGT-1-1qaz2wsx3edc'])
        ]);

        $handler = HandlerStack::create($mock);

        // Add the mock subscriber to the client.
        $client->setGuzzleClient(new Client(['base_uri' => 'https://example.org', 'handler' => $handler]));

        $this->assertTrue($client->login());
    }

    public function testLogout() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user', 'secret');

        // Create a mock subscriber and response.
        $mock = new MockHandler([
            new Response(201, ['Location' => 'https://example.org/cas/v1/tickets/TGT-1-1qaz2wsx3edc']),
            new Response(200, [])
        ]);

        $handler = HandlerStack::create($mock);

        // Add the mock subscriber to the client.
        $client->setGuzzleClient(new Client(['base_uri' => 'https://example.org', 'handler' => $handler]));

        $client->login();

        $this->assertTrue($client->logout());
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage You must login or provide a valid TGT
     */
    public function testFailedLogout() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user', 'secret');

        // Create a mock subscriber and response.
        $mock = new MockHandler([
            new Response(200, [])
        ]);

        $handler = HandlerStack::create($mock);

        // Add the mock subscriber to the client.
        $client->setGuzzleClient(new Client(['base_uri' => 'https://example.org', 'handler' => $handler]));

        $this->assertTrue($client->logout());
    }

    public function testGetTGT() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user', 'secret');

        // Create a mock subscriber and response.
        $mock = new MockHandler([
            new Response(201, ['Location' => 'https://example.org/cas/v1/tickets/TGT-1-1qaz2wsx3edc'])
        ]);

        $handler = HandlerStack::create($mock);

        // Add the mock subscriber to the client.
        $client->setGuzzleClient(new Client(['base_uri' => 'https://example.org', 'handler' => $handler]));
        $client->login();

        $this->assertEquals('TGT-1-1qaz2wsx3edc', $client->getTGT());
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage You must login or provide a valid TGT
     */
    public function testFailedGetService() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user', 'secret');

        // Create a mock subscriber and response.
        $mock = new MockHandler([
            new Response(200, [])
        ]);

        $handler = HandlerStack::create($mock);

        // Add the mock subscriber to the client.
        $client->setGuzzleClient(new Client(['base_uri' => 'https://example.org', 'handler' => $handler]));

        $this->assertTrue($client->get('https://www.example.com'));
    }

    public function testServiceGet()
    {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user', 'secret');

        // Create a mock subscriber and response.
        $mock = new MockHandler([
            new Response(201, ['Location' => 'https://example.org/cas/v1/tickets/TGT-1-1qaz2wsx3edc']),
            new Response(201, [], 'ST-1-abc123'),
            new Response(200, [], 'test..1..2..3')
        ]);

        $handler = HandlerStack::create($mock);

        // Add the mock subscriber to the client.
        $client->setGuzzleClient(new Client(['base_uri' => 'https://example.org', 'handler' => $handler]));
        $client->login();

        $this->assertEquals( (string) $client->get('https://www.example.com?param1=true')->getBody(), 'test..1..2..3');
    }

}
