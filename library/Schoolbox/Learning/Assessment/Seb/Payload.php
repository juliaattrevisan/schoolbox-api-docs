<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Seb;

use Aura\Router\Router;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Generate an xml payload for the given IAssessable
 *
 * Usage;
 *
 * new Payload(
    * $this->getRequest()->server->get('HTTP_HOST'),
    * $router->generate('learningAssessment.getAssessment', [
        * 'assessmentId' => $assessment->getId()
    * ]
 *
 */
class Payload
{

    /**
     * Browser Exam Key and Config Key are hashed with SHA256
     */
    public const HASH_ALGORITHM = 'sha256';


    /**
     * Load this inaccessible file and swop out its vars, phplib style
     */
    private const PATH = __DIR__ . '/config.seb';


    /**
     * @var string
     */
    private string $host;


    /**
     * @var Router
     */
    private Router $router;


    /**
     * @param string      $host
     * @param Router      $router
     */
    public function __construct(string $host, Router $router)
    {
        $this->host = $host;
        $this->router = $router;
    }


    /**
     * Generate a seb config, ready to go, for the assessment
     *
     * @param IAssessable $assessment
     * @return string
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function generate(IAssessable $assessment): string
    {
        /* Figure out the absolute url for this assessment
         */
        $startUrl = 'https://' . $this->host . $this->router->generate('learningAssessment.getAssessment', [
            'assessmentId' => $assessment->getId()
        ]);

        /* Manufacture a phplib style struct for strings and their replacements
         */
        $tokens = [
            '{{ startUrl }}' => $startUrl
        ];

        /* Perform the replacements
         */
        return strtr(file_get_contents(self::PATH), $tokens);
    }


    /**
     * Generate hash of the dynamic xml for this assessment
     *
     * @param IAssessable $assessment
     * @return string
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function generateKey(IAssessable $assessment): string
    {
        return $this->hash($this->generate($assessment));
    }


    /**
     * Hash an xml into whatever format was required
     *
     * @param string $xml
     * @return string
     */
    private function hash(string $xml): string
    {
        return hash(self::HASH_ALGORITHM, json_encode(simplexml_load_string($xml)));
    }
}
