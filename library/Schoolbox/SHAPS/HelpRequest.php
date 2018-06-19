<?php
/**
 * HelpRequest
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
     * Asks help for information located at $endpoint
     *
     * e.g. ->execute($config, 'users');
     *
     * @param IConfigLoader $configLoader
     * @param string $endpoint
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    private function execute(IConfigLoader $configLoader, string $endpoint)
    {
        /* Get the four pieces of the puzzle that we need to request data from Help
         */
        $auth = (new Auth\Parameters(
            $configLoader->get('schoolbox_help_url'),
            $configLoader->get('site_school_code'),
            $configLoader->get('schoolbox_help_access_token')
        ))->generate();


        $response = (new GuzzleClient())->request(
            "get",
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



    /**
     * Get a list of users provisioned on Help for a particular school, in JSON format.
     *
     * @return mixed
     */
    private function getUsersFromHelp()
    {
        $auth = $this->getAuthDetails();

        $response = $client->request(
            "get",
            "{$auth['url']}/api/course-participation/{$auth['schoolCode']}/users", [
                'query' => [
                    'ts' => $auth['timestamp'],
                    'digest' => $auth['digest']
                ]
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get a list of courses available on Help for a particular school, in JSON format.
     *
     * @return mixed
     */
    private function getCoursesFromHelp()
    {
        $client = new GuzzleClient();
        $auth = $this->getAuthDetails();

        $response = $client->request(
            "get",
            "{$auth['url']}/api/course-participation/{$auth['schoolCode']}/courses", [
            'query' => [
                'ts' => $auth['timestamp'],
                'digest' => $auth['digest']
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}