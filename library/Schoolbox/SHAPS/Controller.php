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
     * @var \Schoolbox\Config\Loader
     */
    private $config;

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
        $this->config = new \Schoolbox\Config\Loader();
    }

    private function getUsersFromHelp()
    {
        $client = new GuzzleClient();

        $url = $this->config->get('schoolbox_help_url');

        // Query params
        $schoolCode = strtolower($this->config->get('site_school_code'));

        $now = new Alaress_Date_Format();

        $privateKey = $this->config->get('schoolbox_help_access_token');

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

    private function getProvisionedUserIDs()
    {
        $provisionedUsers = $this->getUsersFromHelp();
        return array_keys($provisionedUsers);
    }

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

    public function getProvisioned()
    {
        $usersFromHelp = $this->getUsersFromHelp();
        $provisionedUsers = [];
        foreach($usersFromHelp as $externalID => $username) {
            $userObj = $this->userMapper->findByExternalId($externalID);
            $provisionedUsers[] = $userObj;
        }
        $provisionedUserIDs = $this->getProvisionedUserIDs();

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/provisioned.twig', [
                    'allUsers' => $provisionedUsers,
                    'provisionedUserIDs' => $provisionedUserIDs
            ])->setTitle('Provisioned Staff - Schoolbox Help Staff Provisioning')
        );
    }

    public function getUnprovisioned()
    {
        $allStaff = $this->userMapper->findByColumn('g.group_type', 'staff')->sortBy("lastname", "asc");
        $usersFromHelp = $this->getUsersFromHelp();
        $provisionedUsers = [];
        foreach($usersFromHelp as $externalID => $username) {
            $userObj = $this->userMapper->findByExternalId($externalID);
            $provisionedUsers[] = $userObj;
        }
        $unprovisionedStaff = $allStaff->diff(new Collection($provisionedUsers));

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/unprovisioned.twig', [
                    'allUsers' => $unprovisionedStaff
            ])->setTitle('Unprovisioned Staff - Schoolbox Help Staff Provisioning')
        );
    }

    public function getSearch()
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/search.twig', [
            ])->setTitle('Search - Schoolbox Help Staff Provisioning')
        );
    }

    public function searchUsers($userID)
    {
        $user = $this->userMapper->findByExternalId($userID);
        $provisionedUserIDs = $this->getProvisionedUserIDs();
        $status = in_array($user->getExternalId(), $provisionedUserIDs);
        $userJSON = [
            'name' => $user->getFullName(),
            'provisioned' => $status,
            'id' => $user->getId(),
            'externalID' => $user->getExternalId(),
        ];
        header('Content-Type: text/json');
        $this->getResponse()->content->set($this->buildJsonOutput($userJSON));
    }


    /**
     * Provide form
     *
     * @param User $user
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getExample(User $user)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@shaps/index.twig', [
            'user' => $user
        ])->setTitle('Example'));
    }

    /**
     * Process the form vars
     */
    public function postExample()
    {
        try {
            $user = $this->getUserMapper()->findById($this->getRequest()->post['id']);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@shaps/submit.twig', [
            'user' => $user,
            'var1' => $this->getRequest()->post['var1']
        ])->setTitle('Example Form Output'));
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