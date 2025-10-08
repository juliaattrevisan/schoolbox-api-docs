<?php

/** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */

/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Exception\ExternalUrlException;
use Codeception\Exception\ModuleException;
use Codeception\Module\Db;
use Codeception\Module\REST;
use Codeception\TestInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Tests\Support\Payload\Request;
use Symfony\Component\BrowserKit\Cookie;

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

        if (($this->_getConfig('xdebug') ?? false) && ($this->client instanceof AbstractBrowser)) {
            $this->client->getCookieJar()->set(new Cookie('XDEBUG_SESSION', 'PHPSTORM'));
        }
    }

    /**
     * @param string|null $sessionID
     */
    public function setSessionCookie(?string $sessionID)
    {
        if ($this->client instanceof AbstractBrowser) {
            $this->client->getCookieJar()->set(new Cookie('PHPSESSID', $sessionID));
        }
    }

    /**
     * @return string|NULL
     */
    public function getSessionCookie()
    {
        if ($this->client instanceof AbstractBrowser) {
            $sessionCookie = $this->client->getCookieJar()->get('PHPSESSID');
            if ($sessionCookie !== null) {
                return $this->client->getCookieJar()->get('PHPSESSID')->getValue();
            }
        }
        return null;
    }

    /**
     * @param Request $request
     * @throws ExternalUrlException
     * @throws ModuleException
     * @throws \JsonException
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
        return (string)$this->connectionModule->_getResponseStatusCode();
    }

    /**
     * @return Db
     */
    public function grabDBModule(): Db
    {
        /** @var Db $db */
        $db = $this->getModule('Db');
        return $db;
    }

    /**
     * Grab the base URL out of this module's configuration.
     *
     * @return string
     */
    public function grabBaseUrl(): string
    {
        return $this->_getConfig('url');
    }
}
