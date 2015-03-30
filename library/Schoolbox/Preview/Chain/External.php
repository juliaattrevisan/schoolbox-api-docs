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

use Schoolbox\Preview\Chain\Strategy\External\Image;
use Schoolbox\Preview\Chain\Strategy\External\Embed;
use Schoolbox\Preview\Chain\Strategy\External\Redirect;

class External extends Base {

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url) {
        $decodedUrl = urldecode($url);

        /* Temporarily disabled
         * $headers = $this->findHeaders($decodedUrl);
         */
        $headers = array();

        $this->start([
            new Image($decodedUrl, $headers),
            new Embed($decodedUrl, $headers),
            new Redirect($decodedUrl, $headers),
        ]);
    }

    /**
     * Decode url and format header output
     *
     * Keept his in case you add strats which check for other
     * content-types
     */
    private function findHeaders($url) {
        $headers = get_headers($url, 1);

        /* Rebuild array if you got redirected around the place
         */
        $numRedirects = count($headers['Content-Type']);

        if ($numRedirects > 1) {
            $newHeaders = [];

            /* Just get the destination, ignore all the intermediary redirect locations
             */
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

        /* Lowercase all the array keys, because of places like twitter
         * which are not camelcased
         */
        $temp = [];

        foreach(array_keys($headers) as $currentKey) {
            $temp[strtolower($currentKey)] = $headers[$currentKey];
        }

        $headers = $temp;

        /* Finally
         */
        return $headers;
    }
}