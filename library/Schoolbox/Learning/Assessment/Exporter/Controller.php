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
use Schoolbox\Learning\Assessment\LessonPlan\Activities\Replicator as LessonPlanActivitiesReplicator;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlan;
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
     * @param Access                         $access
     * @param AssessmentDataSource           $assessmentDataSource
     * @param CourseImplementation\Mapper    $courseImplementationMapper
     * @param UnitImplementation\Mapper      $unitImplementationMapper
     * @param Replicator                     $replicator
     * @param AssessmentFilter\IdFinder      $assessmentIdFinder
     * @param Project\ResultCache\Mapper     $projectResultCacheMapper
     * @param LessonPlanActivitiesReplicator $lessonPlanActivitiesReplicator
     */
    public function __construct(
        protected Access $access,
        protected AssessmentDataSource $assessmentDataSource,
        private CourseImplementation\Mapper $courseImplementationMapper,
        private UnitImplementation\Mapper $unitImplementationMapper,
        private Replicator $replicator,
        private AssessmentFilter\IdFinder $assessmentIdFinder,
        private Project\ResultCache\Mapper $projectResultCacheMapper,
        private LessonPlanActivitiesReplicator $lessonPlanActivitiesReplicator,
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
        $assessment->assertUsable();
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
        $breadcrumbs[] = ['Import', '#'];

        $data = [
            'assessmentSpec'   => $assessment,
            'returnUrl'        => $this->requestQuery->get(Redirect::RETURN_URL, ''),
            'returnUrlName'    => Redirect::RETURN_URL,
            'unImported'       => $notImported,
            'imported'         => $imported,
            'folderWritePerms' => $this->buildFolderWritePermissionsForAssessment($assessmentImpls),
            'enableReimport'   => (bool)$this->getConfigLoader()->get('destructive_activity_export_enabled'),
            'breadcrumbs'      => $breadcrumbs,
            'responseCounts'   => $responseCounts,
            'feedbackCounts'   => $feedbackCounts,
        ];

        $template = '@learningAssessmentExporter/export_assessment.twig';
        $this->response = $this->responseFromTemplate($template, $data);
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

        // Filter out course implementations in deleted folders, and historical classes (no class code and no group).
        $courseImpls = $courseImpls->filter(
            function (CourseImplementation\Model $courseImpl) {
                return (
                    $courseImpl->getFolder() !== null &&
                    !($courseImpl->getFolder()->getCodeString() === '' && $courseImpl->getFolder()->getGroup() === null)
                );
            }
        );

        // Hydrate existing unit implementations.
        foreach ($courseImpls as $courseImpl) {
            $courseImpl->setUnits($this->unitImplementationMapper->findAllByCourseId($courseImpl->getId()));
        }

        // Find the course implementations in which $unitSpec has not been imported.
        $courseImplsInWhichUnitNotImported = new GenericCollection(CourseImplementation\Model::class);
        foreach ($courseImpls as $courseImpl) {
            $found = false;
            foreach ($courseImpl->getUnits() as $unit) {
                if ($unit->getSpecification()->getId() === $unitSpec->getId()) {
                    $found = true;
                }
            }

            if (!$found) {
                $courseImplsInWhichUnitNotImported->add($courseImpl);
            }
        }

        // Sort by title.
        $courseImplsInWhichUnitNotImported->sortByCallback(static function (CourseImplementation\Model $a, CourseImplementation\Model $b) {
            return strnatcasecmp($a->getFolder()->getTitle(), $b->getFolder()->getTitle());
        });

        // Get info on related course outline components.
        $courseOutlineComponentIdsStruct = $this->courseImplementationMapper->getCourseOutlineComponentIdsStruct(
            $courseImpls->map(fn($courseImpl) => $courseImpl->getId())->getAll()
        );

        $data = [
            'unitSpec'                          => $unitSpec,
            'returnUrl'                         => $this->requestQuery->get(Redirect::RETURN_URL, ''),
            'returnUrlName'                     => Redirect::RETURN_URL,
            'courseImplsInWhichUnitNotImported' => $courseImplsInWhichUnitNotImported,
            'courseOutlineComponentIdsStruct'   => $courseOutlineComponentIdsStruct,
            'folderWritePerms'                  => $this->buildFolderWritePermissionsForUnit($courseImpls),
            'breadcrumbs'                       => [
                ['Learning', $this->getRouteIfAuthorized('learning.getClasses')],
                [$courseSpec->getTitle(), '/homepage/' . $courseSpec->getFolder()->getId()],
                [$unitSpec->getTitle(), '/homepage/' . $unitSpec->getFolder()->getId()],
                ['Import', '#']
            ]

        ];

        $template = '@learningAssessmentExporter/export_unit.twig';
        $this->response = $this->responseFromTemplate($template, $data);
    }


    /**
     * Determine whether the current user has WRITE+ perm to the assessment's folders.
     *
     * @param GenericCollection $assessmentImpls
     * @return bool[] Indexed by folder.id
     * @throws MapperException
     */
    private function buildFolderWritePermissionsForAssessment(GenericCollection $assessmentImpls): array
    {
        $folderWritePerms = [];
        foreach ($assessmentImpls as $assessmentImpl) {
            $folder                             = $assessmentImpl->getContext()->getFolder();
            $folderWritePerms[$folder->getId()] = $this->access->isInstructor($this->getUser(), $folder);
        }
        return $folderWritePerms;
    }


    /**
     * Determine whether the current user has WRITE+ perm to the unit's folders.
     *
     * @param GenericCollection $items Collection of objects each having a getFolder() method.
     * @return bool[] Indexed by folder.id
     * @throws MapperException
     */
    private function buildFolderWritePermissionsForUnit(GenericCollection $items): array
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
        $assessment->assertUsable();
        $importedImplementations     = $this->requestPost->get('unitImplementation', []);
        $importedImplementationDates = $this->requestPost->get('unit', []);
        // get corresponding assessment impls in the unit impls selected by the user.
        $assessmentImpls             = $this->buildImplementationsForSpecification($assessment, array_keys($importedImplementations));
        $folderWritePerms            = $this->buildFolderWritePermissionsForAssessment($assessmentImpls);

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

            $newAssessmentImpl = $this->exportToNewContext(
                $assessment,
                $assessmentImpl->getContext(),
                $assessmentImpl->getId() ? $assessmentImpl->getSequence() : null,
                $datesObjects['publish'],
                $datesObjects['open'] ?? $datesObjects['publish'],
                $datesObjects['due'],
                $datesObjects['late'] ?? $datesObjects['due']
            );

            // Replicate lesson plan linked activities only after assessment and
            // project have been overwritten.
            $this->lessonPlanActivitiesReplicator->replicate($assessment, $newAssessmentImpl);
        }

        /** @var UnitSpecification\Model $unitSpec */
        $unitSpec = $assessment->getBaseContext();
        $this->redirectToReturnUrlIfSetOrFallbackUrl(
            $this->routeGenerator->generate(
                'course.modifyCourse',
                ['id' => $unitSpec->getCourse()->getId(),]
            ) . "#{$unitSpec->getId()}"
        );
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
        $fallbackReturnUrl = $this->routeGenerator->generate(
            'course.modifyCourse',
            ['id' => $unitSpec->getCourse()->getId()]
        ) . "#{$unitSpec->getId()}";

        $courseImplIds = array_keys($this->requestPost->get('courseImplementationIds', []));

        if (count($courseImplIds) === 0) {
            // If empty form submitted, do nothing.
            $this->redirectToReturnUrlIfSetOrFallbackUrl($fallbackReturnUrl);
            return;
        }

        $unitDatesByCourseId = $this->requestPost->get('unitDatesByCourseId');

        $courseImpls = $this->courseImplementationMapper->findByColumn('id', $courseImplIds);

        // Hydrate existing unit implementations.
        foreach ($courseImpls as $courseImpl) {
            $courseImpl->setUnits($this->unitImplementationMapper->findAllByCourseId($courseImpl->getId()));
        }

        $folderWritePerms = $this->buildFolderWritePermissionsForUnit($courseImpls);

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

        foreach ($courseImpls as $courseImpl) {
            $units = $courseImpl->getUnits();
            $alreadyExistingUnit = null;

            foreach ($units as $unit) {
                if ($unit->getSpecification()->getId() === $unitSpec->getId()) {
                    $alreadyExistingUnit = $unit;
                }
            }

            if ($alreadyExistingUnit !== null) {
                continue; // Overwriting existing units is never allowed by this tool, so skip this course implementation.
            }

            $unitDates = $unitDatesByCourseId[$courseImpl->getId()];
            $openDateAlaress = Alaress_Date_Format::newLocalShort($unitDates['open']);
            $closeDateAlaress = Alaress_Date_Format::newLocalShort($unitDates['close']);

            $unitImplNew = new UnitImplementation\Model();
            $unitImplNew->setCourse($courseImpl);
            $unitImplNew->setTitle($unitSpec->getFolder()->getTitle());
            $unitImplNew->setSpecification($unitSpec);
            $unitImplNew->setOpenDate($openDateAlaress);
            $unitImplNew->setDueDate($closeDateAlaress);
            $this->unitImplementationMapper->save($unitImplNew);

            $implSpecAssessmentPairs = [];
            foreach ($this->assessmentDataSource->findAllByUnitSpecificationId($unitSpec->getId()) as $unitSpecAssessment) {
                $newAssessmentImpl = $this->exportToNewContext(
                    $unitSpecAssessment,
                    $unitImplNew,
                    null,
                    $openDateAlaress,
                    $openDateAlaress,
                    $closeDateAlaress,
                    $closeDateAlaress
                );
                $implSpecAssessmentPairs[] = [$newAssessmentImpl, $unitSpecAssessment];
            }

            // Replicate lesson plan linked activities only after assessment and
            // project have been overwritten.
            foreach ($implSpecAssessmentPairs as $pair) {
                [$newAssessmentImpl, $unitSpecAssessment] = $pair;
                $this->lessonPlanActivitiesReplicator->replicate($unitSpecAssessment, $newAssessmentImpl);
            }
        }

        $this->redirectToReturnUrlIfSetOrFallbackUrl($fallbackReturnUrl);
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
            $this->postOutputCallbacks[] = function () use ($filename): void {
                /* Delete the temp file
                 */
                unlink($filename);
            };

            $this->response = $this->responseFromContent(file_get_contents($filename))
                ->withHeader('Content-Description', 'File Transfer')
                ->withHeader('Content-Type', 'text/csv')
                ->withHeader('Content-Description', 'File Transfer')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $newFilename . '"')
                ->withHeader('Expires', 0)
                ->withHeader('Cache-Control', 'must-revalidate')
                ->withHeader('Pragma', 'public')
                ->withHeader('Content-Length', filesize($filename))
            ;
        }
    }

    /**
     * Redirect to either:
     *  - the return URL (if set in the URL parameters), OR
     *  - the fallback URL.
     * @param $fallbackUrl
     * @return void
     */
    private function redirectToReturnUrlIfSetOrFallbackUrl($fallbackUrl): void
    {
        // Decode the return URL, if it was set
        $encodedReturnUrl = $this->requestQuery->get(Redirect::RETURN_URL, '');
        if ($encodedReturnUrl) {
            $returnUrl = Redirect::decode($encodedReturnUrl);
        } else {
            $returnUrl = $fallbackUrl;
        }

        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson([Redirect::RETURN_URL => $returnUrl]);
        } else {
            $this->response = $this->redirectAfterPost($returnUrl);
        }
    }
}
