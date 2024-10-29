<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

/**
 * Module for bulk importation of pastoral records.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param RosettaStone            $translator            Used for translating values used in the page title.
     * @param Pastoral\Mapper         $pastoralMapper        Used for creating pastoral records.
     * @param Pastoral\Type\Mapper    $pastoralTypeMapper    Used for loading and validating pastoral types.
     * @param Pastoral\SubType\Mapper $pastoralSubTypeMapper Used for loading and validating pastoral subtypes.
     * @param RoleMapper              $roleMapper            Used for loading roles to show in record visibility.
     * @param Pastoral\Access\Mapper  $pastoralAccessMapper  Used for determining roles which may access records.
     * @param Pastoral\Group\Mapper   $pastoralGroupMapper   Used for loading pastoral groups to select visibility.
     * @param UserMapper              $userMapper            Used for loading pastoral moderators.
     */
    public function __construct(
        private RosettaStone $translator,
        private Pastoral\Mapper $pastoralMapper,
        private Pastoral\Type\Mapper $pastoralTypeMapper,
        private Pastoral\SubType\Mapper $pastoralSubTypeMapper,
        private RoleMapper $roleMapper,
        private Pastoral\Access\Mapper $pastoralAccessMapper,
        private Pastoral\Group\Mapper $pastoralGroupMapper,
        private UserMapper $userMapper,
    ) {
    }

    /**
     * Display form for creating a pastoral bulk import.
     *
     * @return void
     * @since 24.1
     * @api   GET /pastoral/bulk-import
     */
    public function getForm(): void
    {
        // Requirements for loading pastoral visibility inputs
        $allowIndividual = $this->getConfigLoader()->get('pastoral_individual_access');
        $allowParents    = $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED);
        $allowStudents   = $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_STUDENT_ACCESS_ENABLED);

        // Load roles and empty counts to display on pastoral visibility
        $roles          = $this->roleMapper->createCollection();
        $roleUserCounts = [];
        $rolesWithUsers = [];
        $allowedRoles   = $this->pastoralAccessMapper->determineAllowedRoles();
        foreach ($allowedRoles as $roleCurrent) {
            $rolesWithUsers[$roleCurrent->getId()] = false;
            $roleUserCounts[$roleCurrent->getId()] = 0;
            if ($roleCurrent->isStaff() && $roleCurrent->getAccessPastoral()) {
                $roles[] = $roleCurrent;
            }
            if ($allowParents && $roleCurrent->isParent()) {
                $roles[] = $roleCurrent;
            }
        }

        $this->response = $this->responseFromTemplate(
            '@pastoralBulkImport/form.twig',
            [
                'types'            => $this->pastoralTypeMapper->findAll(),
                'subTypes'         => $this->pastoralSubTypeMapper->findAll(),
                'reportAttachzone' => (new Zone('report'))
                    ->setThumbnailSize(64)
                    ->setModalTitle('Select report ZIP')
                    ->setAttachmentsLabel('')
                    ->setAttachPrompt('Upload report ZIP')
                    ->setMimeFilter('application/zip')
                    ->setReadOnly(false)
                    ->setFileVar('report')
                    ->setMulti(false)
                    ->getTwigContext(),
                // Requirements for displaying visibility inputs
                'record'           => $this->pastoralMapper->createModel()->setStudent(null),
                'allowIndividual'  => $allowIndividual,
                'allowParents'     => $allowParents,
                'allowStudents'    => $allowStudents,
                'roles'            => $roles,
                'roleTypes'        => $this->pastoralAccessMapper->determineAllowedRoleTypes(),
                'groups'           => $this->pastoralGroupMapper->findAll()->sortNaturally('name'),
                'rolesWithUsers'   => $roleUserCounts,
                'roleUserCounts'   => $rolesWithUsers,
                'moderatorIds'     => $this->userMapper->findByAccess(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ)
                                                       ->getPropertyValues('id'),
            ],
            $this->translator->translate('pastoral', capital: true) . ' Bulk Upload',
        );
    }
}
