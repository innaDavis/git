<?php

namespace Reliv\Git\Client\Https;

use GuzzleHttp\Message\Response;
use Reliv\Git\Exception\InvalidResponseException;
use Reliv\Git\Exception\NoResponseException;
use Reliv\Git\Exception\NotAuthorizedException;
use Reliv\Git\Exception\NotFoundException;
use SebastianBergmann\Exporter\Exception;

class Client
{
    protected $userName;
    protected $password;
    protected $uri;

    public function __construct($uri, $userName, $password)
    {
        $this->userName = $userName;
        $this->password = $password;
        $this->uri = $uri;
    }

    public function fetchInfo()
    {
        $apiCommand = '/info/refs?service=git-receive-pack';
        $response = $this->makeRequest($apiCommand);

        $return = array(
            'branches' => array(),
            'tags' => array()
        );

        foreach ($response[1] as $listItem) {
            $items = explode(' ', $listItem);

            if (preg_match('/refs\/tags\//', $items[1])) {
                $itemName = str_replace('refs/tags/', '', $items[1]);
                $return['tags'][$itemName] = $items[0];
            } else {
                $itemName = str_replace('refs/heads/', '', $items[1]);
                $return['branches'][$itemName] = $items[0];
            }
        }

        return $return;
    }

    public function getPacketFile()
    {
        $apiCommand = '/info/refs?service=git-upload-pack';
        $response = $this->makeRequest($apiCommand);

        print_r($response);
    }

    public function makeRequest($apiCommand, Array $post = array()) {
        $client = new \GuzzleHttp\Client();

        $fullUrl = $this->uri.$apiCommand;

        $response = null;

        try {
            if ($post) {
                $response = $client->post($fullUrl, ['auth' =>  [$this->userName, $this->password]]);
            } else {
                $response = $client->get($fullUrl, ['auth' =>  [$this->userName, $this->password]]);
            }
        } catch (Exception $e){}

        if (!$response instanceof Response) {
            throw new NoResponseException('There was no response to remote: '.$this->uri);
        }

        $this->checkStatusCodes($response);

        return $this->getMessages($response);
    }

    protected function checkStatusCodes(Response $response)
    {
        $statusCode = $response->getStatusCode();

        switch ($statusCode) {
            case 400:
                throw new NotFoundException('There was no Git server found at: '.$this->uri);
            case 403:
                throw new NotAuthorizedException(
                    'Unable to login to Git at: '.$this->uri.'  Please check your login credentials and try again.'
                );
            case 301:
            case 200:
                return true;
            default:
                throw new InvalidResponseException('The server returned an unknown response. Uri: '.$this->uri);
        }
    }

    protected function getMessages(Response $response)
    {
        $body = (string) $response->getBody();

        if (empty($body)) {
            throw new InvalidResponseException(
                'There was no messages returned from the server at: '.$this->uri
            );
        }

        $messages = array();
        $messageCounter = 0;

        while($body) {
            $lineLengthHex = substr($body, 0, 4);

            if (strlen($lineLengthHex) != 4) {
                throw new InvalidResponseException('A corrupt package was received from the server.  Uri: '.$this->uri);
            }

            if ($lineLengthHex == '0000') {
                $messageCounter++;
                $body = substr_replace($body, '', 0, 4);
                continue;
            }

            $lineLength = hexdec($lineLengthHex);
            $line = substr($body, 4, $lineLength-4);
            $body = substr_replace($body, '', 0, $lineLength);
            $messages[$messageCounter][] = trim($line);
        }

        return $messages;
    }


}