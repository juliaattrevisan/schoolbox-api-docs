<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Exception\ModuleException;
use Codeception\Module\Db;
use Codeception\TestInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Schoolbox\Resource\Storage\HashGenerator;

const VAGRANT_DOMAIN = 'vagrant.dev.schoolbox.cloud';

class Integration extends \Codeception\Module
{

    public function sendRequestCurl($method, $uri, $params)
    {
        $headers= ['Host' => VAGRANT_DOMAIN,
                   'Content-Type'=>'application/x-www-form-urlencoded; charset=UTF-8',
                   'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                   'Referer' => $params['headers']['Referer']
                   ];
        $params['headers']=$headers;

        #_request($method, $uri, $params);
        return $this->getModule('PhpBrowser')->guzzle->request($method, $uri, $params);
    }
}