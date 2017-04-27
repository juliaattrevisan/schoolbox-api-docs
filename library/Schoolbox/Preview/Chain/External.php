<?php
/**
 * Chain
 *
 * Seeks preview from an External (non-local) url
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Preview\Chain\Strategy\External\IFrame;
use Schoolbox\Preview\Chain\Strategy\External\Image;
use Schoolbox\Preview\Chain\Strategy\External\Embed;
use Schoolbox\Preview\Chain\Strategy\External\Redirect;

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
            new Image($decodedUrl, ''),
            new Embed($decodedUrl, ''),
            new IFrame($decodedUrl, explode("\n", ConfigMapper::getInstance()->get('safe_iframe_source_list'))),
            new Redirect($decodedUrl, ''),
        ]);
    }
}
