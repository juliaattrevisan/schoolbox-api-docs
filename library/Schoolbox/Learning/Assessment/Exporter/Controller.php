<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Web\Controller\Base as BaseController;
use Alaress\Web\Controller\Exception;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use DomainException;
use Schoolbox\Filter\BrokenFilterException;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilter;
use InvalidArgumentException;
use Alaress_Date_Format;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use UnexpectedValueException;
use function count;
use function get_class;

/**
 * Controller for assessment export actions.
 */
class Controller extends BaseController
{

    /**
     * @var Access
     */
    protected $access;

    /**
     * @var AssessmentDataSource
     */
    protected $assessmentDataSource;

    /**
     * @var UnitImplementation\Mapper
     */
    private $unitImplementationMapper;

    /** @var Replicator */
    private $replicator;

    /** @var AssessmentFilter\IdFinder */
    private $assessmentIdFinder;

    /** @var FolderMapper */
    private $folderMapper;

    /**
     * Constructor
     *
     * @param Access                    $access
     * @param AssessmentDataSource      $assessmentDataSource
     * @param UnitImplementation\Mapper $unitImplementationMapper
     * @param Replicator                $replicator
     * @param AssessmentFilter\IdFinder $assessmentIdFinder
     * @param FolderMapper              $folderMapper
     */
    public function __construct(
        Access $access,
        AssessmentDataSource $assessmentDataSource,
        UnitImplementation\Mapper $unitImplementationMapper,
        Replicator $replicator,
        AssessmentFilter\IdFinder $assessmentIdFinder,
        FolderMapper $folderMapper
    ) {
        $this->access                   = $access;
        $this->assessmentDataSource     = $assessmentDataSource;
        $this->unitImplementationMapper = $unitImplementationMapper;
        $this->replicator               = $replicator;
        $this->assessmentIdFinder       = $assessmentIdFinder;
        $this->folderMapper             = $folderMapper;
    }


