<?php
/**
 * HelpRequest
 *
 * Ask a help.schoolbox.com.au endpoint to tell me answers
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SHAPS;

use GuzzleHttp\Client as GuzzleClient;
use Schoolbox\IConfigLoader;
use Schoolbox\SHAPS\Auth;


class HelpRequest
{

    /**
     * @var IConfigLoader
     */
    private $configLoader;


    /**
     * HelpRequest constructor.
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }


    /**
     * Asks help for information located at $endpoint
     *
     * ->execute('users');
     * This returns an array of key-value pairs of (id => email)
     *
     * ->execute('courses');
     * This returns an array of...stuff
     *
     * @param string $endpoint
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function execute(string $endpoint) : array
    {
        /* Get the four pieces of the puzzle that we need to request data from Help
         */
        $auth = (new Auth\Parameters(
            $this->configLoader->get('schoolbox_help_url'),
            $this->configLoader->get('site_school_code'),
            $this->configLoader->get('schoolbox_help_access_token')
        ))->generate();

        /* Execute request using guzzle
         */
        $response = (new GuzzleClient())->request(
            'get',
            "{$auth['url']}/api/course-participation/{$auth['schoolCode']}/{$endpoint}", [
                'query' => [
                    'ts' => $auth['timestamp'],
                    'digest' => $auth['digest']
                ]
            ]
        );

        /* Return a json-encoded output of the body contents
         */
        return json_decode($response->getBody()->getContents(), true);
    }
}