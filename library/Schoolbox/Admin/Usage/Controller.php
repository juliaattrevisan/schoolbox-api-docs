<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Usage;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;

/**
 * Class Controller
 *
 * @package Schoolbox\Admin\Usage
 */
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
     * Container for all the graphs and tables
     */
    public function getIndex(): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminUsage/index.twig', []));
    }


    /**
     * Generate payload for System Usage
     */
    public function getSystemPayload(): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        $payload = (new Graph\SystemPayload($database))->generate();

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for Role Usage
     */
    public function getRolePayload(): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        $payload = (new Graph\RolePayload($database))->generate();

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for Total Usage
     */
    public function getTotalPayload(): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        $payload = (new Graph\TotalPayload($database))->generate();

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for User Usage
     */
    public function getUserPayload(): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        $payload = (new Graph\UserPayload($database))->generate();

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }
}