    /**
     * Get the form for exporting a single assessment.
     *
     * * Only assessments within a unit specification context may be exported
     * * Assessments may only be exported to unit implementations which are:
     * ** based on the same specification
     * ** not closed yet
     * ** not already containing a copy of this assessment (unless destructive re-export is enabled)
     *
     * GET vars:
     * * returnUrl: a URL to return to once done
     *
     * @api   GET /learning/export/assessment/:assessmentId
     * @since 165
     *
     * @param IAssessable $assessmentSpec
     * @throws ServiceNotFound
     * @throws MapperException
     */
    public function getExportForm(IAssessable $assessmentSpec)
    {
        $assessmentImpls = $this->buildImplementationsForSpecification($assessmentSpec);

        $notImported = $assessmentImpls->filter(function (IAssessable $assessmentImpl) {
            return null === $assessmentImpl->getId();
        });
        $imported    = $assessmentImpls->filter(function (IAssessable $assessmentImpl) {
            return $assessmentImpl->getId();
        });

        // Load participants into imported assessments
        $this->assessmentDataSource->loadParticipantsIntoAssessmentCollection($imported);

        $data = [
            'assessmentSpec'   => $assessmentSpec,
            'returnUrl'        => $this->getRequest()->query->get(Redirect::RETURN_URL, ''),
            'returnUrlName'    => Redirect::RETURN_URL,
            'unImported'       => $notImported,
            'imported'         => $imported,
            'folderWritePerms' => $this->buildFolderWritePermissions($assessmentImpls),
            'enableReimport'   => (bool)$this->getConfigLoader()->get('destructive_activity_export_enabled'),
        ];

        $template = '@learningAssessmentExporter/export.twig';
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));
    }


    /**
     * Determine whether the current user has WRITE+ perm to the assessments' folders.
     *
     * @param IAssessable[]|GenericCollection $assessmentImpls
     * @return bool[] Indexed by folder.id
     * @throws MapperException
     */
    private function buildFolderWritePermissions(GenericCollection $assessmentImpls): array
    {
        $folderWritePerms = [];
        foreach ($assessmentImpls as $assessmentImpl) {
            $folder                             = $assessmentImpl->getContext()->getFolder();
            $folderWritePerms[$folder->getId()] = $this->access->isInstructor($this->getUser(), $folder);
        }
        return $folderWritePerms;
    }


    /**
     * Processes the export assessment request.
     *
     * @api      POST /learning/export/assessment/:assessmentId
     * @since    165
     *
     * @param IAssessable $assessmentSpec
     *
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws RouteNotFound
     * @throws DomainException
     * @throws MapperException
     * @throws UnexpectedValueException
     * @internal param string $_GET ['returnUrl']            a URL to return to once done
     * @internal param int[] $_POST ['unitImplementation']   unit implementations to push this into.
     *                                           key:   unit implementation id
     *                                           value: true/false
     * @internal param string[][] $_GET ['dates']            by unitImpl.id, then ['publish', 'open', 'due', 'close']
     */
    public function postExport(IAssessable $assessmentSpec)
    {
        $importedImplementations     = $this->getRequest()->post->get('unitImplementation', []);
        $importedImplementationDates = $this->getRequest()->post->get('unit', []);
        $assessmentImpls             = $this->buildImplementationsForSpecification($assessmentSpec);
        $folderWritePerms            = $this->buildFolderWritePermissions($assessmentImpls);

        // Check all permissions before making any changes to system
        $errors = [];

        foreach ($assessmentImpls as $assessmentImpl) {
            // But only if the implementation was ticked by the user
            if (array_key_exists($assessmentImpl->getBaseContext()->getId(), $importedImplementations)) {
                $folderId = $assessmentImpl->getContext()->getFolder()->getId();
                if (!$folderWritePerms[$folderId]) {
                    $errors[$folderId] = true;
                }
            }
        }

        if (count($errors)) {
            $folderIds = '[' . implode(',', array_keys($errors)) . ']';
            $this->sendError(
                "You do not have permission to write to the selected classes: $folderIds",
                [],
                403
            );
            return;
        }

        $enableReimport    = (bool)$this->getConfigLoader()->get('destructive_activity_export_enabled');
        $expectedDateTypes = ['publish', 'open', 'due', 'close'];
        foreach ($assessmentImpls as $assessmentImpl) {
            /** @var UnitImplementation\Model $unitImpl */
            $unitImpl = $assessmentImpl->getBaseContext();

            // Was unitImport not selected in form?
            if (empty($importedImplementations[$unitImpl->getId()])) {
                continue;
            }

            // Does the assessment already exist in the unit import?
            if ($assessmentImpl->getId()) {
                // Does the system permit destructive re-export?
                if ($enableReimport) {
                    $notifier = $this->assessmentDataSource->getNotifier();
                    $before   = $notifier->dehydrate($assessmentImpl);
                    $this->assessmentDataSource->delete($assessmentImpl);
                    $notifier->notify($assessmentImpl, $this->getUser(), $before);
                } else {
                    continue;
                }
            }

            $dates        = $importedImplementationDates[$unitImpl->getId()];
            $datesObjects = [];

            foreach ($expectedDateTypes as $dateType) {
                if (!array_key_exists($dateType, $dates)) {
                    continue;
                }
                $datesObjects[$dateType] = Alaress_Date_Format::newLocal($dates[$dateType]);
            }

            $this->exportToNewContext(
                $assessmentSpec,
                $unitImpl,
                $assessmentImpl->getId() ? $assessmentImpl->getSequence() : null,
                $datesObjects['publish'],
                $datesObjects['open'] ?? $datesObjects['publish'],
                $datesObjects['due'],
                $datesObjects['close'] ?? $datesObjects['due']
            );
        }

        // Decode the return URL, if it was set
        $encodedReturnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL, '');
        if ($encodedReturnUrl) {
            $returnUrl = Redirect::decode($encodedReturnUrl);
        } else {
            /** @var UnitSpecification\Model $unitSpec */
            $unitSpec  = $assessmentSpec->getBaseContext();
            $returnUrl = $this->getRouter()->generate(
                    'course.modifyCourse',
                    ['id' => $unitSpec->getCourse()->getId(),]
                ) . "#{$unitSpec->getId()}";
        }

        // Redir back to the return URL (if set) or the course page if not
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([Redirect::RETURN_URL => $returnUrl]));
        } else {
            $this->getResponse()->redirect->afterPost($returnUrl);
        }
    }


    /**
     * Recursively export an AssessmentSpec to a new context
     *
     * @param IAssessable              $assessmentSpec
     * @param AssessmentContext        $context
     * @param null|int                 $sequence
     * @param Alaress_Date_Format|null $publishDate
     * @param Alaress_Date_Format|null $openDate
     * @param Alaress_Date_Format|null $dueDate
     * @param Alaress_Date_Format|null $closeDate
     * @return IAssessable
     * @throws InvalidArgumentException
     * @throws DomainException
     * @throws MapperException
     * @throws UnexpectedValueException
     */
    protected function exportToNewContext(
        IAssessable $assessmentSpec,
        AssessmentContext $context,
        int $sequence = null,
        Alaress_Date_Format $publishDate = null,
        Alaress_Date_Format $openDate = null,
        Alaress_Date_Format $dueDate = null,
        Alaress_Date_Format $closeDate = null
    ): IAssessable {

        if ($context instanceof UnitImplementation\Model) {
            $publishDate = $publishDate ?? $context->getOpenDate();
            $openDate    = $openDate ?? $context->getOpenDate();
            $dueDate     = $dueDate ?? $context->getDueDate();
            $closeDate   = $closeDate ?? $context->getDueDate();
        } elseif ($context instanceof Project) {
            $publishDate = $publishDate ?? $context->getOpenAt();
            $openDate    = $openDate ?? $context->getOpenAt();
            $dueDate     = $dueDate ?? $context->getDueAt();
            $closeDate   = $closeDate ?? $context->getDueAt();
        } else {
            throw new DomainException('Invalid context: ' . get_class($context));
        }

        // Set sensible defaults for dates
        $assessmentSpec
            ->setPublishAt($publishDate)
            ->setOpenAt($openDate)
            ->setDueAt($dueDate)
            ->setCloseAt($closeDate);

        // Replicate (and notify)!
        $assessmentImpl = $this->replicator->replicateInNewContext($assessmentSpec, $context, $this->getUser(), $sequence);
        $this->assessmentDataSource->getNotifier()->notify($assessmentImpl, $this->getUser(), []);

        // Recursive case
        if ($assessmentSpec instanceof Project) {
            /** @var Project $assessmentImpl */
            foreach ($assessmentSpec->getAssessables() as $subAssessmentSpec) {
                if ($subAssessmentSpec->isDeleted()) {
                    continue;
                }
                $subAssessmentImpl = $this->exportToNewContext($subAssessmentSpec, $assessmentImpl);
                $assessmentImpl->getAssessables()->add($subAssessmentImpl);
            }
        }

        return $assessmentImpl;
    }


    /**
     * Helper method: builds assessment implementations for a spec assessment
     *
     * @param IAssessable $assessment
     * @return IAssessable[]|GenericCollection
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    protected function buildImplementationsForSpecification(IAssessable $assessment): GenericCollection
    {
        $assessmentImpls     = $this->assessmentDataSource->createCollection();
        $unitImplementations = $this->unitImplementationMapper->findAllByUnitSpecificationId(
            $assessment->getContext()->getId()
        );

        foreach ($unitImplementations as $unitImplementation) {

            // Closed already? don't allow export
            if ($unitImplementation->getDueDate()->isPastDate()) {
                continue;
            }

            // Load assessments into unit implementation
            $unitImplementation->setAssessables(
                $this->assessmentDataSource->findAllByUnitImplementationIds([$unitImplementation->getId()])
            );

            // Is $assessable imported into this unit yet?
            $isAlreadyImported = false;
            foreach ($unitImplementation->getAssessables() as $implementationAssessment) {
                // It's not guaranteed that an assessment in a implementation will have a spec.
                // An assessment added to an implementation post-spec won't have one.
                $spec = $implementationAssessment->getSpecificationAssessment();
                if ($spec instanceof IAssessable && $spec->getId() == $assessment->getId()) {
                    $isAlreadyImported = true;
                    $assessmentImpls->add($implementationAssessment);
                    break;
                }
            }
            // Not yet imported?
            if (!$isAlreadyImported) {
                $assessmentImpl = $assessment->cloneForCopy();
                // hydrate folder to get folder code
                $folder = $unitImplementation->getBaseContext()->getFolder();
                $this->folderMapper->findById($folder->getId());
                $unitImplementation->setFolder($folder);
                $assessmentImpl->setContext($unitImplementation);
                $assessmentImpl->setPublishAt($unitImplementation->getOpenDate());
                $assessmentImpl->setOpenAt($unitImplementation->getOpenDate());
                $assessmentImpl->setDueAt($unitImplementation->getDueDate());
                $assessmentImpl->setCloseAt($unitImplementation->getDueDate());
                $unitImplementation->getAssessables()->add($assessmentImpl);
                $assessmentImpls->add($assessmentImpl);
            }
        }

        return $assessmentImpls;
    }


    /**
     * Download a csv of assessment results for Accellerus
     *
     * @api   GET /learning/export/assessment/csv
     * @since 160
     * @throws InvalidArgumentException If any filter keys not recognised
     * @throws BrokenFilterException If a injected filter is unable to be processed
     */
    public function getCSV(): void
    {
        // Generate an updated csv
        $filename = sb_tempnam();
        $file     = fopen($filename, 'wb');
        $exporter = new Exporter($this->assessmentIdFinder, $this->assessmentDataSource);
        $exporter->generate($file);
        fclose($file);

        $now         = date('Y-m-d-H-i-s');
        $newFilename = "results-{$now}.csv";

        // Force download/
        if (file_exists($filename)) {

            $headers = $this->getResponse()->headers;

            // per header
            $headers->set('Content-Description', 'File Transfer');
            $headers->set('Content-Type', 'text/csv');
            $headers->set('Content-Description', 'File Transfer');
            $headers->set('Content-Disposition', 'attachment; filename="' . $newFilename . '"');
            $headers->set('Expires', 0);
            $headers->set('Cache-Control', 'must-revalidate');
            $headers->set('Pragma', 'public');
            $headers->set('Content-Length', filesize($filename));

            $this->getResponse()->content->set([
                'format'     => 'file',
                'file'       => $filename,
                'postOutput' => [
                    function () use ($filename) {
                        /* Delete the temp file
                         */
                        unlink($filename);
                    },
                ],
            ]);
        }
    }
}
