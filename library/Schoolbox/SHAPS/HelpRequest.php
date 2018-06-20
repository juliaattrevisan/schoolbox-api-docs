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
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Schoolbox\SHAPS\Auth;
use Schoolbox\User;
use Schoolbox\UserMapper;

class HelpRequest
{

    /**
     * @var Auth\Parameters
     */
    private $authParams;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * HelpRequest constructor.
     * @param Auth\Parameters $authParams
     * @param UserMapper      $userMapper
     */
    public function __construct(Auth\Parameters $authParams, UserMapper $userMapper)
    {
        $this->authParams = $authParams;
        $this->userMapper = $userMapper;
    }


    /**
     * Alias to the Users endpoint
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function users(): array
    {
        return $this->execute('users');
    }


    /**
     * Alias to the Courses endpoint
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function courses(): array
    {
        return $this->execute('courses');
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
    private function execute(string $endpoint) : array
    {
        /* Get the four pieces of the puzzle that we need to request data from Help
         */
        $auth = $this->authParams->generate();

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


    /**
     * Helper method to get a list of user objects from the raw JSON Help API response.
     *
     * This is a list of hydrated users who have an equivalent representation in Help.
     *
     * @return \Schoolbox\GenericCollection
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function findProvisionedUsers(): \Schoolbox\GenericCollection
    {
        /* This returns an associative array of (id, email)
         * e.g. [246] => test@alaress.com.au
         */
        $users = $this->users();

        /* findByColumnList() only returns users that exist in the current installation
         * If it receives an id from help, but this id isn't in the current installation,
         * the id is simply ignored
         *
         * Order by surname and firstname, then index it by the user id
         */
        return $this->userMapper->findByColumn('id', array_keys($users), [
            'order' => [
                'lastname' => 'asc',
                'firstname' => 'asc'
            ]
        ])->indexBy('id');
    }


    /**
     * Find all the enrolments and the people in them...I suppose
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function findEnrolments(): array
    {
        $enrolments = $this->courses();

        $provisionedUsers = $this->findProvisionedUsers();

        foreach($enrolments as $i => $enrolment) {

            /** @var array $participants */
            $participants = $enrolment['participants'];

            foreach($participants as $n => $participant) {

                /* Check if this participant's id exists in the provisionedUsers index
                 *
                 * If this participant's id wasn't in the provisionedUsers index, then this is
                 * a convoluted way of saying this user couldn't be matched with anyone on our instance.
                 */
                if ($provisionedUsers->offsetGet($participant['id']) === null) {
                    $enrolments[$i]['participants'][$n]['id'] = null;
                }
            }
        }

        return $enrolments;
    }

    /**
     * Officially get the url with trailing slash removed, if it existed
     *
     * @return string
     */
    public function getServerUrl(): string
    {
        return rtrim($this->configLoader->get('schoolbox_help_url'), '/');
    }


    /**
     * Provision users
     *
     * @param array $userIds
     * @return array
     */
    public function provisionUsers($userIds): array
    {
        $payload = $this->buildProvisionUsersPayload($userIds);
        $code    = 400;
        $message = 'Bad Request';
        $data    = [];
        if (!empty($payload)) {
            // Send each user to Help
            $auth = $this->authParams->generate();

            // CSRF defeating:
            // set HTTP_ORIGIN and HTTP_HOST to the Help host
            // This endpoint calls `\Custom\Help\CourseParticipation\Controller::postUserRegistrations`
            // on host `config.schoolbox_help_url`.
            try {
                (new GuzzleClient())->request(
                    'post',
                    "{$auth['url']}/api/course-participation/{$auth['schoolCode']}/users",
                    [
                        'query' => [
                            'ts'     => $auth['timestamp'],
                            'digest' => $auth['digest']
                        ],
                        'form_params' => ['user' => $payload],
                        'headers'     => [
                            'Host'   => $auth['host'],
                            'Origin' => $auth['host'],
                        ],
                    ]
                );
                $code    = 200;
                $message = 'OK';

            } catch (ClientException $e) {
                // 400: empty payload (should never get this) or some users failed
                $code    = 400;
                $message = 'Bad Request';
                if ($e->getResponse()) {
                    $data = json_decode($e->getResponse()->getBody()->getContents());
                }

            } catch (GuzzleException $e) {
                // any other exception
                $code    = 500;
                $message = 'Internal Server Error';
            }
        }

        // Build response
        return [
            'data'   => $data,
            'status' => [
                'code'    => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * Build a payload of users to provision to Schoolbox Help.
     *
     * @param array $userIds
     * @return array
     */
    private function buildProvisionUsersPayload($userIds): array
    {
        $userPayload = [];
        if (is_iterable($userIds)) {
            foreach ($this->userMapper->findByColumn('id', $userIds) as $user) {
                /** @var User $user */
                if (!$user->getRole()->isStaff()) {
                    continue;
                }
                $userPayload[] = [
                    'id'        => $user->getId(),
                    'email'     => $user->getEmail(),
                    'title'     => $user->getTitle(),
                    'firstname' => $user->getFirstname(),
                    'lastname'  => $user->getLastname(),
                ];
            }
        }
        return $userPayload;
    }
}
