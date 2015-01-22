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
use Schoolbox\Resource\LinkMapper;

class HomepageLinks extends Generic {

    /**
     * This should return an array of [title, aside, content]
     *
     * @param User $user
     * @param int $catID
     * @return array
     */
    public function render(User $user, $catID = 0) {

        // generate data
        try {
            $categoryMapper = new CategoryMapper();
            $category = $categoryMapper->findById($catID);

            $linkMapper = new LinkMapper();
            $allLinks = $linkMapper->findAllInCategory($category, array());
        }
        catch(ErrorException $e) {
            die($e);
        }

        return array(
            'title'   => 'Links',
            'aside'   => '',
            'class'   => 'homepage-links',
            'icon'    => 'next',
            'content' => $allLinks,
        );
    }
}