<?php
/** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace Helper;

use Codeception\Module\Db;
use Codeception\Module\REST;
use Codeception\TestInterface;
use Payload\Request;
use Symfony\Component\BrowserKit\Cookie;
use phpDocumentor\Reflection\Types\Mixed_;

/**
 * This Helper provides custom extensions to the REST module
 *
 * Extends, rather than uses, as we need access to protected methods in the REST module
 *
 * Low-level 'how' actions are performed belongs here.
 * Higher-level convenience 'what' actions belong in the Actor, which wll use this Helper.
 *
 * All public methods declared in helper class will be available in $I
 *
 * Note that annotations need to use FQDN as these are copied verbatim to generated actor trait
 *
 * @see https://codeception.com/docs/06-ModulesAndHelpers
 */
class Integration extends REST
{

    /**
     * **HOOK** executed before test
     *
     * @param TestInterface $test
     * @throws \UnexpectedValueException
     */
    public function _before(TestInterface $test): void
    {
        parent::_before($test);

        if ($this->_getConfig('xdebug') ?? false) {
            $this->client->getCookieJar()->set(new Cookie('XDEBUG_SESSION', 'PHPSTORM'));
        }
    }

    /**
     * @param string $sessionID
     */
    public function setSessionCookie(string $sessionID){
        $this->client->getCookieJar()->set(new Cookie('PHPSESSID', $sessionID));
    }

    /**
     * @return string|NULL
     */
    public function getSessionCookie(){
        $sessionCookie = $this->client->getCookieJar()->get('PHPSESSID');
        if($sessionCookie !== NULL)
        {
            return $this->client->getCookieJar()->get('PHPSESSID')->getValue();
        }
        return NULL;
    }

    /**
     * @param \Payload\Request $request
     */
    public function sendRequest(Request $request): void
    {
        foreach ($request->getHeaders() as $name => $value) {
            $this->haveHttpHeader($name, $value);
        }

        $this->execute(
            $request->getMethod(),
            $request->getUrlWithQueryString(),
            $request->getFormParams(),
            $request->getFiles()
        );

        foreach ($request->getHeaders() as $name => $value) {
            $this->deleteHeader($name);
        }
    }

    /**
     * @return string
     */
    public function grabResponseCode(): string
    {
        return $this->connectionModule->_getResponseStatusCode();
    }

    /**
     * @return Db
     */
    public function grabDBModule(): Db
    {
        $db = $this->getModule('Db');
        return $db;
    }
}
