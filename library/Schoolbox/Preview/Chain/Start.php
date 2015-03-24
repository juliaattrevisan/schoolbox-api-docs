<?php
/**
 * Chain
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain;

use Schoolbox\Preview\Chain\Strategy\Image;
use Schoolbox\Preview\Chain\Strategy\Html;
use Schoolbox\Preview\Chain\Strategy\Other;

class Start extends Base {

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url) {
        $headers = $this->findHeaders($url);

        $this->start([
            new Image($headers),
            new Html($headers),
            new Other($headers),
        ]);
    }

    /**
     * Decode url and format header output
     */
    private function findHeaders($url) {
        $headers = get_headers(urldecode($url), 1);

        /* Rebuild array if you got redirected around the place
         */
        $numRedirects = count($headers['Content-Type']);

        if ($numRedirects > 1) {
            $newHeaders = array();

            foreach(array_keys($headers) as $currentKey) {
                if (is_array($headers[$currentKey])) {
                    $newHeaders[$currentKey] = $headers[$currentKey][$numRedirects-1];
                }
                else {
                    $newHeaders[0] = $headers[$numRedirects-1];
                }
            }

            $headers = $newHeaders;
        }

        return $headers;
    }
}