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
     * @param ErrorMapper $errorMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
    ) {
    }


    /**
     * Endpoint that will eventually derive a Zenda redirect
     */
    public function getUrl(): void
    {
        $api = new Api(
            $this->getConfigLoader()->get(ConfigMapper::NAME_ZENDA_CLIENT_ID),
            $this->getConfigLoader()->get(ConfigMapper::NAME_ZENDA_CLIENT_SECRET)
        );

        $params = [
        ];

        $url = $api->retrieve(Api::ENDPOINT_REDIRECT, $params);

        new \Alaress_Probe($url);
    }
}
