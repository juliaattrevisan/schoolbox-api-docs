<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_SchoolNews extends Facade_Component_Generic {

    public function render(GizaUser $user) {

        $limit = 3;

        $newsHandler = new News();

        $topics = $newsHandler->getTopicList();

        $topicList = array();

        foreach($topics as $t) {
            $topicList[$t['newsTopicID']] = $t['name'];
        }

        $headlines = $newsHandler->getHeadlines();

        $newsList = array();


        if(is_array($headlines)) {
            foreach($headlines as $headline) {
                if(isset($topicList[$headline['newsTopicID']])) {
                    if( isset($newsList[$topicList[$headline['newsTopicID']]]) &&
                        count($newsList[$topicList[$headline['newsTopicID']]]) >= $limit ) {
                        continue;
                    }
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
