<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint\Specification;

interface ISpecification
{
    /**
     * Get a list of nodes that did (or didn't) get configured properly
     *
     * @return array
     */
    public function check(): array;
}
