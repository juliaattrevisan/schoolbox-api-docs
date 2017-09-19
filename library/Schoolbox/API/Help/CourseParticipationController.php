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


class CourseParticipationController extends Base
{

    /**
     * @param string $schoolCode
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getCourseList($schoolCode)
    {
        /** @var IConnector $db */
        $db = $this->getDi()->get('databaseInternal');

        $rq = $this->getRequest();
        $rs = $this->getResponse();

        $requestTimestamp = new Alaress_Date_Format($rq->query->get('ts', 0), 'U');

        $deltaTimestamp = abs(Alaress_Date_Format::now()->diffInSeconds($requestTimestamp));

        // permit a window of +/- 10 seconds to guard against replay
        if ($deltaTimestamp > 10) {
            $rs->status->set(400);
            return;
        }

        $authDigest = $rq->query->get('token', '');

        $expectedToken = $this->computeAccessToken(
            $requestTimestamp,
            $schoolCode,
            $db->executeQuery('SELECT access_token FROM site_access WHERE site_school_code = ?', [ $schoolCode ])->fetchColumn()
        );

        $rs->content->set($this->buildJsonOutput($expectedToken === $authDigest));
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