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
use Category;
use Group;
use Config;
use ClientDevice;
use Scrub;
use Schoolbox\User;

class CategoryFolders extends Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return array
     */
    public function render(User $user, $catID = 0) {

        // generate data
        $catHandler = new Category();
        $groupHandler = new Group();
        $children = $catHandler->getCategoryList($catID);

        for($i = 0; $i < count($children); ++$i) {
            if($groupHandler->getPermission($catID) < READ_ACCESS) {
                unset($children[$i]);
            }
        }

        return array(
            'title'   => 'Folders',
            'aside'   => '',
            'class'   => 'category-folders',
            'icon'    => 'work',
            'content' => $children,
        );
    }

}