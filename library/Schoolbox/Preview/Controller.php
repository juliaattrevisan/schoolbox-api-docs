<?php

/**
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Preview;

use Alaress\Web\Controller\Base;
use Schoolbox\Cyberhound\Url\Translator;

/**
 * Class Controller
 *
 * @package Schoolbox\Preview
 */
class Controller extends Base
{
    /**
     * A list of test urls to try
     * Can delete this later
     *
     * @param array $params
     */
    public function index($params)
    {
        $testUrls = [
            ['External Generic url' => "http://www.taylorswift.com/"],
            ['External Generic url' => "http://edition.cnn.com/"],
            ['External Generic url' => "http://en.wikipedia.org/wiki/FR._Lee_Ermey"],
            ['External Generic url' => "https://twitter.com/arstechnica/status/533056992592818176"],
            ['External Video Vimeo' => "https://vimeo.com/channels/staffpicks/23371242"],
            ['External Video Youtube' => "https://www.youtube.com/watch?v=zPonioDYnoY"],
            ['External Audio Soundcloud' => "https://soundcloud.com/majorlazer"],
            ['External Image JPG' => "https://s-media-cache-ak0.pinimg.com/236x/fb/c7/7c/fbc77c27b92b84fa99c9295d6d307a2d.jpg"],
            ['External Image JPG' => "http://globe-views.com/dcim/dreams/owl/owl-03.jpg"],
            ['External Image JPG' => "http://pugetsoundbirds.org/wp-content/uploads/2010/06/Northern-saw-whet-owl.jpg"],
            ['External Image JPG' => "http://www.libertyscentre.co.uk/Owls/large/Collared%20Scops%20Owl.jpg"],
            ['External File Zip' => "http://s3.majorleaguegaming.com/files/OrlandoChamp.zip"],
            ['Local Image JPG' => "/storage/fetch.php?hash=62f643c3b484a87f3ce2e3a8216c7f232787af02"],
            ['Local Image PNG' => "/storage/fetch.php?hash=c28763abce0725ccde28dbdd84aec956844a596d"],
            ['Local Video' => "/storage/fetch.php?hash=10d6e62fec60bbbff5cdae4aae78c8aa64b69ef9"],
            ['Local Video' => "/storage/fetch.php?hash=fcd9618abc108c9b1e7a110b429cc3f4b7df3d4c"],
            ['Local Audio' => "/storage/fetch.php?hash=0f5ad33468ff91ff4ea3f30788467905bd454fc5"],
            ['Local HTML' => "/storage/fetch.php?hash=330c579c668d2c88c37f0c1572abc876fb2cbe67"],
            ['Local XML' => "/storage/fetch.php?hash=f5ce8f8a0192c7de2602a4c132ad66b7ee0b13d6"],
            ['Local PDF' => "/storage/fetch.php?hash=afaafce41021389f0cc857dfc03b136d6d1b2f29"],
            ['Local ZIP' => "/storage/fetch.php?hash=bb85de4fb0f04e27556763f7beaf7f8a0094c8ab"],
            ['Local URL' => "/homepage/43"],

            /* redir.php usage
             */
            ['Local URL Redir by ID' => "/redir.php?id=39"],
            ['Local URL Redir by Base64Encoded' => "/redir.php?url=aHR0cHM6Ly93d3cueW91dHViZS5jb20vd2F0Y2g%2Fdj16UG9uaW9EWW5vWQ%3D%3D"],

            /**
             * Special cases
             */

            /* This url can't be embedded because X-Frame-Options is set to SAMEORIGIN
             * In that case, skip directly to the redirect - but I can't even do that
             * because http_response_code() always returns 404 (regardless of url) and
             * getHeaders() is always 400 Bad Request. This url will just be an empty
             * modal. Not sure how I'm going to get around that yet.
             */
            ['External Generic url' => "http://www.google.com.au/search?q=taylor+swift&oq=taylor+swift&aqs=chrome..69i57j69i65j69i60l4.1253j0j4&sourceid=chrome&es_sm=93&ie=UTF-8"],
        ];

        $this->response = $this->responseFromTemplate(
            '@preview/Index.twig',
            [
                'allUrls' => $testUrls,
            ],
        );
    }


    /**
     * Test method
     * @param array $params
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function preview($params)
    {
        /* Test safeness of requested url via Cyberhound blacklist
         */
        $translator = new Translator(
            $this->getSession()->get('ip'),
            $this->getConfigLoader()->get('cyberhound_api_url'),
            $this->getConfigLoader()->get('cyberhound_api_shared_key')
        );

        $previewFragment = (new Factory($translator))->urlFragment($params['encodedUrl']);

        if ($previewFragment['type'] === 'inline') {
            $previewFragment['fragment'] = $this->responseFromTemplateUnthemed($previewFragment['twig']['template'], $previewFragment['twig']['data'])
                                                ->getBody()->getContents();
        }

        //Not required by preview.js
        unset($previewFragment['twig']);

        $this->response = $this->responseAsJson($previewFragment);
    }
}
