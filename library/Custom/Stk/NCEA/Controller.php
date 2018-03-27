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
$rawData = [];
        if (\count($rawData['records'])) {
            $aggregatedData = (new Aggregation())->aggregate($rawData, $year);

            $this->getResponse()->content->set($this->buildHtmlOutput('@nceaReport/report.twig', [
                'user' => $user,
                'data' => $aggregatedData,
            ])->setTitle("NCEA Report for {$aggregatedData['meta']['fullname']}"));
        }
        else {
            $this->getResponse()->content->set($this->buildHtmlOutput('@nceaReport/no-report.twig', [
                'user' => $user,
            ])->setTitle("NCEA Report for {$user->getFullName()}"));
        }
    }
}