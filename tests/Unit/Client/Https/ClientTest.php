<?php

namespace Reliv\Git\Tests\Unit\Client\Https;

require_once __DIR__.'/../../../../vendor/autoload.php';

use Reliv\Git\Client\Https\Client;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Reliv\Git\Client\Https\Client */
    protected $client;

    public function setup()
    {
        $this->client = new Client('https://github.com/reliv/RelivSkeletonApplication.git', 'wshafer', 'Y3st3erDay');
    }

    public function testConstructor()
    {
        $this->assertTrue($this->client instanceof Client);
    }


    public function testFetchInfo()
    {
        $this->client->getPacketFile();
    }
}