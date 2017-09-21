<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\API\Help;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;


class CourseParticipationController extends Base
{

    /**
     * @param string $schoolCode
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function getCourseList($schoolCode)
    {
        /** @var IConnector $db */
        $db = $this->getDi()->get('databaseInternal');
        $uam = UserAccessMapper::getInstance();

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

        $privateKey = $db->executeQuery('SELECT access_token FROM site_access WHERE site_school_code = ?', [ $schoolCode ])->fetchColumn();

        if (empty($privateKey)) {
            $rs->status->set(400);

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
            $folder = Folder\Mapper::getInstance()->findById(
                Folder\Code\Mapper::getInstance()->findOneByColumn('code', $schoolCode)->getFolder()->getId()
            );

        } catch (\Schoolbox\Mapper\Exception $e) {

            // TODO replace with error message
            $rs->content->set($this->buildJsonOutput('No folder with synergy code of '.$schoolCode));
            return;
        }

        $payload = [];

        // obtain all direct descendants of this folder
        $courses = Folder\Mapper::getInstance()->findByParentId($folder->getId());


        foreach ($courses as $course) {
            $participants = $uam->findUsersInCategory($course)->getColumnValues('email');

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