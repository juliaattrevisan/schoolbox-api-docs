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
     * @param UserMapper  $userMapper
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
    private function getAuthDetails() {
        $url = rtrim($this->getConfigLoader()->get('schoolbox_help_url'), '/');

        // Query params
        $schoolCode = strtolower($this->getConfigLoader()->get('site_school_code'));

        $now = new Alaress_Date_Format();

        $privateKey = $this->getConfigLoader()->get('schoolbox_help_access_token');

        // TODO once \Custom\Help\CourseParticipation\Handler::computeAccessToken is moved, replace this with new method
        $token = sha1(sprintf(':%s-%s-%s:', $now, $schoolCode, $privateKey));

        $timestamp = $now->getUnixTimeStamp();

        return ['url' => $url, 'timestamp' => $timestamp, 'token' => $token];
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

        $response = $client->request(
            "get",
            "{$auth['url']}/api/course-participation/{$auth['schoolCode']}/users", [
            'query' => [
                'ts' => $auth['timestamp'],
                'token' => $auth['token']
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Helper method to get a list of user objects from the raw JSON Help API response.
     *
     * @return Collection
     */
    private function getProvisionedUsers() {
        $provisionedUserIDs = $this->getProvisionedUserIDs();
        $provisionedUsers = $this->userMapper->findByColumn('id', $provisionedUserIDs);
        return $provisionedUsers;
    }

    /**
     * Helper method to get a list of IDs of users provisioned on Help.
     *
     * @return array
     */
    private function getProvisionedUserIDs()
    {
        $provisionedUsers = $this->getUsersFromHelp();
        return array_keys($provisionedUsers);
    }

    /*
     * Helper method to get all staff in your Schoolbox instance.
     *
     * @return Collection
     */
    private function getAllStaff() {
        // All the staff in our Schoolbox instance
        return $this->userMapper->findByColumn('g.group_type', \Schoolbox\Role::STAFF, [
            'order' => [
                'lastname' => 'asc'
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
        $provisionedUserIDs = $this->getProvisionedUserIDs();

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/index.twig', [
                'allUsers' => $allStaff,
                'provisionedUserIDs' => $provisionedUserIDs
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
                    'provisionedUserIDs' => $this->getProvisionedUserIDs()
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
    public function searchUsers($userId)
    {
        // Selectize gives us the user's external ID
        $user = $this->userMapper->findByExternalId($userId);

        $provisionedUserIDs = $this->getProvisionedUserIDs();
        $status = in_array($user->getId(), $provisionedUserIDs);
        $userJSON = [
            'name' => $user->getFullName(),
            'provisioned' => $status,
            'id' => $user->getId(),
            'externalID' => $user->getExternalId(),
        ];
        $this->getResponse()->content->set($this->buildJsonOutput($userJSON));
    }
}
