<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\SHAPS;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\UserMapper;
use GuzzleHttp\Client as GuzzleClient;
use Schoolbox\Collection;

class Controller extends Base
{
    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * Controller constructor.
     *
     * @param UserMapper $userMapper
     */
    public function __construct(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /*
     * Helper method to get details required for any Help API request.
     *
     * @return array
     */
    private function getAuthDetails() : array
    {
        $url = rtrim($this->getConfigLoader()->get('schoolbox_help_url'), '/');

        // Query params
        $schoolCode = strtolower($this->getConfigLoader()->get('site_school_code'));

        $now = new Alaress_Date_Format();

        $accessToken = $this->getConfigLoader()->get('schoolbox_help_access_token');

        $digest = (new AuthDigest($schoolCode, $accessToken))->generate($now->getUnixTimeStamp());

        $timestamp = $now->getUnixTimeStamp();

        return ['url' => $url, 'timestamp' => $timestamp, 'digest' => $digest, 'schoolCode' => $schoolCode];
    }

    /**
     * Get a list of users provisioned on Help for a particular school, in JSON format.
     *
     * @return mixed
     */
    private function getUsersFromHelp()
    {
        $client = new GuzzleClient();
        $auth = $this->getAuthDetails();

        $route = $this->getRouter()->generate('helpCourseParticipation.getUsersBySchool', [
            'schoolCode' => $auth['schoolCode']
        ]);

        $response = $client->request(
            "get",
            "{$auth['url']}{$route}", [
                'query' => [
                    'ts' => $auth['timestamp'],
                    'digest' => $auth['digest']
                ]
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Helper method to get a list of user objects from the raw JSON Help API response.
     *
     * @return Collection
     */
    private function getProvisionedUsers() : Collection
    {
        $provisionedUserIds = $this->getProvisionedUserIds();
        $provisionedUsers = $this->userMapper->findByColumn('id', $provisionedUserIds);
        return $provisionedUsers;
    }

    /**
     * Helper method to get a list of IDs of users provisioned on Help.
     *
     * @return array
     */
    private function getProvisionedUserIds() : array
    {
        $provisionedUsers = $this->getUsersFromHelp();
        return array_keys($provisionedUsers);
    }

    /*
     * Helper method to get all staff in your Schoolbox instance.
     *
     * @return Collection
     */
    private function getAllStaff() : Collection
    {
        // All the staff in our Schoolbox instance
        return $this->userMapper->findByColumn('g.group_type', \Schoolbox\Role::STAFF, [
            'order' => [
                'lastname' => 'asc',
                'firstname' => 'asc'
            ]
        ]);
    }

    /**
     * Renders a list of all staff in your Schoolbox instance, and whether or not they are provisioned on Help.
     *
     * @api GET /shaps/provisioning
     */
    public function getAll()
    {
        $allStaff = $this->getAllStaff();
        $provisionedUserIds = $this->getProvisionedUserIds();

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/index.twig', [
                'allUsers' => $allStaff,
                'provisionedUserIds' => $provisionedUserIds
            ])->setTitle('Schoolbox Help Staff Provisioning')
        );
    }

    /**
     * Renders a list of staff in your Schoolbox instance that are also provisioned on Help.
     *
     * @api GET /shaps/provisioning/provisioned
     */
    public function getProvisioned()
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/provisioned.twig', [
                    'allUsers' => $this->getProvisionedUsers(),
                    'provisionedUserIds' => $this->getProvisionedUserIds()
            ])->setTitle('Provisioned Staff - Schoolbox Help Staff Provisioning')
        );
    }

    /**
     * Renders a list of staff in your Schoolbox instance that are NOT provisioned on Help.
     *
     * @api GET /shaps/provisioning/unprovisioned
     */
    public function getUnprovisioned()
    {
        $allStaff = $this->getAllStaff();
        $unprovisionedStaff = $allStaff->diff($this->getProvisionedUsers());

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/unprovisioned.twig', [
                    'allUsers' => $unprovisionedStaff
            ])->setTitle('Unprovisioned Staff - Schoolbox Help Staff Provisioning')
        );
    }

    /**
     * Renders a selectize for searching users in your Schoolbox instance to see if they are provisioned.
     *
     * @api GET /shaps/provisioning/search
     */
    public function getSearch()
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/search.twig', [
            ])->setTitle('Search - Schoolbox Help Staff Provisioning')
        );
    }

    /**
     * Provides details about a user in response to an ajax request for that user's external ID.
     *
     * @api GET /shaps/provisioning/{userId}
     * @param string $userId
     */
    public function searchUsers(string $userId)
    {
        // Selectize gives us the user's external ID
        $user = $this->userMapper->findByExternalId($userId);

        $provisionedUserIds = $this->getProvisionedUserIds();
        $status = in_array($user->getId(), $provisionedUserIds);
        $userJSON = [
            'name' => $user->getFullName(),
            'provisioned' => $status,
            'id' => $user->getId(),
            'externalID' => $user->getExternalId(),
        ];
        $this->getResponse()->content->set($this->buildJsonOutput($userJSON));
    }
}
