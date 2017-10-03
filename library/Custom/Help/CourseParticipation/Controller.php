<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\CourseParticipation;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
use Schoolbox\CampusMapper;
use Schoolbox\Permission\SiteAccess;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use UnexpectedValueException;


class Controller extends Base
{
    const PARTICIPANT_ROLE_NAME = 'School Staff';
    const PARTICIPANT_CAMPUS    = 1;

    const REQUIRED_USER_FIELDS = [
        'id', 'email', 'title', 'firstname', 'lastname'
    ];

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var SiteAccess\Mapper
     */
    private $tokenMapper;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var Folder\Code\Mapper
     */
    private $codeMapper;


    /**
     * @param UserAccessMapper $userAccessMapper
     * @param SiteAccess\Mapper     $tokenMapper
     * @param Folder\Mapper    $folderMapper
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        SiteAccess\Mapper $tokenMapper,
        Folder\Mapper $folderMapper
    ) {
        $this->userAccessMapper = $userAccessMapper;
        $this->tokenMapper      = $tokenMapper;
        $this->folderMapper     = $folderMapper;
        $this->codeMapper       = $folderMapper->getJoinMapper('codes');
    }


    /**
     * @api   /api/course-participation/apitest
     * @level 170
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getApiTest()
    {
        /** @var SiteAccess\Model[] $codes */
        $codes = $this->tokenMapper->findAll();

        $now = Alaress_Date_Format::now();

        $data = [
            'codes'  => $codes,
            'ts'     => $now->getUnixTimeStamp(),
            'hashes' => [],
            'fields' => self::REQUIRED_USER_FIELDS
        ];

        foreach ($codes as $code) {
            $school = $code->getSiteSchoolCode();
            $data['hashes'][$school] = $this->computeAccessToken($now, $school, $code->getAccessToken());
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@helpCourseParticipation/apitest.twig', $data));
    }


    /**
     * @api   POST /api/course-participation/apitest
     * @level 170
     */
    public function postApiTest()
    {
        $post = $this->getRequest()->post->getArrayCopy();

        $school = $post['schoolCode'];

        $code = $this->tokenMapper->findById($school);

        $now = Alaress_Date_Format::now();

        $uri = $post['host'] . $this->getRouteIfAuthorized('helpCourseParticipation.postUserRegistrations', [ 'schoolCode' => $school ]);

        $c = new Client();

        unset($post['schoolCode'], $post['host']);

        try {
            $c->post($uri, [
                'query' => [
                    'ts'    => $now->getUnixTimeStamp(),
                    'token' => $this->computeAccessToken($now, $school, $code->getAccessToken())
                ],
                'form_params' => $post
            ]);

            $this->getResponse()->redirect->afterPost(
                $this->getRouteIfAuthorized('helpCourseParticipation.getApiTest')
            );

        } catch (BadResponseException $e) {
            $response = $e->getResponse();

            $content = $this->buildFileOutputFromString($response->getBody()->getContents());

            $this->getResponse()->status->set($response->getStatusCode());
            $this->getResponse()->content->set($content);
            $this->getResponse()->headers->set('Content-Type', 'text/html');
        }


    }


    /**
     * @param string $schoolCode
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getFolderAccessBySchool($schoolCode)
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        $requestTimestamp = new Alaress_Date_Format($rq->query->get('ts', 0), Alaress_Date_Format::TIMESTAMP);

        $authDigest = $rq->query->get('token', '');

        $responseCode = $this->getResponseCode($schoolCode, $requestTimestamp, $authDigest);

        if ($responseCode !== 200) {
            // if the request didn't pass the gauntlet, bounce the request
            $this->sendGenericStatus($responseCode);
            return;
        }


        // ok, if you're here, you can see the thing.
        try {
            $folder = $this->folderMapper->findById(
                $this->codeMapper->findOneByColumn('code', $schoolCode)->getFolder()->getId()
            );

        } catch (Mapper\Exception $e) {
            $this->sendGenericStatus(404);
            return;
        }

        $payload = [];

        // obtain all direct descendants of this folder
        // the scope of implementation is immediate children only, rather than attempting to gather all descendants
        $courses = $this->folderMapper->findByParentId($folder->getId());


        foreach ($courses as $course) {
            $participants = $this->userAccessMapper->findUsersInCategory($course)->getColumnValues('email');

            $payload[] = [
                'folderName'   => $course->getName(),
                'folderId'     => $course->getId(),
                'participants' => array_filter($participants)
            ];
        }

        $rs->content->set($this->buildJsonOutput($payload));
    }


    /**
     * @api   /api/course-participation/{schoolCode}/users
     * @level 170
     *
     * @param string $schoolCode
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getUsersBySchool($schoolCode)
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        $requestTimestamp = new Alaress_Date_Format($rq->query->get('ts', 0), Alaress_Date_Format::TIMESTAMP);

        $authDigest = $rq->query->get('token', '');

        $responseCode = $this->getResponseCode($schoolCode, $requestTimestamp, $authDigest);

        if ($responseCode !== 200) {
            // if the request didn't pass the gauntlet, bounce the request
            $this->sendGenericStatus($responseCode);
            return;
        }

        // show the list of users for this school
        // filter to the list of users whose external IDs are prefixed with the given school code
        $predicate = $this->getUserMapper()->getPredicateBuilder()->like('synergy_id', "{$schoolCode}.%");

        // we only need the external ID itself, so bypass object hydration
        $externalIds = $this->getUserMapper()->findScalarByPredicates('synergy_id', $predicate);

        $unprefixed = array_map(function ($id) use ($schoolCode) {
            return str_replace("{$schoolCode}.", '', $id);
        }, $externalIds);

        $rs->content->set($this->buildJsonOutput($unprefixed));
    }


    /**
     * @api   POST /api/course-participation/{schoolCode}/users
     * @level 170
     *
     * @param string $schoolCode
     * @throws Mapper\DefinitionException
     */
    public function postUserRegistrations($schoolCode)
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        try {
            $participantRole     = $this->getRoleMapper()->findOneByColumn('name', self::PARTICIPANT_ROLE_NAME);
            $participantCampuses = $this->getCampusMapper()->findByColumn('id', self::PARTICIPANT_CAMPUS);

        } catch (Mapper\Exception $e) {
            // if this role doesn't exist, that's *bad* bad
            $this->sendGenericStatus(500);
            return;
        }

