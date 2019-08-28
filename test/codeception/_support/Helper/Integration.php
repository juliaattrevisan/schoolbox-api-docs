<?php
/** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace Helper;

use Codeception\Module\REST;
use Payload\Request;

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
    }
}
