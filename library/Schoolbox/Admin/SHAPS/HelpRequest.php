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

namespace Schoolbox\Admin\SHAPS;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Schoolbox\Admin\SHAPS\Auth;
use Schoolbox\User;
use Schoolbox\UserMapper;

use Schoolbox\Admin\SHAPS\Exception\AuthenticationException;
use Schoolbox\Admin\SHAPS\Exception\AuthorisationException;
use Schoolbox\Admin\SHAPS\Exception\RequestException;
use Schoolbox\Admin\SHAPS\Exception\RefusedRequestException;
use Schoolbox\Admin\SHAPS\Exception\UnexpectedResponseException;
use Schoolbox\Admin\SHAPS\Exception\InvalidUrlException;



class HelpRequest
{
    /**
     * API request parameter generator
     *
     * @var Auth\Parameters
     */
    private $authParams;

    /**
     * Data source for users in the local Schoolbox instance
     *
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
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function users(): array
    {
        try {
            return $this->execute('get', 'users');
        } catch (\Schoolbox\Admin\SHAPS\Exception\UnexpectedResponseException $e) {
            throw \Schoolbox\Error\Mapper::getInstance()->generateException(\Schoolbox\Error\Mapper::ERROR_SHAPS_UNEXPECTED_RESPONSE);
        } catch (\Schoolbox\Admin\SHAPS\Exception\AuthorisationException $e) {
            throw \Schoolbox\Error\Mapper::getInstance()->generateException(\Schoolbox\Error\Mapper::ERROR_SHAPS_AUTHORISATION_FAILURE);
        } catch (\Schoolbox\Admin\SHAPS\Exception\AuthenticationException $e) {
            throw \Schoolbox\Error\Mapper::getInstance()->generateException(\Schoolbox\Error\Mapper::ERROR_SHAPS_AUTHENTICATION_FAILURE);
        } catch (\Schoolbox\Admin\SHAPS\Exception\InvalidUrlException $e) {
            throw \Schoolbox\Error\Mapper::getInstance()->generateException(\Schoolbox\Error\Mapper::ERROR_SHAPS_INVALID_URL);
        } catch (\Schoolbox\Admin\SHAPS\Exception\RefusedRequestException $e) {
            throw \Schoolbox\Error\Mapper::getInstance()->generateException(\Schoolbox\Error\Mapper::ERROR_SHAPS_REFUSED_REQUEST);
        } catch (\Schoolbox\Admin\SHAPS\Exception\RequestException $e) {
            throw \Schoolbox\Error\Mapper::getInstance()->generateException(\Schoolbox\Error\Mapper::ERROR_SHAPS_GENERIC_CONNECTION_FAILURE, $e);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw \Schoolbox\Error\Mapper::getInstance()->generateException(\Schoolbox\Error\Mapper::ERROR_SHAPS_GENERIC_CONNECTION_FAILURE, $e);
        }
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
     * @param string $method
     * @param string $endpoint
     * @param array  $formParams
     * @return array
     * @throws \Schoolbox\Admin\SHAPS\Exception\UnexpectedResponseException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    private function execute(string $method, string $endpoint, array $formParams = []): array
    {
        /* Get the four pieces of the puzzle that we need to request data from Help
         */
        $auth = $this->authParams->generate();

        /* Execute request using guzzle
         * Host and Origin headers are for working around CSRF protection
         * (which shouldn't be necessary for API calls, but is for some reason)
         */
        try {
            $response = (new GuzzleClient())->request(
                $method,
                "{$auth['url']}/api/course-participation/{$auth['schoolCode']}/{$endpoint}", [
                    'query' => [
                        'ts'     => $auth['timestamp'],
                        'digest' => $auth['digest']
                    ],
                    'form_params' => $formParams,
                    'headers'     => [
                        'Host'   => $auth['host'],
                        'Origin' => $auth['host'],
                    ],
                ]
            );
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            throw new InvalidUrlException("Server URL was invalid");
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            switch ($e->getCode()) {
                case 403:
                    // Digest does not match
                    throw new AuthenticationException("Authentication failure - incorrect API key and/or school code");
                    break;
                case 400:
                    // Potential replay attack
                    throw new AuthorisationException("Authorisation failure - timestamp is out of date");
                    break;
                default:
                    // Something else happened that the server didn't like
                    throw new RefusedRequestException("Server refused your request");
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Unfortunately, this generic error can sometimes get thrown instead of ConnectException when the URL is invalid
            throw new RequestException("Unable to connect to the server: " .  $e->getMessage());
        }

        /* Return a json-encoded output of the body contents
         */
        $decoded_response = json_decode($response->getBody()->getContents(), true);

        // If this is an array, we know we got data back from Help
        // If it isn't, it either failed to decode or contains an error response code, so throw an exception
        // This path will also be executed if a valid URL is given but the server does not have the required routes
        // (I'm not sure how to detect this otherwise)
        if (\is_array($decoded_response)) {
            return $decoded_response;
        }
        throw new UnexpectedResponseException($decoded_response);
    }


    /**
     * Helper method to get a list of user objects from the raw JSON Help API response.
     *
     * This is a list of hydrated users who have an equivalent representation in Help.
     *
     * @return \Schoolbox\GenericCollection
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
        return $this->authParams->getServerUrl();
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
            try {
                $this->execute('post', 'users', ['user' => $payload,]);
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
