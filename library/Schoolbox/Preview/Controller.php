<?php
/**
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Preview;

use Alaress\Web\Controller\Base;
use Schoolbox\Preview\Chain\Internal;
use Schoolbox\Preview\Chain\External;

class Controller extends Base
{

    /**
     * A list of test urls to try
     * Can delete this later
     * 
     * @param $params
     */
    public function index($params) {
        $testUrls = [

            /* Generic locations
             */
            "http://www.taylorswift.com/",
            "http://edition.cnn.com/",
            "http://192.168.36.10/learningAnalytics/177",
            "https://www.google.com.au/search?q=taylor+swift&oq=taylor+swift&aqs=chrome..69i57j69i65j69i60l4.1253j0j4&sourceid=chrome&es_sm=93&ie=UTF-8",
            "https://s-media-cache-ak0.pinimg.com/236x/fb/c7/7c/fbc77c27b92b84fa99c9295d6d307a2d.jpg",
            "http://en.wikipedia.org/wiki/FR._Lee_Ermey",
            "https://vimeo.com/channels/staffpicks/23371242",
            "https://twitter.com/arstechnica/status/533056992592818176",
            "https://www.youtube.com/watch?v=zPonioDYnoY",
            "https://soundcloud.com/majorlazer",
            "http://globe-views.com/dcim/dreams/owl/owl-03.jpg",
            "http://pugetsoundbirds.org/wp-content/uploads/2010/06/Northern-saw-whet-owl.jpg",
            "http://www.libertyscentre.co.uk/Owls/large/Collared%20Scops%20Owl.jpg",

            /* File downloads
             */
            "http://s3.majorleaguegaming.com/files/OrlandoChamp.zip",

            /* Jpg uploads into Course Builder
             */
            "/storage/fetch.php?hash=62f643c3b484a87f3ce2e3a8216c7f232787af02",

            /* Png uploads into Course Builder
             */
            "/storage/fetch.php?hash=c28763abce0725ccde28dbdd84aec956844a596d",

            /* Video uploads into Course Builder
             */
            "/storage/fetch.php?hash=10d6e62fec60bbbff5cdae4aae78c8aa64b69ef9",
            "/storage/fetch.php?hash=fcd9618abc108c9b1e7a110b429cc3f4b7df3d4c",

            /* Audio uploads into Course Builder
             */
            "/storage/fetch.php?hash=0f5ad33468ff91ff4ea3f30788467905bd454fc5",
        ];

        $this->getResponse()->content->set([
            'template' => '@preview/Index.twig',
            'data' => [
                'allUrls' => $testUrls,
            ]
        ]);
    }

    /**
     * Test method
     */
    public function preview($params) {

        /* Detect whether to use a Local or External chain
         * Each has a different way to determine their type
         */
        $url = new \Url($params['encodedUrl']);

        /* Local Chain
         */
        if ($url->isLocalURL()) {
            $bits = parse_url($params['encodedUrl']);
            $querystring = $bits['query'];

            preg_match('/hash=([a-z0-9]+)/i', $querystring, $matches);

            if (count($matches)) {
                $hash = $matches[1];

                $chain = new Internal($params['encodedUrl'], $hash);
                $result = $chain->getResult();
            }
        }
        /* External Chain
         */
        else {
            $chain = new External($params['encodedUrl']);
            $result = $chain->getResult();
        }

        /* Set up html fragment
         */
        $this->getResponse()->content->set($result['twig']);

        /* Render it
         */
        $output = $this->di->get("webOutput:nochrome");
        $output->setResponse($this->getResponse());
        $fragment = $output->getAsString();

        $previewResponse = [
            'type' => $result['type'],
            'fragment' => $fragment
        ];

        /* Regurgitate it
         */
        $this->getResponse()->content->set([
            'data' => $previewResponse
        ]);
    }
}
