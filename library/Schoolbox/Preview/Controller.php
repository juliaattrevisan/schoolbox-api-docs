<?php
/**
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Preview;

use Alaress\Web\Controller\Base;
use Schoolbox\Preview\Chain\Start;

class Controller extends Base
{

    /**
     * Test method
     *
     * http%3A%2F%2Fwww.taylorswift.com
     * http%3A%2F%2Farstechnica.com%2Finformation-technology%2F2015%2F03%2Ffor-a-brighter-robotics-future-its-time-to-offload-their-brains%2F
     * http%3A%2F%2F192.168.36.10%2Fhomepage%2F501
     * https%3A%2F%2Fwww.google.com.au%2Fwebhp%3Fsourceid%3Dchrome-instant%26ion%3D1%26espv%3D2%26ie%3DUTF-8%23q%3Dphilosoraptor
     */
    public function index($params) {
        $chain = new Start('http%3A%2F%2Fwww.taylorswift.com');

        $this->getResponse()->content->set([
            'template' => '@preview/index.twig',
            'data' => [
                'url' => [
                    'original' => $params['encodedUrl'],
                    'decoded' => urldecode($params['encodedUrl'])
                ]
            ]
        ]);
    }
}
