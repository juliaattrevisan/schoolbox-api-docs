<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Schoolbox\GenericCollection;


/**
 * Interface IFactory
 *
 * @package Schoolbox\External\Importer\Endpoint
 */
interface IFactory
{
    /**
     * @param string|null $file
     * @param string      $type
     * @return null|GenericCollection
     */
    public function produce(?string $file, string $type): ?GenericCollection;
}