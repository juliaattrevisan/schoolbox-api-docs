<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Profile\Field;

use DebugBar\DebugBarException;
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
     * @return array
     * @throws DebugBarException
     * @throws Exception
     * @throws \DomainException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function produce(\Schoolbox\User $viewee, \Schoolbox\User $viewer): array
    {
        $payload = [];

        /* Data about $viewee that we'll put through the $renderableFields list
         */
        if ($viewee->getRole()->isStudent()) {
            $referenceFields = $this->generateStudentReferenceFields($viewee);
        } elseif ($viewee->getRole()->isParent()) {
            $referenceFields = $this->generateParentReferenceFields($viewee);
        } elseif ($viewee->getRole()->isStaff()) {
            $referenceFields = $this->generateStaffReferenceFields($viewee);
        } else {
            throw new \DomainException('Viewee has an unknown role type');
        }

        /* Fields that $viewer can actually see on screen
         */
        $renderableFields = $this->fieldMapper->findRenderableFields($viewee, $viewer);

        /* The preprocessed payload that frontend can just use
         */
        return $payload;
    }


    /**
     * This is a hybrid data struct containing all the information about $viewee.
     * This has schoolbox.user specific fields like pronoun and campus etc.
     * combined with whatever the External_UserInterface fields contain. This method
     * returns a loosely-typed array struct that you can map directly to the
     * list of renderable fields as determined by admin.
     *
     * @param User $viewee
     * @return array
     */
    public function generateReferenceFields(\Schoolbox\User $viewee): array
    {
    }


    /**
     * Preprocesses $viewee (a student) and their corresponding External_UserInterface array
     *
     * This is a hybrid data struct containing all the information about $viewee.
     * This has schoolbox.user specific fields like pronoun and campus etc.
     * combined with whatever the External_UserInterface fields contain. This method
     * returns a loosely-typed array struct that you can map directly to the
     * list of renderable fields as determined by admin.
     *
     * @param User $viewee
     * @return array
     */
    public function generateStudentReferenceFields(\Schoolbox\User $viewee): array
    {
    }


    /**
     * Preprocesses $viewee (a parent) and their corresponding External_UserInterface array
     *
     * This is a hybrid data struct containing all the information about $viewee.
     * This has schoolbox.user specific fields like pronoun and campus etc.
     * combined with whatever the External_UserInterface fields contain. This method
     * returns a loosely-typed array struct that you can map directly to the
     * list of renderable fields as determined by admin.
     *
     * @param User $viewee
     * @return array
     */
    public function generateParentReferenceFields(\Schoolbox\User $viewee): array
    {
    }


    /**
     * Preprocesses $viewee (a staff) and their corresponding External_UserInterface array
     *
     * This is a hybrid data struct containing all the information about $viewee.
     * This has schoolbox.user specific fields like pronoun and campus etc.
     * combined with whatever the External_UserInterface fields contain. This method
     * returns a loosely-typed array struct that you can map directly to the
     * list of renderable fields as determined by admin.
     *
     * @param User $viewee
     * @return array
     */
    public function generateStaffReferenceFields(\Schoolbox\User $viewee): array
    {
    }
}
