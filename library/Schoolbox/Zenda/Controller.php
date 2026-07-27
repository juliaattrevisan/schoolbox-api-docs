<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Zenda;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper    $errorMapper
     * @param \External_User $externalUser
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private \External_User $externalUser,
    ) {
    }


    /**
     * Endpoint that will eventually derive a Zenda redirect
     * @throws \JsonException  When a $params couldn't be encoded, but it's always an array from here; shouldn't happen.
     */
    public function getUrl(): void
    {
        $params = (new PayloadGenerator($this->externalUser))->generate($this->getUser());

        /* This can throw a DomainException in the case of an Ultimate Failure...but what do we do about that?
         * I guess we'd need to make an error screen of some kind
         */
        try {
            $url = (new Api(
                $this->getConfigLoader()->get(ConfigMapper::NAME_ZENDA_CLIENT_ID),
                $this->getConfigLoader()->get(ConfigMapper::NAME_ZENDA_CLIENT_SECRET),
                $this->getConfigLoader()->get(ConfigMapper::NAME_ZENDA_HOST),
            ))->retrieve(Api::ENDPOINT_REDIRECT, $params)['redirectURL'];
        } catch (\DomainException $e) {
            /* No url could be found for this user for some reason...Zenda couldn't find anything or we can't connect
             * to the Zenda itself. Since we retry EVERY POSSIBILITY you might have waited a long time to see this
             * error screen :/
             */
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* They send us an expiryAt too; probably expecting us to continually reuse it until it dies. We aren't
         * storing and reusing that atm, forcing us to send new requests every time.
         *
         * TODO find out how to reuse this (if we can)
         */

        /* Generic 404 for now I suppose: they didn't send us a meaningful url
         */
        if ('' === trim((string)$url)) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Get the url and redirect to it, I guess
         */
        $this->response = $this->redirectFound($url);
    }
}
