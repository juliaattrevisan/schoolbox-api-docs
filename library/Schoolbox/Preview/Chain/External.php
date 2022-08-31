<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain;

use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Preview\Chain\Strategy\External\IFrame;
use Schoolbox\Preview\Chain\Strategy\External\Image;
use Schoolbox\Preview\Chain\Strategy\External\Embed;
use Schoolbox\Preview\Chain\Strategy\External\Redirect;

/**
 * Class External
 *
 * Chain of Responsibility
 * Seeks preview from an External (non-local) url
 *
 * @package Schoolbox\Preview\Chain
 */
class External extends Base
{

    /**
     * Constructor
     *
     * @param string $url
     */
    public function __construct($url)
    {
        $decodedUrl = urldecode($url);

        $this->start([
            new Image($decodedUrl, []),
            new Embed($decodedUrl, []),
            new IFrame($decodedUrl, preg_split('/\R/', ConfigLoader::getInstance()->get('safe_iframe_source_list'))), // Explode on \R to include all types of newline chars.
            new Redirect($decodedUrl, []),
        ]);
    }
}
