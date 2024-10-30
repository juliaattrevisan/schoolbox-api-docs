<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use Alaress\Web\Controller\Base;
use League\Flysystem\FilesystemException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\TokenReplacer\Simple;
use Schoolbox\User;
use Schoolbox\UserMapper;
use ZipArchive;

/**
 * Controller for bulk importation of pastoral records.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param RosettaStone             $translator             Used for translating values used in the page title.
     * @param Pastoral\Mapper          $pastoralMapper         Used for creating pastoral records.
     * @param Pastoral\Type\Mapper     $pastoralTypeMapper     Used for loading and validating pastoral types.
     * @param Pastoral\SubType\Mapper  $pastoralSubTypeMapper  Used for loading and validating pastoral subtypes.
     * @param RoleMapper               $roleMapper             Used for loading roles to show in record visibility.
     * @param Pastoral\Access\Mapper   $pastoralAccessMapper   Used for determining roles which may access records.
     * @param Pastoral\Group\Mapper    $pastoralGroupMapper    Used for loading pastoral groups to select visibility.
     * @param UserMapper               $userMapper             Used for loading pastoral moderators.
     * @param Pastoral\Audience\Mapper $pastoralAudienceMapper Used for loading audiences for types and subtypes.
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
        private Pastoral\Audience\Mapper $pastoralAudienceMapper,
        private FilesystemProxy $filesystemProxy,
    ) {
    }

    /**
     * Display form for creating a pastoral bulk import.
     *
     * @api   GET /pastoral/bulk-import
     * @since 24.1
     *
     * @return void
     */
    public function getForm(): void
    {
        // Requirements for loading pastoral visibility inputs
        // TODO extract elsewhere, and use in pastoral modify form as well
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

        // Requirements for updating selections on change
        // TODO extract elsewhere, and use in pastoral modify form as well
        $types            = $this->pastoralTypeMapper->findAll();
        $subtypes         = $this->pastoralSubTypeMapper->findAll();
        $audiences        = $this->pastoralAudienceMapper->findAll()->filter($this->pastoralAudienceMapper->getPermissionCallback());
        $typeAudiences    = [];
        $subtypeAudiences = [];
        foreach ($audiences as $audience) {
            if ($audience->getSubtype()) {
                $subtypeAudiences[$audience->getSubtype()->getId()][] = $audience;
            } else {
                $typeAudiences[$audience->getType()->getId()][] = $audience;
            }
        }

        $typesIndex        = [];
        $typesIndexPrivate = [];
        $audienceDefaults  = [];
        foreach ($types as $type) {
            $typesIndex[$type->getId()]        = $type->getTemplate();
            $typesIndexPrivate[$type->getId()] = $type->getTemplatePrivate();
            if (key_exists($type->getId(), $typeAudiences) || $type->getVisibilityLocked()) {
                $audienceDefaults[] = [
                    'typeId'    => $type->getId(),
                    'subtypeId' => 0,
                    'audiences' => $typeAudiences[$type->getId()] ?? [],
                ];
            }
        }

        $subtypesIndex        = [];
        $subtypesIndexPrivate = [];

        foreach ($subtypes as $subtype) {
            $subtypesIndex[$subtype->getId()]        = $subtype->getTemplate();
            $subtypesIndexPrivate[$subtype->getId()] = $subtype->getTemplatePrivate();
            if (key_exists($subtype->getId(), $subtypeAudiences) || $subtype->getVisibilityLocked()) {
                $audienceDefaults[] = [
                    'typeId'    => $subtype->getPastoralType()->getId(),
                    'subtypeId' => $subtype->getId(),
                    'audiences' => $subtypeAudiences[$subtype->getId()] ?? [],
                ];
            }
        }

        $this->response = $this->responseFromTemplate(
            '@pastoralBulkImport/form.twig',
            [
                // Base form requirements
                'types'            => $types,
                'subTypes'         => $subtypes,
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
                'returnUrl'         => $this->requestQuery->get('returnUrl')
                    ?? $this->routeGenerator->generate('pastoral.getIndex'),

                // Requirements for displaying visibility inputs
                'record'          => $this->pastoralMapper->createModel()->setStudent(null),
                'allowIndividual' => $allowIndividual,
                'allowParents'    => $allowParents,
                'allowStudents'   => $allowStudents,
                'roles'           => $roles,
                'roleTypes'       => $this->pastoralAccessMapper->determineAllowedRoleTypes(),
                'groups'          => $this->pastoralGroupMapper->findAll()->sortNaturally('name'),
                'rolesWithUsers'  => $roleUserCounts,
                'roleUserCounts'  => $rolesWithUsers,
                'moderatorIds'    => $this->userMapper->findByAccess(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ)
                                                      ->getPropertyValues('id'),

                // Requirements for displaying scripts
                'audiences'            => $audienceDefaults,
                'typesIndexPrivate'    => $typesIndexPrivate,
                'typesIndex'           => $typesIndex,
                'subtypesIndexPrivate' => $subtypesIndexPrivate,
                'subtypesIndex'        => $subtypesIndex,
            ],
            $this->translator->translate('pastoral', capital: true) . ' Bulk Import',
        );
    }

    /**
     * Display preview of pastoral bulk import, after having posted the form
     * above.
     *
     * @api   POST /pastoral/bulk-import/preview
     * @since 24.1
     *
     * @return void
     */
    public function postImportPreview(): void
    {
        // Parse the uploaded ZIP file
        $zipFile = $this->loadStorageZipFile();
        $archive = $this->createLocalZipArchive($zipFile);
        $files   = $this->associateFilesWithUser($archive);

        $this->response = $this->responseFromTemplate(
            '@pastoralBulkImport/importPreview.twig',
            [
                'files'     => $files,
                'returnUrl' => $this->requestQuery->get('returnUrl')
                    ?? $this->routeGenerator->generate('pastoral.getIndex'),
                'formVars'  => $this->requestPost->getArrayCopy(),
            ],
        );
    }

    /**
     * Actually perform the pastoral bulk import.
     *
     * @api   POST /pastoral/bulk-import
     * @since 24.1
     *
     * @return void
     */
    public function postImport(): void
    {
        $zipFile = $this->loadStorageZipFile();
        $archive = $this->createLocalZipArchive($zipFile);
        $files   = $this->associateFilesWithUser($archive);

        $dummyRecord = $this->pastoralMapper->createModel();
        foreach ($files as $file) {
            // Extract processPastoralRecords to its own class
        }

        // Create a new pastoral record for each student extracted from the ZIP
    }

    /**
     * Load the ZIP file from storage.
     *
     * @return IStorage
     * @throws ResourceException
     */
    private function loadStorageZipFile(): IStorage
    {
        $factory = new CollectorFactory();
        return $factory->build([$this->requestPost->get('report')])->getFirst();
    }

    /**
     * Given a ZIP file in storage: copy it to local temp storage, and create a
     * `ZipArchive` out of it.
     *
     * @param IStorage $zipFile
     * @return ZipArchive
     * @throws FilesystemException
     */
    private function createLocalZipArchive(IStorage $zipFile): ZipArchive
    {
        // Need to ensure it exists locally:
        // otherwise it can't operate upon it
        $filename = tempnam($this->getConfigLoader()->get('zip_dir'), 'zip');
        $fp       = fopen($filename, 'wb');
        stream_copy_to_stream($this->filesystemProxy->readStream($zipFile), $fp);
        fclose($fp);

        $archive = new ZipArchive();
        $archive->open($filename);
        return $archive;
    }

    /**
     * Associate files in `$archive` with a user.
     *
     * @param ZipArchive $archive
     * @return array{name: string, user: ?User, externalId: string}
     */
    private function associateFilesWithUser(ZipArchive $archive): array
    {
        $tokenReplacer = new Simple();
        $files         = [];
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = $archive->getNameIndex($i);
            if ($name !== false) {
                $tokens     = $tokenReplacer->extract($this->requestPost->get('template'), $name);
                $user       = null;
                $externalId = null;
                if (isset($tokens['[externalId]'])) {
                    $externalId = $tokens['[externalId]'];
                    try {
                        $user = $this->userMapper->findByExternalId($externalId, Role::STUDENT);
                    } catch (MapperException) {
                        // noop
                    }
                }
                $files[] = [
                    'name'       => $name,
                    'user'       => $user,
                    'externalId' => $externalId,
                ];
            }
        }
        return $files;
    }
}
