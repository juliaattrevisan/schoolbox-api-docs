<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum;

use DebugBar\DebugBarException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Builds the WA Curriculum csv from a set of endpoints
 */
class WACurriculumBuilder
{
    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const NAP_TIMEOUT = 3;


    /**
     * Number of times to retry after having NAP_TIMEOUT seconds' rest before
     * finally giving up
     */
    private const NUM_RETRY = 3;


    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;


    /**
     * Manifest is a hardcoded set of urls to json files that inform
     * the WA curriculum csv nodes. We can't guarantee how this set
     * changes over time
     *
     * The source website produces broken links, so the http protocol
     * is manually repaired to https:// here
     */
    private const HOST = 'https://scsaapps.scsa.wa.edu.au/Apps/CurriculumJSON';
    private const MANIFEST = [
        '/english.json' => 'English Curriculum - Pre-primary to Year 10 ',
        '/hpe.json' => 'Health and Physical Education Curriculum - Pre-primary to Year 10',
        '/hass.json' => 'Humanities and Social Sciences Curriculum - Pre-primary to Year 10',

        /* Languages Curriculum
         */
        '/chinese-second-language.json' => 'Chinese: Second Language Curriculum - Pre-primary to Year 10',
        '/french-second-language.json' => 'French: Second Language Curriculum - Pre-primary to Year 10',
        '/german-second-language.json' => 'German: Second Language Curriculum - Pre-primary to Year 10',
        '/indonesian-second-language.json' => 'Indonesian: Second Language Curriculum - Pre-primary to Year 10',
        '/italian-second-language.json' => 'Italian: Second Language Curriculum - Pre-primary to Year 10',
        '/japanese-second-language.json' => 'Japanese: Second Language Curriculum - Pre-primary to Year 10',
        '/languages7-10.json' => 'Languages Curriculum - Year 7 to Year 10',

        /* Some other unnamed section/grouping
         */
        '/mathematics.json' => 'Mathematics Curriculum - Pre-primary to Year 10',
        '/science.json' => 'Science Curriculum - Pre-primary to Year 10',
        '/technologies.json' => 'Technologies Curriculum - Pre-primary to Year 10',
        '/arts.json' => 'The Arts Curriculum - Pre-primary to Year 10',
    ];


    /**
     * @var Client|null
     */
    private ?Client $client;


    /**
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct()
    {
        $this->client = $this->initClient(self::HOST, self::DEFAULT_TIMEOUT);
    }


    /**
     * Set up guzzle client
     *
     * @param string $host
     * @param int    $timeout
     * @return Client
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    private function initClient(string $host, int $timeout): Client
    {
        $headers = [
            'Content-Type' => 'application/json'
        ];

        return new Client([
            'headers' => $headers,
            'base_uri' => $host,
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }


    /**
     * @param string $url
     * @return string
     * @throws GuzzleException
     * @throws \RuntimeException
     */
    private function fetch(string $url): string
    {
        $response = $this->client->get($url);

        if (200 === $response->getStatusCode()) {
            return $response->getBody()->getContents();
        }

        return '';
    }


    /**
     * Collate the WA curriculum csv from the json source files
     *
     * @return void
     * @throws GuzzleException
     * @throws DebugBarException
     * @throws \RuntimeException
     */
    public function generate(): void
    {
        foreach (self::MANIFEST as $url => $name) {
            new \Alaress_Probe($this->fetch($url));
        }
    }
}