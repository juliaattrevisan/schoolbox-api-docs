<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_HomepageNews extends Facade_Component_Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return array
     */
    public function render(GizaUser $user, $catID = 0) {

        if($catID == 0) {
            return '';
        }

        $limit = 8;

        $newsList = array();

        $homepageNewsHandler = new CategoryNews();

        $catNews = $homepageNewsHandler->getAllForCategory($catID);

        if($catNews) {
            foreach($catNews as $item) {
                if(count($newsList) >= $limit) {
                    break;
                }
                $newsList[] = $item;
            }
        }

        return array(
            'title'   => 'News',
            'aside'   => '',
            'class'   => 'homepage-news',
            'icon'    => 'news',
            'content' => $newsList,
        );
    }

}
