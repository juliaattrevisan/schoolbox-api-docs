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
     */
    public function index($params) {
        $testUrls = [
            "http://www.taylorswift.com/",
            "http://edition.cnn.com/",
            "http://192.168.36.10/learningAnalytics/177",
            "https://www.google.com.au/search?q=taylor+swift&oq=taylor+swift&aqs=chrome..69i57j69i65j69i60l4.1253j0j4&sourceid=chrome&es_sm=93&ie=UTF-8",
            "https://s-media-cache-ak0.pinimg.com/236x/fb/c7/7c/fbc77c27b92b84fa99c9295d6d307a2d.jpg",
            "http://en.wikipedia.org/wiki/FR._Lee_Ermey",
            "https://twitter.com/arstechnica/status/533056992592818176",
            "http://s3.majorleaguegaming.com/files/OrlandoChamp.zip",
        ];

        /* Rummage
         */
        $results = [];

        foreach ($testUrls as $currentUrl) {
            $chain = new Start(urlencode($currentUrl));

            $results[$currentUrl] = $chain->getResult();
        }

        $this->getResponse()->content->set([
            'template' => '@preview/index.twig',
            'data' => [
                'results' => $results
            ]
        ]);
    }
}
