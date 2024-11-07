<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use League\Flysystem\FilesystemException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\TemplateDataHelper;
use Schoolbox\Pastoral\Translator\VisibilityTranslator;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Storage;
use Schoolbox\Role;
use Schoolbox\TokenReplacer;
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
     * @param RosettaStone               $translator               For translating values used in the page title.
     * @param Mapper                     $pastoralBulkImportMapper For creating bulk import records.
     * @param Pastoral\DataSource        $pastoralDataSource       For creating pastoral records.
     * @param Pastoral\SubType\Mapper    $pastoralSubTypeMapper    For loading and validating pastoral subtypes.
     * @param Pastoral\Type\Mapper       $pastoralTypeMapper       For loading and validating pastoral types.
     * @param Pastoral\Severity\Mapper   $pastoralSeverityMapper   For loading pastoral severities.
     * @param Pastoral\Tag\Mapper        $pastoralTagMapper        For saving pastoral tags.
     * @param Pastoral\Access\Mapper     $pastoralAccessMapper     For determining roles which may access records.
     * @param Pastoral\Group\Mapper      $pastoralGroupMapper      For loading pastoral groups to select visibility.
     * @param Pastoral\Attachment\Mapper $pastoralAttachmentMapper For creating pastoral attachments.
     * @param UserMapper                 $userMapper               For loading pastoral moderators.
     * @param Pastoral\Audience\Mapper   $pastoralAudienceMapper   For loading audiences for types and subtypes.
     * @param Storage\FilesystemProxy    $filesystemProxy          For copying ZIP files from storage to local:
     *                                                             required for working with ZIP files.
     * @param Storage\Receiver           $receiver                 For copying streams from inside upload to storage.
     * @param Pastoral\RecordProcessor   $recordProcessor          For actually saving pastoral records according to
     *                                                             form input.
     */
    public function __construct(
        private RosettaStone $translator,
        private Mapper $pastoralBulkImportMapper,
        private Pastoral\DataSource $pastoralDataSource,
        private Pastoral\SubType\Mapper $pastoralSubTypeMapper,
        private Pastoral\Type\Mapper $pastoralTypeMapper,
        private Pastoral\Severity\Mapper $pastoralSeverityMapper,
        private Pastoral\Tag\Mapper $pastoralTagMapper,
        private Pastoral\Access\Mapper $pastoralAccessMapper,
        private Pastoral\Group\Mapper $pastoralGroupMapper,
        private Pastoral\Attachment\Mapper $pastoralAttachmentMapper,
        private UserMapper $userMapper,
        private Pastoral\Audience\Mapper $pastoralAudienceMapper,
        private Storage\FilesystemProxy $filesystemProxy,
        private Storage\Receiver $receiver,
        private Pastoral\RecordProcessor $recordProcessor,
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
        // Make loading data a little easier
        $templateDataHelper = new TemplateDataHelper(
            $this->getConfigLoader(),
            $this->pastoralSubTypeMapper,
            $this->pastoralTypeMapper,
            $this->pastoralSeverityMapper,
            $this->pastoralAccessMapper,
            $this->pastoralGroupMapper,
            $this->userMapper,
            $this->pastoralAudienceMapper,
        );
        $templateData = $templateDataHelper->loadTemplateData();
        $this->response = $this->responseFromTemplate(
            '@pastoralBulkImport/form.twig',
            $templateData + [
                'returnUrl'        => $this->requestQuery->get('returnUrl')
                    ?? $this->routeGenerator->generate('pastoral.getIndex'),
                'record'           => $this->pastoralDataSource->createModel()->setStudent(null),
                'reportAttachzone' => (new Zone('report'))
                    ->setThumbnailSize(64)
                    ->setModalTitle('Select report ZIP')
                    ->setAttachmentsLabel('')
                    ->setAttachPrompt('Upload report ZIP')
                    ->setMimeFilter(Zone::ZIP_ALL)
                    ->setReadOnly(false)
                    ->setFileVar('report')
                    ->setMulti(false)
                    ->useImportExpiryDate()
                    ->getTwigContext(),
                'moderator'        => $this->getUser()->hasServicePermission(
                    UserAccess::SERVICE_PASTORAL,
                    UserAccess::ACCESS_READ,
                ),
            ],
            $this->translator->translate('pastoral', capital: true) . ' Bulk Import',
        );
    }

    /**
     * Save the form import.
     *
     * @api   POST /pastoral/bulk-import/create
     * @since 24.1
     *
     * @return void
     */
    public function postCreateImport(): void
    {
        // Save the import here, then redirect to a create page.
        $zipStorage = $this->loadZipFromStorage();
        $type       = $this->pastoralTypeMapper->findById($this->requestPost->get('typeId'));
        $subType    = $this->requestPost->get('subtypeId')
            ? $this->pastoralSubTypeMapper->findById($this->requestPost->get('subtypeId'))
            : null;
        $import     = $this->pastoralBulkImportMapper->createModel()
            ->setOwner($this->getUser())
            ->setZip($zipStorage)
            ->setFilePathTemplate($this->requestPost->get('template'))
            ->setSeverity(
                $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_SEVERITY_ENABLED)
                    ? $this->pastoralSeverityMapper->findById($this->requestPost->get('severityId'))
                    : null
            )
            ->setType($this->pastoralTypeMapper->findById($this->requestPost->get('typeId')))
            ->setSubtype($subType)
            ->setHeading($this->requestPost->get('heading'))
            ->setBodyPublic($this->requestPost->get('body_public'))
            ->setPoints($this->requestPost->getFloat('points'))
            ->setImportedAt(null)
        ;

        // Create tags
        $import = $import->setTags(
            $this->recordProcessor->prepareTags(
                $this->requestPost->get('tags') ?? '',
                $this->requestPost->get('tagNames') ?? ''
            )
        );

        // Create access
        $visibilityTranslator = new VisibilityTranslator(
            $this->pastoralAccessMapper,
            $this->pastoralGroupMapper,
            $this->userMapper,
            Access\Model::class,
        );
        $import = $import->setAccess($visibilityTranslator->prepareAccesses($this->requestPost, $subType ?? $type));

        // And save
        $this->pastoralBulkImportMapper->save($import);

        // Then, redirect to a place where the fields are displayed
        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate('pastoralBulkImport.getImport', ['id' => $import->getId()]),
        );
    }

    /**
     * Display the current state of `$import`, including whether or not it has
     * actually been imported.
     *
     * @api   GET /pastoral/bulk-import/{id}
     * @since 24.1
     *
     * @param Model $import
     * @return void
     */
    public function getImport(Model $import): void
    {
        if ($import->getImportLog() === null) {
            // Generate the import log from the ZIP file's contents,
            // cleaning up once done.
            $zipArchive       = null;
            $localZipFilename = null;
            try {
                $zipStorage       = $import->getZip();
                $localZipFilename = $this->createLocalZipFile($zipStorage);
                $zipArchive       = $this->createZipArchive($localZipFilename);
                $import->setImportLog($this->generateImportLog($zipArchive, $import->getFilePathTemplate()));
            } finally {
                $this->cleanup($zipArchive, $localZipFilename);
            }
        }

        $this->response = $this->responseFromTemplate(
            '@pastoralBulkImport/import.twig',
            [
                'returnUrl' => $this->requestQuery->get('returnUrl')
                    ?? $this->routeGenerator->generate('pastoral.getIndex'),
                'import'    => $import,
            ],
        );
    }

    /**
     * Actually perform the pastoral bulk import.
     *
     * @api   POST /pastoral/bulk-import/{id}/run
     * @since 24.1
     *
     * @param Model $import
     * @return void
     */
    public function postRunImport(Model $import): void
    {
        if ($import->getImportedAt()) {
            $this->sendError('This import has already been run, and cannot be run again.');
            return;
        }

        // Load the Zip file's contents, cleaning up only once all files have
        // been extracted from the ZIP.
        $zipArchive       = null;
        $localZipFilename = null;
        try {
            $zipStorage       = $import->getZip();
            $localZipFilename = $this->createLocalZipFile($zipStorage);
            $zipArchive       = $this->createZipArchive($localZipFilename);
            $importLog        = $this->generateImportLog($zipArchive, $import->getFilePathTemplate());

            // Create the records
            $tokenReplacer = new TokenReplacer\Student(new TokenReplacer\Simple());
            foreach ($importLog['known'] as $i => $pastoralRecord) {
                // First, create the record itself
                $student = $this->userMapper->findById($pastoralRecord['user']['id']);
                $record  = $this->pastoralDataSource->createModel()
                    ->setOwner($this->getUser())
                    ->setStudent($student)
                    ->setModifiedBy($this->getUser())
                    ->setHeading($import->getHeading())
                    ->setType($import->getType())
                    ->setSubtype($import->getSubtype())
                    ->setSeverity($import->getSeverity())
                    ->setPoints($import->getPoints())
                    ->setTags($import->getTags())
                    ->setBodyPublic($tokenReplacer->replace($import->getBodyPublic(), $student))
                    ->setBodyPrivate('')
                    ->setExternalId(null)
                ;

                // Create access
                $accessModels = $this->pastoralAccessMapper->createCollection();
                foreach ($import->getAccess() as $access) {
                    /** @var Access\Model $access */
                    $accessModels->add(
                        $access->writeToPastoralAccess($this->pastoralAccessMapper->createModel()->setPastoral($record))
                    );
                }
                $record->setAccess($accessModels);

                // Create attachments
                foreach ($pastoralRecord['files'] as $fileName) {
                    $attachment = $this->receiver->acceptStream(
                        basename($fileName),
                        $zipArchive->getStream($fileName),
                    );
                    $record->getAttachments()->add(
                        (new Pastoral\Attachment\Model())
                            ->setPastoral($record)
                            ->setStorage($attachment)
                            ->setPrivate(false)
                            ->setExternalId(null)
                            ->setRealname($attachment->getFilename())
                    );
                }

                // And save: also writing a log I guess
                $this->pastoralDataSource->save($record);

                // In the log: set the record ID
                $importLog['known'][$i]['record']['id'] = $record->getId();

                // Save access, attachments and tags as well
                $this->pastoralAccessMapper->save($record->getAccess());
                $this->pastoralAttachmentMapper->save($record->getAttachments());
                foreach ($import->getTags() as $tag) {
                    $this->pastoralTagMapper->assignToRecord($record, $tag);
                }
            }
        } finally {
            $this->cleanup($zipArchive, $localZipFilename);
        }

        // Save a record of the import, possibly including logs,
        // and then redirect back to the main page.
        $import = $import
            ->setImportedAt(Alaress_Date_Format::now())
            ->setImportLog($importLog);
        $this->pastoralBulkImportMapper->save($import);

        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate('pastoralBulkImport.getImport', ['id' => $import->getId()]),
        );
    }

    /**
     * Load the ZIP file from storage.
     *
     * @return Storage\IStorage
     * @throws ResourceException
     */
    private function loadZipFromStorage(): Storage\IStorage
    {
        $factory = new CollectorFactory();
        return $factory->build([$this->requestPost->get('report')])->getFirst();
    }

    /**
     * Write the ZIP file from storage to the local file system, so that a
     * `ZipArchive` may be created from it.
     *
     * @param Storage\IStorage $zipFile
     * @return string
     * @throws FilesystemException
     */
    private function createLocalZipFile(Storage\IStorage $zipFile): string
    {
        $filename = tempnam($this->getConfigLoader()->get('zip_dir'), 'zip');
        $fp       = fopen($filename, 'wb');
        stream_copy_to_stream($this->filesystemProxy->readStream($zipFile), $fp);
        fclose($fp);
        return $filename;
    }

    /**
     * Given a ZIP file in storage: copy it to local temp storage, and create a
     * `ZipArchive` out of it.
     *
     * This is required as `ZipArchive` cannot work on a stream: it may only
     * work upon a local file.
     *
     * @param string $zipFilename
     * @return ZipArchive
     */
    private function createZipArchive(string $zipFilename): ZipArchive
    {
        $archive = new ZipArchive();
        $archive->open($zipFilename);
        return $archive;
    }

    /**
     * Clean up the `ZipArchive` object and its file on disk, if either exists.
     *
     * @param ZipArchive|null $zipArchive       The archive, if created.
     * @param string|null     $localZipFilename The filename of the archive,
     *                                          if created.
     * @return void
     */
    private function cleanup(?ZipArchive $zipArchive, ?string $localZipFilename): void
    {
        $zipArchive?->close();
        if (isset($localZipFilename) && is_file($localZipFilename) && is_writable($localZipFilename)) {
            unlink($localZipFilename);
        }
    }

    /**
     * Generate an import log for this archive: it will be written to the
     * pastoral log import, and will be used to generate the preview and import
     * result screens.
     *
     * @param ZipArchive $archive
     * @param string     $template
     * @return array{
     *     known: array<
     *         array{
     *             files: array<string>,
     *             user: array{id: int, externalId: string, name: string},
     *             record: ?array{id: int}
     *         }
     *     >,
     *     unknown: array<
     *         array{
     *             file: string,
     *             reason: string,
     *             user: array{externalId: ?string},
     *         },
     *     >,
     * }
     */
    private function generateImportLog(ZipArchive $archive, string $template): array
    {
        $tokenReplacer  = new TokenReplacer\Simple();
        $importLog      = ['known' => [], 'unknown' => []];
        $template       = $this->trimExtension($template);
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $filename = $archive->getNameIndex($i);
            if ($filename !== false) {
                $tokens        = $tokenReplacer->extract($template, $this->trimExtension($filename));
                $user          = null;
                $externalId    = null;
                $unknownReason = null;
                if (isset($tokens['[externalId]'])) {
                    $externalId = $tokens['[externalId]'];
                    try {
                        $user = $this->userMapper->findByExternalId($externalId, Role::STUDENT);
                    } catch (MapperException\Deleted) {
                        // noop: no user was found.
                        // This will show as a "no student found" message.
                        $unknownReason = 'Student was deleted';
                    } catch (MapperException $e) {
                        $unknownReason = $e->getMessage();
                    }
                } else {
                    $unknownReason = 'External ID not found in filename';
                }

                if ($user) {
                    $importLog['known'][$user->getId()] ??= [
                        'user'  => [
                            'id'         => $user->getId(),
                            'externalId' => $user->getExternalId() ?? '',
                            'name'       => $user->formatName(),
                        ],
                        'files'  => [],
                        'record' => null,
                    ];
                    $importLog['known'][$user->getId()]['files'][] = $filename;
                } else {
                    $importLog['unknown'][] = [
                        'file'   => $filename,
                        'reason' => $unknownReason,
                        'user'   => ['externalId' => $externalId ?? null],
                    ];
                }
            }
        }

        // Strip keys from known users
        $importLog['known'] = array_values($importLog['known']);
        return $importLog;
    }

    /**
     * Trim the extension off of `$path`, if there is one.
     *
     * Required because `\Schoolbox\TokenReplacer\Simple::extract` extracts the
     * entire remainder of a string if the template's last character is a token:
     * e.g. "[externalId]" matches "123.pdf" in "123.pdf".
     *
     * @param string $path
     * @return string
     */
    private function trimExtension(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension !== '') {
            return substr($path, 0, -(strlen($extension) + 1));
        }
        return $path;
    }
}
