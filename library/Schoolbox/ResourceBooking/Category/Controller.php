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
     * Browses the list of categories.
     */
    public function browse() {
        $response = $this->getResponse();
        $response->content->set(
            array(
                'data'     => array(),
                'template' => __DIR__.'/templates/browse.twig',
            )
        );
    }
}
