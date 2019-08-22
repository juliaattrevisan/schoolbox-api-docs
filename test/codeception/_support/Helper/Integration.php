<?php
namespace Helper;

use Codeception\Exception\ModuleException;
use Codeception\Module\PhpBrowser;
use Psr\Http\Message\UriInterface;

/**
 * here you can define custom actions
 * all public methods declared in helper class will be available in $I
 */
class Integration extends \Codeception\Module
{
    /**
     * @param string              $method  HTTP method.
     * @param string|UriInterface $uri     URI object or string.
     * @param array               $options Request options to apply.
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws ModuleException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendRequestCurl($method, $uri, $params)
    {
        /** @var PhpBrowser $phpBrowser */
        $phpBrowser = $this->getModule('PhpBrowser');
        return $phpBrowser->guzzle->request($method, $uri, $params);
    }
}
