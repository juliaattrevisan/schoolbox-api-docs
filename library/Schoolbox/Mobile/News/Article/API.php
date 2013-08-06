<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Mobile\News\Article;

use News;
use Response_HTTP;
use CategoryNews;

class API {

    public function School($newsID) {
        $newsHandler = new News();

	    if(!is_numeric($newsID) || $newsID < 1) {
		    return new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
	    }

        $news = $newsHandler->getByID($newsID);
	    if(is_array($news) ) {
		    $response = new Response_HTTP();
	        $topic = $newsHandler->getTopicByID($news['item_topic_id']);

	        $article = array(
	            'title'   => $news['item_heading'],
	            'aside'   => '',
	            'class'   => 'news wide',
	            'icon'    => 'news',
	            'content' => array(
	                'body' => $news['item_body'],
	                'image' => $news['item_image']
	            )
	        );

	        $response->setPage('title', 'School News &rsaquo; ' . $topic['name']);
	        $response->setResponse('articles',array($article));
		    return $response;
	    }
	    else {
		    return new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
	    }

    }

    public function Category($newsID) {
	    
        $newsHandler = new CategoryNews();

	    if(!is_numeric($newsID) || $newsID < 1) {
		    return new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
	    }

        $news = $newsHandler->getByID($newsID);
	    if(is_array($news)) {
		    $response = new Response_HTTP();
	        $article = array(
	            'title'   => $news['heading'],
	            'aside'   => '',
	            'class'   => 'news wide',
	            'icon'    => 'news',
	            'content' => $news['body']
	        );

	        $response->setPage('title', 'News');
	        $response->setResponse('articles',array($article));

	        return $response;
	    }
	    else {
		    return new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
	    }
    }
}
