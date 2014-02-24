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
