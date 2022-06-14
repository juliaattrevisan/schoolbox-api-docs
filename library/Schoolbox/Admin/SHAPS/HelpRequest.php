<?php

/**
 * HelpRequest
 *
 * Ask a help.schoolbox.com.au endpoint to tell me answers
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\SHAPS;

use GuzzleHttp;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Schoolbox\Admin\SHAPS\Auth;
use Schoolbox\User;
use Schoolbox\UserMapper;
use InvalidArgumentException;
use Custom\Help\CourseParticipation;

use function http_build_query;

class HelpRequest
{
    /**
     * API request parameter generator
     *
     * @var Auth\Parameters
     */
    private Auth\Parameters $authParams;

    /**
     * Data source for users in the local Schoolbox instance
     *
     * @var UserMapper
     */
    private UserMapper $userMapper;


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
     * @throws InvalidArgumentException                If unable to instantiate a guzzle client
     * @throws GuzzleException                         4xx, 5xx, connect, tooManyRedirect
     * @throws Exception\UnexpectedResponseException   2xx response, but response is unrecognisable
     */
    public function users(): array
    {
        return $this->execute('get', 'users');
    }

    /**
     * Alias to the Courses endpoint
     *
     * @return array
     * @throws InvalidArgumentException                If unable to instantiate a guzzle client
     * @throws GuzzleException                         4xx, 5xx, connect, tooManyRedirect
     * @throws Exception\UnexpectedResponseException   2xx response, but response is unrecognisable
     */
    public function courses(): array
    {
        return $this->execute('get', 'courses');
    }


    /**
     * Asks help for information located at $endpoint
     *
     * ->execute('get', 'users');
     * This returns an array of key-value pairs of (id => email)
     *
     * ->execute('get', 'courses');
     * This returns an array of...stuff
     *
     * @param string     $method
     * @param string     $endpoint
     * @param array|null $formParams
     * @param array      $queryParams
     * @return array
     * @throws InvalidArgumentException                If unable to instantiate a guzzle client
     * @throws GuzzleException                         4xx, 5xx, connect, tooManyRedirect
     * @throws Exception\UnexpectedResponseException   2xx response, but response is unrecognisable
     */
    private function execute(string $method, string $endpoint, ?array $formParams = null, array $queryParams = []): array
    {
        /* Get the four pieces of the puzzle that we need to request data from Help
         */
        $auth = $this->authParams->generate();

        /* Execute request using guzzle
         * Host and Origin headers are for working around CSRF protection
         * (which shouldn't be necessary for API calls, but is for some reason)
         */
        $defaultQueryParams = ['ts' => $auth['timestamp']];
        $queryString        = http_build_query(array_merge($defaultQueryParams, $queryParams));
        $uri                = "{$auth['url']}/api/course-participation/{$auth['schoolCode']}/{$endpoint}?$queryString";
        $headers            = [
            'Host'           => $auth['host'],
            'Origin'         => $auth['host'],
            'Authentication' => $auth['digest'],
        ];
        if (null === $formParams) {
            $body = null;
        } else {
            $body                    = http_build_query($formParams);
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        // Need to instantiate a Request instance so we can insert into exceptions below.
        $request  = new GuzzleHttp\Psr7\Request($method, $uri, $headers, $body);
        $response = (new GuzzleHttp\Client())->send($request, ['allow_redirects' => ['strict' => true]]);

        if (CourseParticipation\Controller::STATUS_NO_CONTENT === $response->getStatusCode()) {
            // There's no contents to read.
            return [];
        }

        // Attempt to json decode the response body
        try {
            $decoded_response = json_decode($response->getBody()->getContents(), true);
        } catch (\RuntimeException $e) {
            throw new Exception\UnexpectedResponseException('Unable to read SHAPS API response', $request, $response, $e);
        }

        // If this is an array, we can assume we got some data back from Help
        if (\is_array($decoded_response)) {
            return $decoded_response;
        }
        // If it isn't, it either failed to decode or we hit an incorrect server endpoint, but that server endpoint returned a 2xx, likely
        // with a HTML payload. This happens for example if you hit a login wall, which redirected you to a login page.
        throw new Exception\UnexpectedResponseException("Unable to decode SHAPS API response: {$decoded_response}", $request, $response);
    }


    /**
     * Helper method to get a list of user objects from the raw JSON Help API response.
     *
     * This is a list of hydrated users who have an equivalent representation in Help.
     *
     * @return \Schoolbox\GenericCollection
     * @throws InvalidArgumentException                If unable to instantiate a guzzle client
     * @throws GuzzleException                         4xx, 5xx, connect, tooManyRedirect
     * @throws Exception\UnexpectedResponseException   2xx response, but response is unrecognisable
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
            'order'   => [
                'lastname'  => 'asc',
                'firstname' => 'asc',
            ],
            'deleted' => true,
        ])->indexBy('id');
    }


    /**
     * Find all the enrolments and the people in them...I suppose
     *
     * @return array
     * @throws InvalidArgumentException                If unable to instantiate a guzzle client
     * @throws GuzzleException                         4xx, 5xx, connect, tooManyRedirect
     * @throws Exception\UnexpectedResponseException   2xx response, but response is unrecognisable
     */
    public function findEnrolments(): array
    {
        $enrolments = $this->courses();

        $provisionedUsers = $this->findProvisionedUsers();

        foreach ($enrolments as $i => $enrolment) {
            /** @var array $participants */
            $participants = $enrolment['participants'];

            foreach ($participants as $n => $participant) {
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
        return $this->authParams->getServerUrl();
    }

    /**
     * Provision course participation
     * @param array $userIds
     * @param array $courses
     * @return array
     */
    public function provisionUserParticipation(array $userIds, array $courses): array
    {
        $users         = $this->userMapper->findByColumn('id', $userIds, ['deleted' => true]);
        $coursePayload = [];
        foreach ($courses as $courseId => $add) {
            foreach ($users as $user) {
                if ($add && $user->isDeleted()) {
                    // do not attempt to add deleted user to a course
                    continue;
                }
                $coursePayload[$courseId][$user->getEmail()] = $add;
            }
        }
        $data = [];

        try {
            $this->execute('post', 'courses', ['course' => $coursePayload], ['byUsername' => '1']);
            $code    = 200;
            $message = 'OK';
        } catch (ClientException | Exception\UnexpectedResponseException | ConnectException | TooManyRedirectsException $e) {
            // 400: empty payload (should never get this) or some users failed
            $code    = 400;
            $message = 'Bad Request';
            if ($e->getResponse()) {
                try {
                    $data = json_decode($e->getResponse()->getBody()->getContents(), true);
                    foreach ($data as $key => $emailAddress) {
                        $data[$key]['message'] = sprintf("Failed to modify participation for %s", $emailAddress);
                    }
                } catch (\RuntimeException $e) {
                    // Not much we can do here.
                }
            }
        } catch (GuzzleException $e) {
            // any other exception
            $code    = 500;
            $message = 'Internal Server Error';
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
     * Provision users
     *
     * @param array $userIds
     * @return array
     * @throws InvalidArgumentException                If unable to instantiate a guzzle client
     */
    public function provisionUsers(array $userIds): array
    {
        $payload = $this->buildProvisionUsersPayload($userIds);
        $data    = [];
        if (!empty($payload)) {
            // Send each user to Help
            try {
                $this->execute('post', 'users', ['user' => $payload], ['byUsername' => '1']);
                $code    = 200;
                $message = 'OK';
            } catch (ClientException | Exception\UnexpectedResponseException | ConnectException | TooManyRedirectsException $e) {
                // 400: empty payload (should never get this) or some users failed
                $code    = 400;
                $message = 'Bad Request';
                if ($e->getResponse()) {
                    try {
                        // Build human-readable error message
                        $data = json_decode($e->getResponse()->getBody()->getContents(), true);
                        foreach ($data as $key => $failedUserData) {
                            $data[$key]['message'] = sprintf("Failed to provision user %s: %s", $failedUserData[1]['id'], $failedUserData[0] ?? '');
                        }
                    } catch (\RuntimeException $e) {
                        // Not much we can do here.
                    }
                }
            } catch (GuzzleException $e) {
                // any other exception
                $code    = 500;
                $message = 'Internal Server Error';
            }
        } else {
            // empty payload
            $code    = 400;
            $message = 'Bad Request';
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
    private function buildProvisionUsersPayload(array $userIds): array
    {
        $userPayload = [];
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
        return $userPayload;
    }
}
