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
use Schoolbox\Preview\Chain\Strategy\Other;

class Start {

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url) {

        /* xml the curl result so we can find things better
         * This allows us to xpath our way around
         *
         * Still, improperly formed documents according to
         * simplexml or domdocument causes it to fatally die
         * Avoid it by using internal_errors(true)
         * http://stackoverflow.com/questions/6090667/php-domdocument-errors-warnings-on-html5-tags
         */
//        if (($curl != null) && strlen($curl->getResult())) {
//            $data = $curl->getResult();
//        }

        /* Set up chain of responsibility
         */
        $image = new Image($url);
        $other = new Other($url);

        /* Try until one of them matches
         */
        $image->setNext($other);

        $result = $image->test();
    }
}