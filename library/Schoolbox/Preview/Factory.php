<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\Preview;

use DomainException;
use InvalidArgumentException;
use Schoolbox\Cyberhound\Url\Translator;
use Schoolbox\Preview\Chain\Internal;
use Schoolbox\Preview\Chain\External;
use Schoolbox\Preview\Registry\Decoder\LinkRedir;

/**
 * Class Factory
 *
 * @package Schoolbox\Preview
 */
class Factory
{

    /**
     * @var Translator
     */
    private $translator;


    /**
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }


    /**
     * Given a URL will create the appropriate preview json array for outputting
     * This exists separately now so that it can be used in the task screen for displaying URLs inline
     *
     * @param string $encodedUrl
     * @return array
     * @throws DomainException
     * @throws InvalidArgumentException if $url is not a string
     */
    public function urlFragment($encodedUrl): array
    {
        /* Distill a url from an encapsulating structure, if necessary
         */
        $decoderRegistry = new Registry\Registry($encodedUrl);
        $decoderRegistry->add(new LinkRedir());

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

        if ($url->isLocalUrl()) {
            /* Internal Chain
             */
            $result = (new Internal($url->getUrl()))->getResult();
        } else {
            /* External Chain
             */
            $result = (new External($url->getUrl()))->getResult();
            if (isset($result['src'])) {
                $result['src'] = $this->translator->translate($result['src']);
            }
        }

        /* Set up html fragment
         */
        $fragment = null;

        if (($result['type'] ?? null) !== 'inline') {
            $fragment = $result['src'] ?? null;
        }

        return [
            'type'     => $result['type'] ?? null,
            'twig'     => $result['twig'] ?? null,
            'fragment' => $fragment,
        ];
    }
}
