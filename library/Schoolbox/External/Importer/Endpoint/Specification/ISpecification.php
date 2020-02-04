<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint\Specification;

/**
 * Interface ISpecification
 *
 * @package Schoolbox\External\Importer\Endpoint\Specification
 */
interface ISpecification
{

    /**
     * Status text for existing nodes
     */
    public const STATUS_OK = 'OK';


    /**
     * Status text for missing nodes
     */
    public const STATUS_MISSING = 'MISSING';


    /**
     * Status text for existing nodes that are empty but probably should contain something
     */
    public const STATUS_EMPTY = 'EMPTY';


    /**
     * Get a list of nodes that did (or didn't) get configured properly
     *
     * @return array
     */
    public function check(): array;
}
