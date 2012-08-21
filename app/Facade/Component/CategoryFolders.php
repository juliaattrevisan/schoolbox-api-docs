<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_CategoryFolders extends Facade_Component_Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return array
     */
    public function render(GizaUser $user, $catID = 0) {

        // generate data

        return array(
            'title'   => 'Folders',
            'aside'   => '',
            'class'   => 'category-folders',
            'icon'    => 'work',
            'content' => array(),
        );
    }

}