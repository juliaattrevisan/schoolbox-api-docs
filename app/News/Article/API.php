<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class News_Article_API {

    public function School($newsID) {
        $response = new Response_HTTP();

        $newsHandler = new News();
        $news = $newsHandler->getByID($newsID);

        $article = array(
            'title'   => $news['item_heading'],
            'aside'   => '',
            'class'   => '',
            'icon'    => 'news',
            'content' => $news['item_body']
        );

        $response->setPage('title', 'News');
        $response->setResponse('articles',array($article));

        return $response;
    }

    public function Category($newsID) {
        $response = new Response_HTTP();

        $newsHandler = new CategoryNews();
        $news = $newsHandler->getByID($newsID);

        $article = array(
            'title'   => $news['heading'],
            'aside'   => '',
            'class'   => '',
            'icon'    => 'news',
            'content' => $news['body']
        );

        $response->setPage('title', 'News');
        $response->setResponse('articles',array($article));

        return $response;
    }
}
