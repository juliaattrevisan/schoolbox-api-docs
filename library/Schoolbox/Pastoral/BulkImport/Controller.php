<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use Alaress\Paginator;
use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use League\Flysystem\FilesystemException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\TemplateDataHelper;
use Schoolbox\Pastoral\Translator\VisibilityTranslator;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Storage;
use Schoolbox\Role;
use Schoolbox\TokenReplacer;
use Schoolbox\Url\Uri;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use ZipArchive;

/**
 * Controller for bulk importation of pastoral records.
 *
 * @phpstan-import-type ImportLog from Model
 */
class Controller extends Base
{
    /**
     * How many imports are shown per list page?
     */
    private const PAGE_SIZE = 10;

    /**
     * Which query string param is the page number in?
     */
    private const PAGE_QUERY_STRING_PARAM = 'page';

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
     * @param RosettaStone               $rosettaStone             For translating values used in the page title.
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
        private RosettaStone $rosettaStone
    ) {
    }

    /**
     * Display list of previous imports.
     *
     * @api   GET /pastoral/bulk-import
     * @since 24.1
     *
     * @return void
     */
    public function getList(): void
    {
        // Set up pagination
        $currentUrl  = $this->routeGenerator->generate('pastoralBulkImport.getList');
        $importCount = $this->pastoralBulkImportMapper->countAll();
        $paginator   = (new Paginator\UrlPaginator())
            ->setUrl($currentUrl)
            ->setPaginator(new Paginator\Paginator())
            ->setPageSize(self::PAGE_SIZE)
            ->setParam(self::PAGE_QUERY_STRING_PARAM)
            ->setTotal($importCount);
        $currentPage = $this->requestQuery->get(self::PAGE_QUERY_STRING_PARAM, 1);

        // Show x number of previous imports, and when they were imported, and
        // their undo status.
        $imports = ($importCount > 0)
            ? $this->pastoralBulkImportMapper->findAll([
                'order'  => [
                    'created_at' => $this->pastoralBulkImportMapper::SORT_ORDER_DESC,
                    'id'         => $this->pastoralBulkImportMapper::SORT_ORDER_DESC,
                ],
                'limit'  => $paginator->getPageSize(),
                'offset' => $paginator->getOffset($currentPage),
            ])
            : $this->pastoralBulkImportMapper->createCollection();

        /**
         * both pastoral Write and Moderators access this page, and they can access via different directions.
         * we should try to map the breadcrumbs to the most logical starting point
         * and build the sidenav with admin if they have access
         *
         * write > Pastoral home
         * write+ > Administration Home
         */
        $pastoralCareProper = $this->rosettaStone->translate('pastoralCare', false, true);
        $adminRoute = $this->getRouteIfAuthorized('adminHome.getIndex');
        $breadcrumbs = [
            [$pastoralCareProper, $this->routeGenerator->generate('pastoral.getIndex')],
            ["Bulk Import", null],
        ];

        /* Assuming the Pastoral moderator came from Admin > Pastoral, rather than directly from Pastoral
         */
        if ($this->user->isSuperuser() || $this->user->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL)) {
            $breadcrumbs = [
                ['Administration', $adminRoute],
                ['Pastoral Care', null],
                ["Bulk Import", null],
            ];
        }

        $this->response = $this->responseFromTemplate(
            '@pastoralBulkImport/list.twig',
            [
                'imports'     => $imports,
                'paginator'   => $paginator,
                'currentPage' => $currentPage,
                'breadcrumbs' => $breadcrumbs,
                'showAdminNavMenu'    => $this->user->isSuperuser() || $this->user->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL),

                // Encode return URL separately, so that page number is included
                'encodedReturnUrl' => Redirect::encode(
                    (new Uri($currentUrl))
                        ->withQuery(http_build_query([self::PAGE_QUERY_STRING_PARAM => $currentPage]))
                ),
            ],
        );
    }

    /**
     * Display form for creating a pastoral bulk import.
     *
     * @api   GET /pastoral/bulk-import/create
     * @since 24.1
     *
     * @return void
     */
    public function getCreateImportForm(): void
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

        /**
         * both pastoral Write and Moderators access this page, and they can access via different directions.
         * we should try to map the breadcrumbs to the most logical starting point
         * and build the sidenav with admin if they have access
         *
         * write > Pastoral home
         * write+ > Administration Home
         */
        $pastoralCareProper = $this->rosettaStone->translate('pastoralCare', false, true);
        $adminRoute = $this->getRouteIfAuthorized('adminHome.getIndex');
        $breadcrumbs = [
            [$pastoralCareProper, $this->routeGenerator->generate('pastoral.getIndex')],
            ["Bulk Import", $this->getRouteIfAuthorized('pastoralBulkImport.getList')],
            ["Create", null],
        ];

        /* Assuming the Pastoral moderator came from Admin > Pastoral, rather than directly from Pastoral
         */
        if ($this->user->isSuperuser() || $this->user->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL)) {
            $breadcrumbs = [
                ['Administration', $adminRoute],
                ['Pastoral Care', null],
                ["Bulk Import", $this->getRouteIfAuthorized('pastoralBulkImport.getList')],
                ["Create", null],
            ];
        }
        $this->response = $this->responseFromTemplate(
            '@pastoralBulkImport/form.twig',
            $templateData + [
                'breadcrumbs'       => $breadcrumbs,
                'showAdminNavMenu'    => $this->user->isSuperuser() || $this->user->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL),
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

        /* Try fetching the prescribed subtype from the post payload. Due to multiplayer schoolbox
         * this id might or might not exist at runtime. I don't think we should try to assign a deleted
         * subtype, so error out somewhat-meaningfully instead.
         */
        $subtypeId = $this->requestPost->get('subtypeId');

        try {
            $subType = $this->pastoralSubTypeMapper->findById($subtypeId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Recycle the same error from RecordProcessor
             */
            throw new SchoolboxException("Pastoral subtype with ID {$subtypeId} cannot be found");
        }

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
            $this->generateRedirectUrl(
                $import,
                $this->routeGenerator->generate('pastoralBulkImport.getImport', ['id' => $import->getId()]),
            ),
        );
    }

    /**
     * Delete the bulk import.
     *
     * This will _not_ delete any pastoral records created by the import:
     * it will only remove the bulk import from the form.
     *
     * @api   POST /pastoral/bulk-import/delete
     * @since 24.1
     *
     * @param Model $import
     * @return void
     * @throws MapperException
     */
    public function postDeleteImport(Model $import): void
    {
        $this->pastoralBulkImportMapper->delete($import);
        $this->response = $this->redirectAfterPost(
            $this->generateRedirectUrl(
                $import,
                $this->routeGenerator->generate('pastoralBulkImport.getList', ['id' => $import->getId()]),
            ),
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
                $zipStorage = $import->getZip();
                if (!$zipStorage) {
                    $this->sendError(
                        'This import\'s ZIP file no longer exists.',
                        ['Delete and recreate the import'],
                        HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                    );
                    return;
                }
                $localZipFilename = $this->createLocalZipFile($zipStorage);
                $zipArchive       = $this->createZipArchive($localZipFilename);
                $import->setImportLog($this->generateImportLog($zipArchive, $import->getFilePathTemplate()));
            } finally {
                $this->cleanup($zipArchive, $localZipFilename);
            }
        }

        /**
         * both pastoral Write and Moderators access this page, and they can access via different directions.
         * we should try to map the breadcrumbs to the most logical starting point
         * and build the sidenav with admin if they have access
         *
         * write > Pastoral home
         * write+ > Administration Home
         */
        $pastoralCareProper = $this->rosettaStone->translate('pastoralCare', false, true);
        $adminRoute = $this->getRouteIfAuthorized('adminHome.getIndex');
        $breadcrumbs = [
            [$pastoralCareProper, $this->routeGenerator->generate('pastoral.getIndex')],
            ["Bulk Import", $this->getRouteIfAuthorized('pastoralBulkImport.getList')],
            ["Results", null],
        ];

        /* Assuming the Pastoral moderator came from Admin > Pastoral, rather than directly from Pastoral
         */
        if ($this->user->isSuperuser() || $this->user->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL)) {
            $breadcrumbs = [
                ['Administration', $adminRoute],
                ['Pastoral Care', null],
                ["Bulk Import", $this->getRouteIfAuthorized('pastoralBulkImport.getList')],
                ["Results", null],
            ];
        }

        $this->response = $this->responseFromTemplate(
            '@pastoralBulkImport/import.twig',
            [
                'breadcrumbs' => $breadcrumbs,
                'showAdminNavMenu'    => $this->user->isSuperuser() || $this->user->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL),
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

        // Save imported by/at immediately,
        // so that mashing the import button doesn't create multiple imports.
        $import = $import
            ->setImportedBy($this->getUser())
            ->setImportedAt(Alaress_Date_Format::now());
        $this->pastoralBulkImportMapper->save($import);

        // Load the Zip file's contents, cleaning up only once all files have
        // been extracted from the ZIP.
        $zipArchive       = null;
        $localZipFilename = null;
        try {
            $zipStorage = $import->getZip();
            if (!$zipStorage) {
                $this->sendError(
                    'This import\'s ZIP file no longer exists.',
                    ['Delete and recreate the import'],
                    HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                );
                return;
            }
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

        // Save import logs, then redirect back to the main page.
        $import = $import->setImportLog($importLog);
        $this->pastoralBulkImportMapper->save($import);

        $this->response = $this->redirectAfterPost(
            $this->generateRedirectUrl(
                $import,
                $this->routeGenerator->generate('pastoralBulkImport.getImport', ['id' => $import->getId()]),
            ),
        );
    }

    /**
     * Undo a previously run import, by deleting any records which were created.
     *
     * @api   POST /pastoral/bulk-import/{id}/undo
     * @since 24.1
     *
     * @param Model $import
     * @return void
     */
    public function postUndoImport(Model $import): void
    {
        if (!$import->getImportedAt()) {
            $this->sendError('This import has not been run, and cannot be undone.');
            return;
        }

        // Investigate the log to find what was created:
        // delete all of these.
        foreach ($import->getImportLog()['known'] as $item) {
            $recordId = $item['record']['id'] ?? null;
            if (isset($recordId)) {
                $this->pastoralDataSource->delete(
                    $this->pastoralDataSource->findById($this->getUser(), $recordId),
                );
            }
        }

        // Save undo fields
        $import = $import
            ->setUndoneBy($this->getUser())
            ->setUndoneAt(Alaress_Date_Format::now());
        $this->pastoralBulkImportMapper->save($import);

        // Redirect
        $this->response = $this->redirectAfterPost(
            $this->generateRedirectUrl(
                $import,
                $this->routeGenerator->generate('pastoralBulkImport.getImport', ['id' => $import->getId()]),
            ),
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
     * @return ImportLog
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
                $imported      = false;
                if (isset($tokens['[externalId]'])) {
                    $externalId = $tokens['[externalId]'];
                    try {
                        // findByExternalId doesn't raise a deleted exception:
                        // see SA-2288 for discussion.
                        // For now just use standard find method.
                        $user     = $this->userMapper->findOneByColumnList([
                            'synergy_id'   => $externalId,
                            'g.group_type' => Role::STUDENT,
                        ]);
                        $imported = true;
                    } catch (MapperException\Deleted $e) {
                        $unknownReason = 'student has been deleted';
                        $user          = $e->getDeletedObject() instanceof User ? $e->getDeletedObject() : null;
                    } catch (MapperException) {
                        $unknownReason = 'no matching student found';
                    }
                } else {
                    $unknownReason = 'external ID not found in filename';
                }

                if ($imported) {
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
                        'user'   => [
                            'id'         => $user?->getId(),
                            'externalId' => $externalId ?? null,
                            'name'       => $user?->formatName(),
                        ],
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

    /**
     * Generate a URL for post-import or post-preview redirect, including the
     * return URL if it's in the query string.
     *
     * @param Model $import
     * @return string
     */
    private function generateRedirectUrl(Model $import, string $redirectUrl): string
    {
        $returnQuery = [];
        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnQuery[Redirect::RETURN_URL] = $this->requestQuery->get(Redirect::RETURN_URL);
        }
        return (new Uri())
            ->withPath($redirectUrl)
            ->withQuery(http_build_query($returnQuery))
            ->__toString();
    }
}
