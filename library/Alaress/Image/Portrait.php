<?php
/**
 * Alaress_Image_Portrait
 *
 * Facade class for Alaress_Image_Processor and Alaress_Image_Cache implementation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image;

use Alaress\Image\Transformation\Transformation;
use Schoolbox\User;

class Portrait extends AbstractHandler
{

    /**
     * Sets up the caching implementation, image processor and streams it
     *
     * @param int            $externalUserId
     * @param string         $key
     * @param Transformation $transformations
     * @param User           $sessionUser
     */
    public function __construct($externalUserId, $key, Transformation $transformations, User $sessionUser)
    {
        /* Choose a caching method to use
         */
        $cache = new Cache\Portrait($externalUserId, $key, $sessionUser);
        $cache->purgeOldTransformations();

        $this->initProcessor($cache, $transformations);
    }

}
