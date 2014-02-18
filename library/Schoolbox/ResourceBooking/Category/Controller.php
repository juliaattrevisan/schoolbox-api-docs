<?php
/**
 * Experimental controller for a resource booking category
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Category;
use Alaress\Web\Controller\Base;

class Controller extends Base {

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * Browses the list of categories.
     */
    public function browse() {

        // How am i going to paginate this list?
        $list = $this->getMapper()->findAll();

        $response = $this->getResponse();
        $response->content->set(
            array(
                'template' => __DIR__.'/templates/browse.twig',
                'data'     => array(
                    'list' => $list,
                ),
            )
        );
    }

    /**
     * Form for adding another category
     */
    public function add() {
        $response = $this->getResponse();
        $response->content->set(
            array(
                'template' => __DIR__.'/templates/add.twig',
                'data'     => array(
                    'item' => new Model(),
                ),
            )
        );
    }

    /**
     * Create request destination
     */
    public function create() {
        $post  = $this->getRequest()->post;
        $model = new Model();
        $model->setName($post['name']);
        $this->getMapper()->save($model);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate('resourceCategory.browse', array())
        );
    }

    public function edit($id) {
        $item = $this->getMapper()->findById($id);
        $this->getResponse()->content->set(
            array(
                'template' => __DIR__.'/templates/add.twig',
                'data'     => array(
                    'item' => $item,
                ),
            )
        );
    }

    public function update($id) {
        $item = $this->getMapper()->findById($id);
        $post  = $this->getRequest()->post;
        $model = new Model();
        $model->setName($post['name']);
        $this->getMapper()->save($model);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate('resourceCategory.browse', array())
        );
    }

    /**
     * @param  Mapper $mapper
     * @return $this
     */
    public function setMapper($mapper) {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @return Mapper
     */
    public function getMapper() {
        return $this->mapper;
    }

}
