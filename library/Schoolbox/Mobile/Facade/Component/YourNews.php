<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Facade\Component;

use CategoryNews;
use Config;
use Scrub;
use Schoolbox\User;

class YourNews extends Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return array
     */
    public function render(User $user) {

        $limit = 3;

        // get categories involved in
        $subjects = $user->getSubjects()->asArray();
        $groups = $user->getGroups()->asArray();
        $cats = array_merge($subjects, $groups);

        $newsList = array();

        $homepageNewsHandler = new CategoryNews();

        foreach($cats as $cat) {
            $catNews = $homepageNewsHandler->getAllForCategory($cat->get('id'));

            if($catNews) {
                foreach($catNews as $item) {
                    if(isset($newsList[$cat->get('name')]) && count($newsList[$cat->get('name')]) >= $limit) {
                        continue;
                    }
                    $newsList[$cat->get('name')][] = $item;
                }
            }
        }

        return array(
            'title'   => 'My News',
            'aside'   => '',
            'class'   => 'dashboard-news',
            'icon'    => 'news',
            'content' => $newsList,
        );
    }

}
