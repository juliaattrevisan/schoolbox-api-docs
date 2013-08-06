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
use Alaress_Date_Relative;
use TextFormatter;
use Config;
use ClientDevice;
use Scrub;
use Schoolbox\User;
use Schoolbox\UserMapper;

class HomepageNews extends Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return array
     */
    public function render(User $user, $catID = 0) {

        if($catID == 0) {
            return '';
        }

        $limit = 8;

        $newsList = array();

        $homepageNewsHandler = new \CategoryNews();
        $userHandler = new \User();

        $catNews = $homepageNewsHandler->getAllForCategory($catID);

        if($catNews) {
            foreach($catNews as $item) {
                if(count($newsList) >= $limit) {
                    break;
                }
                $adr = new Alaress_Date_Relative($item['posted']);
                $item['post_datetime_relative'] = $adr->getRelativeTime();
                $item['poster'] = $userHandler->formatName($item);
                $item['item_body_clean'] = TextFormatter::cutSentence(Scrub::html2text($item['body']), 300);
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
