<?php
/**
 * Batchxml
 *
 * ISAMS Batch File processor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\UPS;

use Schoolbox\Config\Loader;
use Schoolbox\External\Connector\ISync;
use Schoolbox\External\Normalise\User;


class Batchxml implements ISync
{

    /**
     * @var Loader
     */
    private $config;


    /**
     * Batchxml constructor.
     */
    public function __construct()
    {
        $this->config = new Loader();
    }

    /**
     * Gets a collection of normalized student objects.
     *
     * This method should return an iterator instead of an array, for memory
     * efficiency reasons.
     *
     * @return User\Student[]
     */
    public function findAllStudents(): array
    {
        $host = $this->config->get('external_ups_provider_batchxml_host');
        $key = $this->config->get('external_ups_provider_batchxml_key');

        /* Faster, for now
         */
//        $ba = new Batchxml\Api($host, $key);
//        $result = $ba->retrieveDocument();

        $result = file_get_contents('../library/Schoolbox/External/UPS/Batchxml/temp.xml');





        return [];
    }


    /**
     * Gets a collection of normalized staff objects.
     *
     * This method should return an iterator instead of an array, for memory
     * efficiency reasons.
     *
     * @return User\Staff[]
     */
    public function findAllStaff()
    {
        // TODO: Implement findAllStaff() method.
    }


    /**
     * Gets a collection of normalized parent objects.
     *
     * This method should return an iterator instead of an array, for memory
     * efficiency reasons.
     *
     * @return User\Guardian[]
     */
    public function findAllParents()
    {
        // TODO: Implement findAllParents() method.
    }
}