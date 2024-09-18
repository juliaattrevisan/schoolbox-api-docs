<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Profile\Field;

use DebugBar\DebugBarException;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

class Factory
{
    /**
     * Constructor
     *
     * @param \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper
     */
    public function __construct(private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper)
    {
    }


    /**
     * Find the fields that were enabled for $viewee by admin,
     * that $viewer was able to see,
     * ordered by sequence ascending
     *
     * @param User $viewee
     * @param User $viewer
     * @return GenericCollection
     * @throws DebugBarException
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    public function produce(\Schoolbox\User $viewee, \Schoolbox\User $viewer): GenericCollection
    {
        return $this->fieldMapper->findRenderableFields($viewee, $viewer);
    }
}
