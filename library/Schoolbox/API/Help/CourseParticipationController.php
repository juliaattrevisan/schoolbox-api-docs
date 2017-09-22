<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\API\Help;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\API\Help\Token;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;


class CourseParticipationController extends Base
{
    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var Token\Mapper
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
     * @param Token\Mapper     $tokenMapper
     * @param Folder\Mapper    $folderMapper
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        Token\Mapper $tokenMapper,
        Folder\Mapper $folderMapper
    ) {
        $this->userAccessMapper = $userAccessMapper;
        $this->tokenMapper      = $tokenMapper;
        $this->folderMapper     = $folderMapper;
        $this->codeMapper       = $folderMapper->getJoinMapper('codes');
    }


    /**
     * @param string $schoolCode
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getCourseList($schoolCode)
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        $requestTimestamp = new Alaress_Date_Format($rq->query->get('ts', 0), 'U');

        $deltaTimestamp = abs(Alaress_Date_Format::now()->diffInSeconds($requestTimestamp));

        // permit a window of +/- 10 seconds to guard against replay
        if ($deltaTimestamp > 100) { // TODO change back to 10 seconds
            $rs->status->set(400);

            // TODO replace with error message
            $rs->content->set($this->buildJsonOutput(Alaress_Date_Format::now()->getUnixTimeStamp()));
            return;
        }

        $authDigest = $rq->query->get('token', '');

        try {
            $privateKey = $this->tokenMapper->findById($schoolCode)->getAccessToken();
        } catch (Mapper\Exception $e) {

            // TODO replace with error message
            $rs->content->set($this->buildJsonOutput('Try ecc'));
            return;
        }

        $expectedToken = $this->computeAccessToken(
            $requestTimestamp,
            $schoolCode,
            $privateKey
        );

        if ($expectedToken !== $authDigest) {
            $rs->status->set(403);

            // TODO replace with error message
            $rs->content->set($this->buildJsonOutput([
                Alaress_Date_Format::now()->getUnixTimeStamp(),
                $expectedToken
            ]));
            return;
        }


        // ok, if you're here, you can see the thing.
        try {
            $folder = $this->folderMapper->findById(
                $this->codeMapper->findOneByColumn('code', $schoolCode)->getFolder()->getId()
            );

        } catch (Mapper\Exception $e) {
            // TODO replace with error message
            $rs->content->set($this->buildJsonOutput('No folder with synergy code of '.$schoolCode));
            return;
        }

        $payload = [];

        // obtain all direct descendants of this folder
        $courses = $this->folderMapper->findByParentId($folder->getId());


        foreach ($courses as $course) {
            $participants = $this->userAccessMapper->findUsersInCategory($course)->getColumnValues('email');

            $payload[] = [
                'name'         => $course->getName(),
                'id'           => $course->getId(),
                'participants' => $participants
            ];
        }

        $rs->content->set($this->buildJsonOutput($payload));

//        $rs->content->set($this->buildJsonOutput([
//            'rqTime'    => (int)$requestTimestamp->getUnixTimeStamp(),
//            'timestamp' => (int)Alaress_Date_Format::now()->getUnixTimeStamp(),
//            'delta'     => (int)$deltaTimestamp,
//            'expected'  => $expectedToken,
//            'actual'    => $authDigest,
//            'match'     => $expectedToken === $authDigest
//        ]));
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
}