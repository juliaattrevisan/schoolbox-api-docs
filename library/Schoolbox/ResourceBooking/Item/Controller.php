<?php
/**
 * Controller for resource items.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Item;
use Alaress\Web\Controller\Base;

class Controller extends Base {

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * Browses the list of items.
     */
    public function browse() {
        $list = $this->getMapper()->findAll();
        $this->getResponse()->content->set(
            array(
                'template' => __DIR__.'/templates/browse.twig',
                'data'     => array(
                    'list' => $list,
                ),
            )
        );
    }

    /**
     * Display form for adding another category
     */
    public function add() {
        $this->getResponse()->content->set(
            array(
                'template' => __DIR__.'/templates/edit.twig',
                'data'     => array(
                    'item'         => new Model(),
                    'categoryList' => $this->getMapper()->getJoinMapper('category')->findAll(),
                ),
            )
        );
    }

    /**
     * Backend for adding a new category
     */
    public function create() {
        $post  = $this->getRequest()->post;
        $model = new Model();
        $model
            ->setName($post['name'])
            ->setDescription($post['description'])
            ->setCategory($post['resource_category_id'])
            // Let's just get started
            ->setMinLeadTime(new \DateInterval('PT1H'))
            ->setMinReservationTime(new \DateInterval('PT1H'))
            ->setMaxReservationTime(new \DateInterval('PT24H'))
        ;
        $this->getMapper()->save($model);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate('resourceItem.browse', array())
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
