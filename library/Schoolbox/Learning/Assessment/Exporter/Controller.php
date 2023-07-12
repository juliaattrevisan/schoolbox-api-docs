<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base as BaseController;
use Alaress\Web\Controller\Exception as ControllerException;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Filter\BrokenFilterException;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilter;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Replicator;
use InvalidArgumentException;
use Alaress_Date_Format;

/**
 * Controller for assessment export actions.
 */
class Controller extends BaseController
{
    /**
     * Constructor
     *
     * @param Access                      $access
     * @param AssessmentDataSource        $assessmentDataSource
     * @param CourseImplementation\Mapper $courseImplementationMapper
     * @param UnitImplementation\Mapper   $unitImplementationMapper
     * @param Replicator                  $replicator
     * @param AssessmentFilter\IdFinder   $assessmentIdFinder
     * @param Project\ResultCache\Mapper  $projectResultCacheMapper
     */
    public function __construct(
        protected Access $access,
        protected AssessmentDataSource $assessmentDataSource,
        private CourseImplementation\Mapper $courseImplementationMapper,
        private UnitImplementation\Mapper $unitImplementationMapper,
        private Replicator $replicator,
        private AssessmentFilter\IdFinder $assessmentIdFinder,
        private Project\ResultCache\Mapper $projectResultCacheMapper
    ) {
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
     * @param IAssessable $assessment Assessment specification
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function getExportFormForAssessment(IAssessable $assessment): void
    {
        $assessmentImpls = $this->buildImplementationsForSpecification($assessment);

        $notImported = $assessmentImpls->filter(function (IAssessable $assessmentImpl) {
            return null === $assessmentImpl->getId();
        })->sortByCallback(static function (IAssessable $a, IAssessable $b) {
            return strnatcasecmp($a->getBaseContext()->getFolder()->getTitle(), $b->getBaseContext()->getFolder()->getTitle());
        });

        $imported = $assessmentImpls->filter(function (IAssessable $assessmentImpl) {
            return $assessmentImpl->getId();
        })->sortByCallback(static function (IAssessable $a, IAssessable $b) {
            return strnatcasecmp($a->getBaseContext()->getFolder()->getTitle(), $b->getBaseContext()->getFolder()->getTitle());
        });

        // Find interactions with imported assessments
        $assessmentIds = $imported->getPropertyValues('id');
        $responseCounts = $this->assessmentDataSource->findResponseCountsForAssessments($assessmentIds);
        $feedbackCounts = $this->assessmentDataSource->findFeedbackCountsForAssessments($assessmentIds);

        $breadcrumbs = [
            ['Learning', $this->getRouteIfAuthorized('learning.getClasses')],
            [$assessment->getBaseContext()->getTitle(), '/homepage/' . $assessment->getBaseContext()->getFolder()->getId()],
        ];
        if ($assessment->getContext() instanceof Project) {
            $breadcrumbs[] = [$assessment->getContext()->getTitle(), $this->getRouteIfAuthorized('learningAssessment.getAssessment', ['assessmentId' => $assessment->getContext()->getId()])];
        }
        $breadcrumbs[] = [$assessment->getTitle(), $this->getRouteIfAuthorized('learningAssessment.getAssessment', ['assessmentId' => $assessment->getId()])];
        $breadcrumbs[] = ['Export', '#'];

        $data = [
            'assessmentSpec'   => $assessment,
            'returnUrl'        => $this->getRequest()->query->get(Redirect::RETURN_URL, ''),
            'returnUrlName'    => Redirect::RETURN_URL,
            'unImported'       => $notImported,
            'imported'         => $imported,
            'folderWritePerms' => $this->buildFolderWritePermissions($assessmentImpls),
            'enableReimport'   => (bool)$this->getConfigLoader()->get('destructive_activity_export_enabled'),
            'breadcrumbs'      => $breadcrumbs,
            'responseCounts'   => $responseCounts,
            'feedbackCounts'   => $feedbackCounts,
        ];

        $template = '@learningAssessmentExporter/export_assessment.twig';
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));
    }


    /**
     * Get the form for exporting a single unitSpec.
     *
     * GET vars:
     * * returnUrl: a URL to return to once done
     *
     * @api   GET /learning/export/unit/:unitSpecId
     * @since 23.1
     *
     * @param UnitSpecification\Model $unitSpec UnitSpec
     * @throws MapperException
     */
    public function getExportFormForUnit(UnitSpecification\Model $unitSpec): void
    {
        $courseSpec = $unitSpec->getCourse();
        $courseImpls = $this->courseImplementationMapper->findAllByCourseSpecificationId($courseSpec->getId());

        // Filter out historical classes (no class code, no group).
        $courseImpls->filter(
            function (CourseImplementation\Model $courseImpl) {
                return !($courseImpl->getFolder()->getCodeString() === '' && $courseImpl->getFolder()->getGroup() === null);
            }
        );

        // Hydrate existing unit implementations.
        foreach ($courseImpls as $courseImpl) {
            $courseImpl->setUnits($this->unitImplementationMapper->findAllByCourseId($courseImpl->getId()));
        }

        // Split $courseImpls into two collections:
        //  * those in which $unitSpec has been imported, and
        //  * those in which $unitSpec has not been imported.
        $courseImplsInWhichUnitImported = new GenericCollection(CourseImplementation\Model::class);
        $courseImplsInWhichUnitNotImported = new GenericCollection(CourseImplementation\Model::class);
        foreach ($courseImpls as $courseImpl) {
            $found = false;
            foreach ($courseImpl->getUnits() as $unit) {
                if ($unit->getSpecification()->getId() === $unitSpec->getId()) {
                    $found = true;
                }
            }

            if ($found) {
                $courseImplsInWhichUnitImported->add($courseImpl);
            } else {
                $courseImplsInWhichUnitNotImported->add($courseImpl);
            }
        }

        // Sort both collections by title.
        foreach (array($courseImplsInWhichUnitImported, $courseImplsInWhichUnitNotImported) as $collection) {
            $collection->sortByCallback(static function (CourseImplementation\Model $a, CourseImplementation\Model $b) {
                return strnatcasecmp($a->getFolder()->getTitle(), $b->getFolder()->getTitle());
            });
        }
        $data = [
            'unitSpec'                          => $unitSpec,
            'returnUrl'                         => $this->getRequest()->query->get(Redirect::RETURN_URL, ''),
            'returnUrlName'                     => Redirect::RETURN_URL,
            'courseImplsInWhichUnitNotImported' => $courseImplsInWhichUnitNotImported,
            'courseImplsInWhichUnitImported'    => $courseImplsInWhichUnitImported,
            'folderWritePerms'                  => $this->buildFolderWritePermissions($courseImpls),
            'enableReimport'                    => (bool)$this->getConfigLoader()->get('destructive_activity_export_enabled'),
            'breadcrumbs'                       => [],
        ];

        $template = '@learningAssessmentExporter/export_unit.twig';
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));
    }


    /**
     * Determine whether the current user has WRITE+ perm to the assessments' folders.
     *
     * @param GenericCollection $items Collection of objects each having a getFolder() method.
     * @return bool[] Indexed by folder.id
     * @throws MapperException
     */
    private function buildFolderWritePermissions(GenericCollection $items): array
    {
        $folderWritePerms = [];
        foreach ($items as $item) {
            $folder                             = $item->getFolder();
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
     * @param IAssessable $assessment
     *
     * @throws InvalidArgumentException
     * @throws ControllerException
     * @throws RouteNotFound
     * @throws \DomainException
     * @throws \LogicException
     * @throws MapperException
     * @throws \UnexpectedValueException
     * @internal param string $_GET ['returnUrl']            a URL to return to once done
     * @internal param int[] $_POST ['unitImplementation']   unit implementations to push this into.
     *                                           key:   unit implementation id
     *                                           value: true/false
     * @internal param string[][] $_GET ['dates']            by unitImpl.id, then ['publish', 'open', 'due', 'late']
     */
    public function postExportForAssessment(IAssessable $assessment): void
    {
        $importedImplementations     = $this->getRequest()->post->get('unitImplementation', []);
        $importedImplementationDates = $this->getRequest()->post->get('unit', []);
        // get corresponding assessment impls in the unit impls selected by the user.
        $assessmentImpls             = $this->buildImplementationsForSpecification($assessment, array_keys($importedImplementations));
        $folderWritePerms            = $this->buildFolderWritePermissions($assessmentImpls);

        // Check all permissions before making any changes to system
        $errors = [];

        foreach ($assessmentImpls as $assessmentImpl) {
            $folderId = $assessmentImpl->getContext()->getFolder()->getId();
            if (!$folderWritePerms[$folderId]) {
                $errors[$folderId] = true;
            }
        }

        if (\count($errors)) {
            $folderIds = '[' . implode(',', array_keys($errors)) . ']';
            $this->sendError(
                "You do not have permission to write to the selected classes: $folderIds",
                [],
                403
            );
            return;
        }

        $enableReimport    = (bool)$this->getConfigLoader()->get('destructive_activity_export_enabled');
        $expectedDateTypes = ['publish', 'open', 'due', 'late'];
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
                $assessment,
                $assessmentImpl->getContext(),
                $assessmentImpl->getId() ? $assessmentImpl->getSequence() : null,
                $datesObjects['publish'],
                $datesObjects['open'] ?? $datesObjects['publish'],
                $datesObjects['due'],
                $datesObjects['late'] ?? $datesObjects['due']
            );
        }

        // Decode the return URL, if it was set
        $encodedReturnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL, '');
        if ($encodedReturnUrl) {
            $returnUrl = Redirect::decode($encodedReturnUrl);
        } else {
            /** @var UnitSpecification\Model $unitSpec */
            $unitSpec  = $assessment->getBaseContext();
            $returnUrl = $this->routeGenerator->generate(
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
     * Export a unit specification to one or more pre-existing course implementations.
     *
     * @api   POST /learning/export/unit/:unitSpecId
     * @since 23.1
     *
     * @param UnitSpecification\Model $unitSpec
     * @return void
     * @throws ControllerException
     * @throws MapperException
     * @throws RouteNotFound
     * @internal param int[] $_POST['courseImplementationIds'] course implementation IDs into which to export the $unitSpec.
     */
    public function postExportForUnit(UnitSpecification\Model $unitSpec): void
    {
        $courseImplIds = array_keys($this->getRequest()->post->get('courseImplementationIds', []));

        if (count($courseImplIds) === 0) {
            // If empty form submitted, do nothing.
            return;
        }

        $unitDatesByCourseId = $this->getRequest()->post->get('unitDatesByCourseId');

        $courseImpls = $this->courseImplementationMapper->findByColumn('id', $courseImplIds);

        // Hydrate existing unit implementations.
        foreach ($courseImpls as $courseImpl) {
            $courseImpl->setUnits($this->unitImplementationMapper->findAllByCourseId($courseImpl->getId()));
        }

        $folderWritePerms = $this->buildFolderWritePermissions($courseImpls);

        // Check all permissions before making any changes to system
        $errors = [];
        foreach ($courseImpls as $courseImpl) {
            $folderId = $courseImpl->getFolder()->getId();
            if (!$folderWritePerms[$folderId]) {
                $errors[$folderId] = true;
            }
        }

        if (\count($errors)) {
            $folderIds = '[' . implode(',', array_keys($errors)) . ']';
            $this->sendError(
                "You do not have permission to write to the selected classes: $folderIds",
                [],
                403
            );
            return;
        }

        $reimportIsEnabled = (bool)$this->getConfigLoader()->get('destructive_activity_export_enabled');

        foreach ($courseImpls as $courseImpl) {
            $units = $courseImpl->getUnits();
            $unitToOverwrite = null;

            foreach ($units as $unit) {
                if ($unit->getSpecification()->getId() === $unitSpec->getId()) {
                    $unitToOverwrite = $unit;
                }
            }

            if ($unitToOverwrite !== null) {
                if ($reimportIsEnabled) {
                    $unitToOverwrite->setDeletedAt(new Alaress_Date_Format(time(), Alaress_Date_Format::TIMESTAMP));
                    $this->unitImplementationMapper->save($unitToOverwrite);
                } else {
                    continue; // We cannot overwrite the existing unit, so skip this course implementation.
                }
            }

            $unitDates = $unitDatesByCourseId[$courseImpl->getId()];

            $unitImplToInsert = new UnitImplementation\Model();
            $unitImplToInsert->setCourse($courseImpl);
            $unitImplToInsert->setTitle($unitSpec->getFolder()->getTitle());
            $unitImplToInsert->setSpecification($unitSpec);
            $unitImplToInsert->setOpenDate(new Alaress_Date_Format($unitDates['open'] . ' 12:00am', Alaress_Date_Format::FORMAT_DATE_PICKER));
            $unitImplToInsert->setDueDate(new Alaress_Date_Format($unitDates['close'] . ' 12:00am', Alaress_Date_Format::FORMAT_DATE_PICKER));
            $this->unitImplementationMapper->save($unitImplToInsert);
        }

        // Decode the return URL, if it was set
        $encodedReturnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL, '');
        if ($encodedReturnUrl) {
            $returnUrl = Redirect::decode($encodedReturnUrl);
        } else {
            $returnUrl = $this->routeGenerator->generate(
                'course.modifyCourse',
                ['id' => $unitSpec->getCourse()->getId()]
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
     * @throws \DomainException
     * @throws MapperException
     * @throws \UnexpectedValueException
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
            throw new \DomainException('Invalid context: ' . \get_class($context));
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
            foreach ($assessmentSpec->getAssessables() as $subAssessmentSpec) {
                if ($subAssessmentSpec->isDeleted()) {
                    continue;
                }

                /** @var Project $assessmentImpl */
                $subAssessmentImpl = $this->exportToNewContext($subAssessmentSpec, $assessmentImpl);

                $assessmentImpl->getAssessables()->add($subAssessmentImpl);
            }

            /* A specific usage where creating projects and activities
             * always invalidates the project result cache
             *
             * This algorithm occurs when you export a whole project to a class
             */
            $projectResultCache = new Project\ResultCache\ResultCache($this->projectResultCacheMapper);
            $projectResultCache->refresh($this->assessmentDataSource, $assessmentImpl);
        } elseif ($assessmentSpec->getContext() instanceof Project) {
            /* A specific usage where creating projects and activities
             * always invalidates the project result cache
             *
             * This algorithm occurs when you use Export To Classes on a non-project assessment
             * even if that assessment was already inside a project
             */
            $projectResultCache = new Project\ResultCache\ResultCache($this->projectResultCacheMapper);
            $projectResultCache->refresh($this->assessmentDataSource, $assessmentImpl);
        }

        return $assessmentImpl;
    }


    /**
     * Helper method: builds assessment implementations for a spec assessment
     *
     * @param IAssessable $assessmentSpec
     * @param array|null  $unitImplIds
     * @return IAssessable[]|GenericCollection
     * @throws InvalidColumnException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    protected function buildImplementationsForSpecification(IAssessable $assessmentSpec, array $unitImplIds = null): GenericCollection
    {
        $defaultDueTime   = $this->getConfigLoader()->get('lms_duetime_default');

        // if the assessment is in a project spec, get project impls of the project spec
        $projectImpls = $assessmentSpec->getContext() instanceof Project
            ? $this->assessmentDataSource->findAllImplementationsBySpecificationId($assessmentSpec->getContext()->getId())
                ->indexByCallback(static function (IAssessable $assessable) {
                    return $assessable->getBaseContext()->getId();
                })
            : null;

        // unit implementations of the unit spec of this assessment spec or for specified unit impl ids
        $unitImplementations = $unitImplIds
            ? $this->unitImplementationMapper->findByColumn('id', $unitImplIds)
            : $this->unitImplementationMapper->findAllByUnitSpecificationId($assessmentSpec->getBaseContext()->getId());

        // find all assessment impl for the assessment spec
        $allAssessmentImpls = $this->assessmentDataSource->findAllImplementationsBySpecificationId($assessmentSpec->getId())
                                                         ->indexByCallback(static function (IAssessable $assessable) {
                                                             return $assessable->getBaseContext()->getId();
                                                         });

        $assessmentImpls = $this->assessmentDataSource->createCollection();
        foreach ($unitImplementations as $unitImplementation) {
            // Closed already? don't allow export
            if ($unitImplementation->getDueDate()->isPastDate()) {
                continue;
            }

            if ($allAssessmentImpls->keyExists($unitImplementation->getId())) {
                $assessmentImpls->add($allAssessmentImpls->offsetGet($unitImplementation->getId()));
                continue;
            }

            $context = null;
            // try to find a corresponding project impl, do not return, if project impl doesn't exist.
            if ($projectImpls) {
                if ($projectImpls->keyExists($unitImplementation->getId())) {
                    $context = $projectImpls->offsetGet($unitImplementation->getId());
                } else {
                    continue;
                }
            }

            $assessmentImpl = $assessmentSpec->cloneForCopy();
            $assessmentImpl->setContext($context ?? $unitImplementation);
            $assessmentImpl->setPublishAt($unitImplementation->getOpenDate());
            $assessmentImpl->setOpenAt($unitImplementation->getOpenDate());
            $assessmentImpl->setDueAt($unitImplementation->getDueDate()->setTimeFromString($defaultDueTime));
            $assessmentImpl->setCloseAt($unitImplementation->getDueDate());
            $assessmentImpls->add($assessmentImpl);
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
        $exporter = new Exporter($this->assessmentIdFinder, $this->assessmentDataSource, $this->getUser());
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
