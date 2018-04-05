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
     * NCEA Report
     *
     * @param User $user
     * @throws \Alaress\Database\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getReport(User $user)
    {
        /** @var IConnector $externalDb */
        $externalDb = $this->getDi()->get('databaseExternal');

        $acquisition = (new Acquisition($externalDb, $user->getExternalId()))->getRecords((string)date('Y'));

        $this->getResponse()->content->set($this->buildHtmlOutput('@nceaReport/report.twig', [
            'user' => $user
        ])->setTitle("NCEA Report for {$user->getFullName()}"));
    }
}