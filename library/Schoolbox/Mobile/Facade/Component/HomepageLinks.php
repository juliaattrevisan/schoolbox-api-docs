<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Facade\Component;

use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\CategoryLogMapper;
use Config;
use ClientDevice;
use Scrub;
use Link;
use ErrorException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class HomepageLinks extends Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return array
     */
    public function render(User $user, $catID = 0) {

        // generate data
        try {
            $linkHandler = new Link();
            $links = $linkHandler->getAllInCategory($catID);
        }
        catch(ErrorException $e) {
            die($e);
        }


        return array(
            'title'   => 'Links',
            'aside'   => '',
            'class'   => 'homepage-links',
            'icon'    => 'next',
            'content' => $links,
        );
    }

}