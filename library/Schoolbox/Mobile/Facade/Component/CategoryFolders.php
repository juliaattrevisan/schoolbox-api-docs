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

use Schoolbox\Resource\CategoryTreeBuilder;
use Schoolbox\Assessment\Course\TaskMapper;

class CategoryFolders extends Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @param int $catID
     * @return array
     */
    public function render(User $user, $catID = 0) {

        // generate data
        $groupHandler = new Group();

        $treeBuilder = new CategoryTreeBuilder(new CategoryMapper(), new TaskMapper(), $groupHandler);
        $allFolders = $treeBuilder->buildChildNodes($catID);

        for($i = 0; $i < count($allFolders); ++$i) {
            if ($groupHandler->getPermission($catID) < READ_ACCESS) {
                unset($allFolders[$i]);
            }
        }

        return array(
            'title'   => 'Folders',
            'aside'   => '',
            'class'   => 'category-folders',
            'icon'    => 'work',
            'content' => $allFolders,
        );
    }
}