        $requestTimestamp = new Alaress_Date_Format($rq->query->get('ts', 0), 'U');

        $authDigest = $rq->query->get('token', '');

        $responseCode = $this->getResponseCode($schoolCode, $requestTimestamp, $authDigest);

        if ($responseCode !== 200) {
            // if the request didn't pass the gauntlet, bounce the request
            $this->sendGenericStatus($responseCode);
            return;
        }

        // process the payload
        $post = $rq->post;

        if (empty($post['user'])) {
            // empty payload: do nothing
            $this->sendGenericStatus(400);
            return;
        }

        $failedUsers = [];

        foreach ($post['user'] as $userData) {

            if (!$this->userPayloadIsValid($userData)) {
                $failedUsers[] = $userData;
                continue;
            }

            // user uniqueness is looked up by external ID
            $externalId = "{$schoolCode}.{$userData['id']}";

            try {
                $user = $this->getUserMapper()->findOneByColumn('synergy_id', $externalId);

            } catch (Mapper\Exception\Deleted $e) {
                // user exists, but is deleted; consider this a fail case
                $failedUsers[] = $userData;
                continue;

            } catch (Mapper\Exception $e) {
                // user does not exist; create the model
                $user = $this->getUserMapper()->createModel()
                    ->setRole($participantRole)
                    ->setCampusCollection($participantCampuses)
                    ->setLocalEmail(false)
                    ->setWebmail(false)
                    ->setUseInternalMail(false)
                    ->setEportfolioEnable(false)
                ;
            }

            // update user object
            $user
                ->setUsername($userData['email'])
                ->setTitle($userData['title'])
                ->setFirstname($userData['firstname'])
                ->setLastname($userData['lastname'])
                ->setAltEmail($userData['email'])
                ->setEnabled(true)
                ->setExternalId($externalId)
            ;

            try {
                $this->getUserMapper()->save($user);

            } catch (Mapper\Exception $e) {
                // the user failed to save; is fail case
                $failedUsers[] = $userData;

            } catch (UnexpectedValueException $e) {
                // duplicate username
                $failedUsers[] = $userData;

            }
        }

        if (count($failedUsers)) {
            // just blame the client :P
            $this->sendGenericStatus(400);
            $rs->content->set($this->buildJsonOutput($failedUsers));

        } else {
            // everything went fine, nothing to respond with
            $this->sendGenericStatus(204);
            $rs->content->set($this->buildJsonOutput(null));
        }

    }


    /**
     * @param array $userData
     * @return bool
     */
    private function userPayloadIsValid(array $userData)
    {
        // check all the necessary data is present
        foreach (self::REQUIRED_USER_FIELDS as $required) {
            if (!array_key_exists($required, $userData) || empty(trim($userData[$required]))) {
                return false;
            }
        }

        return true;
    }


    /**
     * @param string              $schoolCode
     * @param Alaress_Date_Format $requestTimestamp
     * @param string              $authDigest
     * @return int
     */
    private function getResponseCode($schoolCode, Alaress_Date_Format $requestTimestamp, $authDigest)
    {
        $deltaTimestamp = abs(Alaress_Date_Format::now()->diffInSeconds($requestTimestamp));

        // permit a window of +/- 10 seconds to guard against replay
        if ($deltaTimestamp > 10) {
            return 400;
        }

        try {
            $privateKey = $this->tokenMapper->findById($schoolCode)->getAccessToken();
        } catch (Mapper\Exception $e) {
            return 401;
        }

        $expectedToken = $this->computeAccessToken(
            $requestTimestamp,
            $schoolCode,
            $privateKey
        );

        if ($expectedToken !== $authDigest) {
            return 403;
        }

        return 200;
    }


    /**
     * @param string $timestamp
     * @param string $schoolCode
     * @param string $accessToken
     * @return string
     */
    private function computeAccessToken($timestamp, $schoolCode, $accessToken)
    {
        return sha1(sprintf(':%s-%s-%s:', $timestamp, $schoolCode, $accessToken));
    }


    /**
     * @param $code
     * @return void
     */
    private function sendGenericStatus($code)
    {
        $r = new Response($code);

        $this->getResponse()->status->set($r->getStatusCode());
        $this->getResponse()->content->set($this->buildJsonOutput($r->getReasonPhrase()));
    }


    /**
     * @return UserMapper|Mapper
     * @throws Mapper\DefinitionException
     */
    private function getUserMapper()
    {
        return $this->userAccessMapper->getJoinMapper('user');
    }


    /**
     * @return RoleMapper|Mapper
     * @throws Mapper\DefinitionException
     */
    private function getRoleMapper()
    {
        return $this->getUserMapper()->getJoinMapper('role');
    }


    /**
     * @return CampusMapper|Mapper
     * @throws Mapper\DefinitionException
     */
    private function getCampusMapper()
    {
        return $this->getUserMapper()->getJoinMapper('userCampus');
    }
}
