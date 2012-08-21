<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Category_API extends Generic_API {

    private $colMap = array(null, 'left', 'right', 'top');

    /**
     * @url /category/homepage/$catID
     * @param $catID
     * @return Response_HTTP
     */
    public function Homepage($catID) {
        $response = new Response_HTTP($this->responseCode);

        $m = array();

        $m[] = microtime(true);

        $cMap = new GizaCategory_DataMapper();

        $m[] = microtime(true);

        $category = $cMap->read($catID);

        $m[] = microtime(true);

        $homepage = $category->get('homepage');

        $m[] = microtime(true);

        $components = $homepage->get('components');

        $m[] = microtime(true);

        /*
        for($i = 1; $i < count($m); $i++) {
            echo (($m[$i] - $m[$i - 1]) * 1000) . "ms\n";
        }

        echo (($m[count($m) - 1] - $m[0]) * 1000) . "ms\n";
        */

        $articles = array();
        foreach($components as $c) {
            $articles[] = array(
                'title'   => $c->get('name'),
                'aside'   => $c->get('method'),
                'class'   => 'homepage-' . $this->colMap[$c->get('column')],
                'icon'    => '',
                'content' => $homepage ? $c->get('config') : array('flail'),
            );
        }

        $response->setPage('title', $category->get('name'));
        $response->setResponse('articles', $articles);
        $response->setResponse('colours', $homepage->get()->getColours());

        return $response;
    }
}
