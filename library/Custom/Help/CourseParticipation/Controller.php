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
use GuzzleHttp\Psr7\Response;
use Schoolbox\Permission\SiteAccess;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;


class Controller extends Base
{
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
            'hashes' => []
        ];

        foreach ($codes as $code) {
            $school = $code->getSiteSchoolCode();
            $data['hashes'][$school] = $this->computeAccessToken($now, $school, $code->getAccessToken());
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@helpCourseParticipation/apitest.twig', $data));
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
        /** @var UserMapper $userMapper */
        $userMapper = $this->userAccessMapper->getJoinMapper('user');

        // filter to the list of users whose external IDs are prefixed with the given school code
        $predicate = $userMapper->getPredicateBuilder()->like('synergy_id', "{$schoolCode}.%");

        // we only need the external ID itself, so bypass object hydration
        $externalIds = $userMapper->findScalarByPredicates('synergy_id', $predicate);

        $unprefixed = array_map(function ($id) use ($schoolCode) {
            return str_replace("{$schoolCode}.", '', $id);
        }, $externalIds);

        $rs->content->set($this->buildJsonOutput($unprefixed));
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

}