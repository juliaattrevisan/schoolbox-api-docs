<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Facade\Component;

use Alaress_Date_Relative;
use Scrub;
use TextFormatter;
use Schoolbox\User;

class SchoolNews extends Generic {

    public function render(User $user) {

        $limit = 3;

        $newsHandler = new \News();
        $userHandler = new \User();

        $topics = $newsHandler->getTopicList();

        $topicList = array();

        foreach($topics as $t) {
            $topicList[$t['newsTopicID']] = $t['name'];
        }

//        $headlines = $newsHandler->getHeadlines();
        $headlines = $newsHandler->getNewsListing(false);

        $newsList = array();

        if(is_array($headlines)) {
            foreach($headlines as $headline) {
                if(isset($topicList[$headline['newsTopicID']])) {
                    if( isset($newsList[$topicList[$headline['newsTopicID']]]) &&
                        count($newsList[$topicList[$headline['newsTopicID']]]) >= $limit ) {
                        continue;
                    }
                    $adr = new Alaress_Date_Relative($headline['post_datetime']);
                    $headline['post_datetime_relative'] = $adr->getRelativeTime();
                    $headline['poster'] = $userHandler->formatName($headline);
                    $headline['item_body_clean'] = TextFormatter::cutSentence(Scrub::html2text($headline['item_body']), 300);
                    $newsList[$topicList[$headline['newsTopicID']]][] = $headline;
                }
            }
        }

        return array(
            'title'   => 'School News',
            'aside'   => '',
            'class'   => 'dashboard-news',
            'icon'    => 'news',
            'content' => $newsList,
        );
    }
}
