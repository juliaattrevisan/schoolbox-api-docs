<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Mobile\News;

use Schoolbox\Mobile\Generic;
use Schoolbox\Resource\CategoryMapper;
use Config;
use ClientDevice;
use Scrub;
use Response_HTTP;
use User;
use News;
use Alaress_Date_Relative;
use TextFormatter;

class API extends Generic\API {

    public function School($categoryName = '') {
        $response = new Response_HTTP();

        $newsHandler = new News();
        $userHandler = new User();
//        $headlines = $newsHandler->getHeadlines();
        $headlines = $newsHandler->getNewsListing(false, $categoryName);

        $articles = array();

        if(is_array($headlines) && count($headlines)) {
            foreach($headlines as $headline) {
                if(!isset($articles[$headline['newsTopicID']])) {
                    // create an article for this topic
                    $articles[$headline['newsTopicID']] = array(
                        'title'   => $headline['topicName'],
                        'aside'   => '',
                        'class'   => 'news',
                        'icon'    => '',
                        'content' => array()
                    );
                }
                // insert headling into the topic
                $adr = new Alaress_Date_Relative($headline['post_datetime']);
                $headline['post_datetime_relative'] = $adr->getRelativeTime();
                $headline['item_body_clean'] = TextFormatter::cutSentence(Scrub::html2text($headline['item_body']), 300);
                $headline['poster'] = $userHandler->formatName($headline);
                $articles[$headline['newsTopicID']]['content'][] = $headline;
            }
        }
        else {
            $articles = array(
                array(
                    'title'   => 'all',
                    'aside'   => '',
                    'class'   => 'news wide',
                    'icon'    => '',
                    'content' => false
                )
            );
        }

        $response->setPage('title', 'School News');
        $response->setResponse('articles', $articles);


        return $response;
    }
}
