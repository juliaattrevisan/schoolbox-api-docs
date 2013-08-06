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
use Schoolbox\User;

class CategoryForums extends Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return array
     */
    public function render(User $user, $catID = 0) {

        // generate data

        return array(
            'title'   => 'Forums',
            'aside'   => '',
            'class'   => 'category-forums',
            'icon'    => 'groups',
            'content' => array(),
        );
    }

}