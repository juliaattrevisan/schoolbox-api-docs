<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Custom\Stk\NCEA;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     */
    public function __construct(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }


    /**
     * NCEA Report for $year
     *
     * @param User $user
     * @param int  $year
     * @throws \Alaress\Database\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getReport(User $user, int $year)
    {
        /** @var IConnector $externalDb */
        $externalDb = $this->getDi()->get('databaseExternal');

        $rawData = (new Acquisition($externalDb, $user->getExternalId()))->getRecords($year);

        $aggregatedData = (new Aggregation())->aggregate($rawData, $year);

        /* Equivalent representation from either the custom view, or getStudent()
         *
         * User data only exists when there are records; user's data is duped per record
         *
         * If there are no records, we have to get it from somewhere else (specifically Tutor Group)
         */
        if (!\count($aggregatedData['records'])) {
            $externalUser = $this->getDi()->get('externalUser')->getStudent($user->getExternalId());

            $aggregatedData['meta'] = [
                'externalId' => $user->getExternalId(),
                'firstname' => $user->getFirstname(),
                'fullname' => $user->getFullName(),
                'tutorGroup' => $externalUser['StudentTutor'],
                'year' => $year,
            ];
        }

        /* Pass to twig to render nicely
         */
        $this->getResponse()->content->set($this->buildHtmlOutput('@nceaReport/report.twig', [
            'user' => $user,
            'data' => $aggregatedData,
        ])->setTitle("NCEA Report for {$user->getFullName()}"));
    }
}