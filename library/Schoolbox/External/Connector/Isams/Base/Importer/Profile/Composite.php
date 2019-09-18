<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer\Profile;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\External\Connector\Isams\Base\Importer;
use Schoolbox\External\Profile as UserProfile;
use Zend\Uri\Exception\InvalidUriPartException;

/**
 * Class Composite
 *
 * Strategy executor
 *
 * @package Schoolbox\External\Connector\Isams\Base\Importer
 */
class Composite implements Importer\ImporterInterface
{

    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var Importer\Batchxml
     */
    private $batchxml;


    /**
     * @var UserProfile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var UserProfile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var UserProfile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var UserProfile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var UserProfile\Relation\Mapper
     */
    private $relationMapper;


    /**
     * @var Collection
     */
    private $registry;


    /**
     * Composite constructor.
     *
     * @param LoggerInterface             $log
     * @param bool                        $perform
     * @param Importer\Batchxml           $batchxml
     * @param UserProfile\Mapper          $baseProfileMapper
     * @param UserProfile\Student\Mapper  $studentProfileMapper
     * @param UserProfile\Parent\Mapper   $parentProfileMapper
     * @param UserProfile\Staff\Mapper    $staffProfileMapper
     * @param UserProfile\Relation\Mapper $relationMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Importer\Batchxml $batchxml,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relation\Mapper $relationMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->registry = new Collection();
        $this->batchxml = $batchxml;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationMapper = $relationMapper;
    }


    /**
     * Execute each of the strategies
     *
     * @throws \RuntimeException
     * @throws InvalidUriPartException
     * @throws InvalidArgumentException
     */
    public function import(): void
    {
        $this->registry
            ->add(new Importer\Profile\Profile(
                $this->log,
                $this->perform,
                $this->batchxml,
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationMapper
            ));

        foreach ($this->registry as $strategy) {
            $strategy->import();
        }
    }
}
