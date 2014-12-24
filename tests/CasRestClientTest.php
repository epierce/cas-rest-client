<?php
namespace epierce;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;

require_once('../vendor/autoload.php');


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
        $client->setCredentials('user','blah');

        // Create a mock subscriber and response.
        $mock = new Mock([
            "HTTP/1.1 400 Bad Request\r\n\Content-Length: 0r\n\r\n"
        ]);

        // Add the mock subscriber to the client.
        $client->getGuzzleClient()->getEmitter()->attach($mock);

        $this->assertFalse($client->login());
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessageRegExp /Client error.*Not Found/
     */
    public function testBadUrl() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user','blah');

        // Create a mock subscriber and response.
        $mock = new Mock([
            "HTTP/1.1 404 Not Found\r\n\Content-Length: 0r\n\r\n"
        ]);

        // Add the mock subscriber to the client.
        $client->getGuzzleClient()->getEmitter()->attach($mock);

        $this->assertFalse($client->login());
    }

    /**
     *
     */
    public function testGoodCredentials() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user','secret');

        // Create a mock subscriber and response.
        $mock = new Mock([
            new Response(201, ['Location' => 'https://example.org/cas/v1/tickets/TGT-1-1qaz2wsx3edc'])
        ]);

        // Add the mock subscriber to the client.
        $client->getGuzzleClient()->getEmitter()->attach($mock);

        $this->assertTrue($client->login());
    }

    /**
     *
     */
    public function testLogout() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user','secret');

        // Create a mock subscriber and response.
        $mock = new Mock([
            new Response(201, ['Location' => 'https://example.org/cas/v1/tickets/TGT-1-1qaz2wsx3edc']),
            new Response(200, [])
        ]);

        // Add the mock subscriber to the client.
        $client->getGuzzleClient()->getEmitter()->attach($mock);
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
        $client->setCredentials('user','secret');

        // Create a mock subscriber and response.
        $mock = new Mock([
            new Response(200, [])
        ]);

        // Add the mock subscriber to the client.
        $client->getGuzzleClient()->getEmitter()->attach($mock);

        $this->assertTrue($client->logout());
    }

    /**
     *
     */
    public function testGetTGT() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user','secret');

        // Create a mock subscriber and response.
        $mock = new Mock([
            new Response(201, ['Location' => 'https://example.org/cas/v1/tickets/TGT-1-1qaz2wsx3edc'])
        ]);

        // Add the mock subscriber to the client.
        $client->getGuzzleClient()->getEmitter()->attach($mock);
        $client->login();

        $this->assertEquals('TGT-1-1qaz2wsx3edc',$client->getTGT());
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage You must login or provide a valid TGT
     */
    public function testFailedGetService() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user','secret');

        // Create a mock subscriber and response.
        $mock = new Mock([
            new Response(200, [])
        ]);

        // Add the mock subscriber to the client.
        $client->getGuzzleClient()->getEmitter()->attach($mock);

        $this->assertTrue($client->get('https://www.example.com'));
    }

    /**
     *
     */
    public function testServiceNoParameters() {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user','secret');

        // Create a mock subscriber and response.
        $mock = new Mock([
            new Response(201, ['Location' => 'https://example.org/cas/v1/tickets/TGT-1-1qaz2wsx3edc']),
            new Response(201, [],Stream::factory('ST-1-abc123')),
            new Response(200, [])
        ]);

        // Add the mock subscriber to the client.
        $client->getGuzzleClient()->getEmitter()->attach($mock);
        $client->login();

        $this->assertEquals($client->get('https://www.example.com')->getEffectiveUrl(), 'https://www.example.com?ticket=ST-1-abc123');
    }

    /**
     *
     */
    public function testServiceOneParameter()
    {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user', 'secret');

        // Create a mock subscriber and response.
        $mock = new Mock([
            new Response(201, ['Location' => 'https://example.org/cas/v1/tickets/TGT-1-1qaz2wsx3edc']),
            new Response(201, [], Stream::factory('ST-1-abc123')),
            new Response(200, [])
        ]);

        // Add the mock subscriber to the client.
        $client->getGuzzleClient()->getEmitter()->attach($mock);
        $client->login();

        $this->assertEquals($client->get('https://www.example.com?param1=true')->getEffectiveUrl(), 'https://www.example.com?param1=true&ticket=ST-1-abc123');
    }

    /**
     *
     */
    public function testServiceGet()
    {
        $client = new CasRestClient();
        $client->setCasServer('https://example.org');
        $client->setCredentials('user', 'secret');

        // Create a mock subscriber and response.
        $mock = new Mock([
            new Response(201, ['Location' => 'https://example.org/cas/v1/tickets/TGT-1-1qaz2wsx3edc']),
            new Response(201, [], Stream::factory('ST-1-abc123')),
            new Response(200, [], Stream::factory('test..1..2..3'))
        ]);

        // Add the mock subscriber to the client.
        $client->getGuzzleClient()->getEmitter()->attach($mock);
        $client->login();

        $this->assertEquals($client->get('https://www.example.com?param1=true')->getBody(), 'test..1..2..3');
    }

}