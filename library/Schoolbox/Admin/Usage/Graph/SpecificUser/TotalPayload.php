<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Usage\Graph\SpecificUser;

use Alaress\Database\Connector\IConnector;
use Schoolbox\User;


/**
 * Class TotalPayload
 *
 * @package Schoolbox\Admin\Usage\Graph
 */
class TotalPayload
{

    /**
     * @var IConnector
     */
    private IConnector $database;


    /**
     * System constructor.
     *
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }


    /**
     * @param User $user
     * @return array
     */
    public function generate(User $user): array
    {
        $total = disk_total_space(\Config::get('storage_dir'));
        $free  = disk_free_space(\Config::get('storage_dir'));
        $used  = $total - $free;

        return [[
            'label' => 'Total',
            'size' => round(100 * $used / ($used + $free))
        ]];
    }
}