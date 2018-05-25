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
use Codeception\Lib\Connector\Guzzle;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use GuzzleHttp\Client as GuzzleClient;
use Schoolbox\Permission\SiteAccess;
use Schoolbox\Collection;

class Controller extends Base
{
    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(ErrorMapper $errorMapper, UserMapper $userMapper)
    {
        $this->setErrorMapper($errorMapper);
        $this->setUserMapper($userMapper);
    }

    /**
     * Get a list of users provisioned on Help for a particular school, in JSON format.
     *
     * @return mixed
     */
    private function getUsersFromHelp()
    {
        $client = new GuzzleClient();

        $url = $this->getConfigLoader()->get('schoolbox_help_url');

        // Query params
        $schoolCode = strtolower($this->getConfigLoader()->get('site_school_code'));

        $now = new Alaress_Date_Format();

        $privateKey = $this->getConfigLoader()->get('schoolbox_help_access_token');

        // TODO use general location \Custom\Help\CourseParticipation\Handler::computeAccessToken once factored out
        $token = sha1(sprintf(':%s-%s-%s:', $now, $schoolCode, $privateKey));

        $timestamp = $now->getUnixTimeStamp();

        $response = $client->request(
            "get",
            "${url}/api/course-participation/${schoolCode}/users", [
            'query' => [
                'ts' => $timestamp,
                'token' => $token
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

    /**
     * Renders a list of all staff in your Schoolbox instance, and whether or not they are provisioned on Help.
     *
     * @api GET /shaps/provisioning
     */
    public function getAll()
    {
        // All the staff in our Schoolbox instance
        $allStaff = $this->userMapper->findByColumn('g.group_type', 'staff')->sortBy("lastname", "asc");
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
        $allStaff = $this->userMapper->findByColumn('g.group_type', 'staff')->sortBy("lastname", "asc");
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
     * @api GET /shaps/provisioning/{userID}
     */
    public function searchUsers($userID)
    {
        // Selectize gives us the user's external ID
        $user = $this->userMapper->findByExternalId($userID);

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

    /**
     * @return UserMapper
     */
    private function getUserMapper(): UserMapper
    {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     * @return $this
     */
    private function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    private function getErrorMapper(): ErrorMapper
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return $this
     */
    private function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }
}
