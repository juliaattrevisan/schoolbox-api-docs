<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;

class Category_API extends Generic_API {

    private $colMap = array(null, 'left', 'right', 'top');

    /**
     * @url /category/homepage/$catID
     * @param $catID
     * @return Response_HTTP
     */
    public function Homepage($catID) {
		/**
		 * @var CategoryMapper $categoryMapper
		 * @var Category $category
		 */
		$categoryMapper = CategoryMapper::getInstance();
	    $category = $categoryMapper->findById($catID);

	    // category doesn't exist
	    if(!($category instanceof Category)) {
		    // return 404
			return new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
	    }

	    $response = new Response_HTTP($this->responseCode);

	    $factory = new Component_Homepage_Factory(new User(), null, false);
	    $componentCollection = $factory->buildComponentList($catID);

        $articles = array();
        foreach($componentCollection as $c) {
	        /**
	         * @var Component_ControllerAbstract $c
	         */

	        $instance = $c->getInstance();

	        $articles[] = array(
                'title'   => $instance->config['componentname'] || $instance->definition->name,
                'aside'   => $instance->definition->name,
                'class'   => 'homepage-' . $this->colMap[$instance->position],
                'icon'    => '',
                'content' => $category->getTemplateId() ? $c->getConfig() : array(),
            );
        }

        $response->setPage('title', $category->getName());
        $response->setResponse('articles', $articles);

        return $response;
    }
}
