<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Zenda;

use Alaress\Web\Controller\Base;
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
        /* Temporary only to shut up phpstannis
         * Eventually url derivation via Zenda might throw errors: not sure yet
         */
        $temp = $this->errorMapper->createModel();

        echo 'url generation goes here';
    }
}
