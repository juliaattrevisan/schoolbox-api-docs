<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Usage\Graph\GlobalUsers;

use Alaress\Database\Connector\IConnector;


/**
 * Class TotalPayload
 *
 * @package Schoolbox\Admin\Usage\Graph\GlobalUsers
 */
class TotalPayload
{

    /**
     * @var IConnector
     */
    private IConnector $database;


    /**
     * @var string
     */
    private string $storageDir;


    /**
     * System constructor.
     *
     * @param IConnector $database
     * @param string     $storageDir
     */
    public function __construct(IConnector $database, string $storageDir)
    {
        $this->database = $database;
        $this->storageDir = $storageDir;
    }


    /**
     * Find out how much space was used out of what could be used,
     * expressed as a percentage
     *
     * @return array
     */
    public function generate(): array
    {
        $total = disk_total_space($this->storageDir);
        $free  = disk_free_space($this->storageDir);
        $used  = $total - $free;

        return [[
            'label' => 'Total',
            'size' => round(100 * $used / ($used + $free))
        ]];
    }
}