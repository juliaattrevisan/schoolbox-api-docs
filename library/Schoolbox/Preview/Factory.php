<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */

namespace Schoolbox\Preview;

use Aura\Router\Router;
use Schoolbox\Preview\Chain\Internal;
use Schoolbox\Preview\Chain\External;
use Schoolbox\Preview\Registry\Decoder\LinkRedir;
use Schoolbox\Preview\Registry\Decoder\TaskRedir;
use Schoolbox\User;

class Factory
{

    /**
     * @var Router
     */
    private $router;


    /**
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }


    /**
     * Given a URL will create the appropriate preview json array for outputting
     * This exists separately now so that it can be used in the task screen for displaying URLs inline
     *
     * @param string $encodedUrl
     * @param User   $user
     * @return array
     * @throws \DomainException
     * @throws \InvalidArgumentException if $url is not a string
     */
    public function urlFragment($encodedUrl, User $user)
    {
        /* Distill a url from an encapsulating structure, if necessary
         */
        $decoderRegistry = new Registry\Registry($encodedUrl);
        $decoderRegistry->add(new LinkRedir());
        $decoderRegistry->add(new TaskRedir($this->router));

        $testUrl = $decoderRegistry->findUrl();

        /* No decoder could distill a url from the encapsulated structure
         * or there was no encapsulating structure: just use the url by
         * default, since nothing can be done about it
         */
        if ('' === $testUrl) {
            $testUrl = $encodedUrl;
        }

        /* Detect whether to use a Local or External chain
         * Each has a different way to determine their type
         */
        $url = new \Url($testUrl);

        /* Local Chain
         */
        if ($url->isLocalUrl()) {
            if (!$user instanceof User) {
                $user = new User();
            }
            $chain  = new Internal($url->getUrl(), $user);
            $result = $chain->getResult();
        } /* External Chain
         */
        else {
            $chain  = new External($url->getUrl());
            $result = $chain->getResult();
        }

        /* Set up html fragment
         */
        $fragment = null;
        if ($result['type'] !== 'inline') {
            $fragment = $result['src'];
        }

        $previewResponse = [
            'type'     => $result['type'],
            'twig'     => $result['twig'],
            'fragment' => $fragment,
        ];

        return $previewResponse;
    }
}
