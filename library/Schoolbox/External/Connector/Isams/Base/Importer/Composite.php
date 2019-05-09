<?php
/**
 * Composite
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Schoolbox\Collection;
use Schoolbox\External\Connector\Isams\Base\Importer;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\External\UserRelationMapper;
use Schoolbox\UserMapper;

class Composite implements ImporterInterface
{

    /**
     * @var Collection
     */
    private $registry;


    /**
     * @var Batchxml
     */
    private $batchxml;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var UserProfileMapper
     */
    private $userProfileMapper;


    /**
     * @var
     */
    private $userRelationMapper;


    /**
     * Composite constructor.
     *
     * @param Batchxml           $batchxml
     * @param UserMapper         $userMapper
     * @param UserProfileMapper  $userProfileMapper
     * @param UserRelationMapper $userRelationMapper
     */
    public function __construct(
        Batchxml $batchxml,
        UserMapper $userMapper,
        UserProfileMapper $userProfileMapper,
        UserRelationMapper $userRelationMapper
    )
    {
        $this->registry = new Collection();
        $this->batchxml = $batchxml;
        $this->userMapper = $userMapper;
        $this->userProfileMapper = $userProfileMapper;
        $this->userRelationMapper = $userRelationMapper;
    }


    /**
     * Execute each of the strategies
     */
    public function import(): void
    {
        $this->registry
            ->add(new Importer\Profile($this->batchxml, $this->userMapper, $this->userProfileMapper))
            ->add(new Importer\Relations($this->batchxml, $this->userMapper, $this->userRelationMapper));

        foreach ($this->registry as $strategy) {
            $strategy->import();
        }
    }
}