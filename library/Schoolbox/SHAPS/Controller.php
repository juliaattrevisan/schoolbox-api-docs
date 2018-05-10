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

    public function getProvisionmentStatusList() {
        // All the staff in our Schoolbox instance
        $allStaff = $this->userMapper->findByColumn('g.group_type', 'staff')->sortBy("lastname", "asc");

        // TODO this will be all the staff on Help
        $provisionedStaff = $this->userMapper->findByColumn('g.group_type', 'staff')->slice($allStaff->count()/2);




        // TODO make this config stuff 'right' later
        $config = new \Schoolbox\Config\Loader();
        $schoolCode = $config->get('site_school_code');

        $client = new GuzzleClient();

        $ip = "http://192.168.36.10";
        $timestamp = Alaress_Date_Format::now();
        $token = sha1('ecc');

        $response = $client->request(
            "get",
            "${ip}/api/course-participation/${schoolCode}/users",
            ['query' =>
                ['ts' => $timestamp, 'token' => $token]
            ]
        );

        $body = $response->getBody()->getContents();

        $provisionedUserIDs = [];
        foreach($allStaff as $user) {
            if ($provisionedStaff->contains($user)) {
                $provisionedUserIDs[] = $user->getID();
            }
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@shaps/index.twig', [
                'allUsers' => $allStaff,
                'provisionedUserIDs' => $provisionedUserIDs
            ])->setTitle('Example')
        );
    }

    public function searchUsers($userID) {
        $user = $this->userMapper->findByExternalId($userID);
        $userJSON = [
            'name' => $user->getFullName(),
            'id' => $user->getId(),
            'externalID' => $user->getExternalId(),
        ];
        header('Content-Type: text/json');
        echo json_encode($userJSON);

        /* Prevent rendering of template */
        exit();
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