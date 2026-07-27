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

        try {
            $url = (new Api(
                $this->getConfigLoader()->get(ConfigMapper::NAME_ZENDA_CLIENT_ID),
                $this->getConfigLoader()->get(ConfigMapper::NAME_ZENDA_CLIENT_SECRET)
            ))->retrieve(Api::ENDPOINT_REDIRECT, $params)['redirectURL'];
        } catch (\DomainException $e) {
            /* Could not determine identifier in payload: there wasn't a meaningful email/phone value
             * for this user; throw an arbitrary error for now
             */
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        /* Generic 404 for now I suppose
         */
        if ('' === trim((string)$url)) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Get the url and redirect to it, I guess
         */
        $this->response = $this->redirectFound($url);
    }
}
