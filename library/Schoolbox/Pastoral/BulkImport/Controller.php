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
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\RoleMapper;

/**
 * Module for bulk importation of pastoral records.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param RosettaStone            $translator            Required for translating values used in the page title.
     * @param Pastoral\Mapper         $pastoralMapper        Required for creating pastoral records.
     * @param Pastoral\Type\Mapper    $pastoralTypeMapper    Required for loading and validating pastoral types.
     * @param Pastoral\SubType\Mapper $pastoralSubTypeMapper Required for loading and validating pastoral subtypes.
     * @param RoleMapper              $roleMapper            Required for loading roles to show in record visibility.
     */
    public function __construct(
        private RosettaStone $translator,
        private Pastoral\Mapper $pastoralMapper,
        private Pastoral\Type\Mapper $pastoralTypeMapper,
        private Pastoral\SubType\Mapper $pastoralSubTypeMapper,
        private RoleMapper $roleMapper,
        private Pastoral\Access\Mapper $pastoralAccessMapper,
        private Pastoral\Group\Mapper $pastoralGroupMapper,
    ) {
    }

    /**
     * Display form for creating a pastoral bulk import.
     *
     * @api GET /pastoral/bulk-import
     * @since 24.1
     * @return void
     */
    public function getForm(): void
    {
        $allowIndividual = $this->getConfigLoader()->get('pastoral_individual_access');
        $allowParents  = $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED);
        $allowStudents = $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_STUDENT_ACCESS_ENABLED);

        $roles        = $this->roleMapper->createCollection();
        $allowedRoles = $this->pastoralAccessMapper->determineAllowedRoles();
        $roleUserCounts = [];
        $rolesWithUsers = [];

        foreach ($allowedRoles as $roleCurrent) {
            $rolesWithUsers[$roleCurrent->getId()] = false;
            $roleUserCounts[$roleCurrent->getId()] = 0;
            if ($roleCurrent->isStaff() && $roleCurrent->getAccessPastoral()) {
                $roles[] = $roleCurrent;
//                $roleUserCounts[$roleCurrent->getId()] = 0;
            }
            if ($allowParents && $roleCurrent->isParent()) {
                $roles[] = $roleCurrent;
            }
        }


        $this->response = $this->responseFromTemplate(
            '@pastoralBulkImport/form.twig',
            [
                'record'           => $this->pastoralMapper->createModel()->setStudent(null),
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
                'allowIndividual' => $allowIndividual,
                'allowParents' => $allowParents,
                'allowStudents' => $allowStudents,
                'roles' => $roles,
                'roleTypes'         => $this->pastoralAccessMapper->determineAllowedRoleTypes(),
                'groups'            => $this->pastoralGroupMapper->findAll()->sortNaturally('name'),
                'rolesWithUsers' => $rolesWithUsers,
                'roleUserCounts' => $roleUserCounts,
            ],
            $this->translator->translate('pastoral', capital: true) . ' Bulk Upload',
        );
    }
}
