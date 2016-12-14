<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\Exporter;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

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
     * @var UnitImplementationMapper
     */
    private $unitImplementationMapper;

    /**
     * Constructor
     *
     * @param Access                   $access
     * @param AssessmentDataSource     $assessmentDataSource
     * @param UnitImplementationMapper $unitImplementationMapper
     */
    public function __construct(
        Access $access,
        AssessmentDataSource $assessmentDataSource,
        UnitImplementationMapper $unitImplementationMapper
    ) {
        $this->access                   = $access;
        $this->assessmentDataSource     = $assessmentDataSource;
        $this->unitImplementationMapper = $unitImplementationMapper;
    }

    /**
     * Get the form for exporting a single assessment.
     *
     * * Only assessments within a unit specification context may be exported
     * * Assessments may only be exported to unit implementations which are:
     * ** based on the same specification
     * ** not closed yet
     * ** not already containing a copy of this assessment
     *
     * GET vars:
     * * returnUrl: a URL to return to once done
     *
     * @api GET /learning/export/assessment/:assessmentId
     * @level 165
     * @param IAssessable $assessment
     */
    public function getExportForm(IAssessable $assessment)
    {
        $data = [
            'assessment'      => $assessment,
            'returnUrl'       => $this->getRequest()->query->get(Redirect::RETURN_URL, ''),
            'returnUrlName'   => Redirect::RETURN_URL,
            'implementations' => $this->retrieveUnitImplementationsForSpecification($assessment),
        ];

        $template = "@learningAssessmentExporter/export.twig";
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));
    }

    /**
     * Processes the export assessment request.
     *
     * GET vars:
     * * returnUrl: a URL to return to once done
     *
     * POST vars:
     * * unitImplementation: unit implementations to push this into.
     *                       key:   unit implementation id
     *                       value: true/false
     *
     *
     * @api POST /learning/export/assessment/:assessmentId
     * @level 165
     * @param IAssessable $assessment
     */
    public function postExport(IAssessable $assessment)
    {
        $importedImplementations = $this->getRequest()->post->get('unitImplementation', []);
        $newImplementations      = $this->assessmentDataSource->createCollection();
        $sequences               = [];

        foreach ($this->retrieveUnitImplementationsForSpecification($assessment) as $implementation) {
            /** @var UnitImplementation $unit */
            $unit = $implementation['unit'];

            if (!empty($importedImplementations[$unit->getId()])) {
                // This is being imported, so do stuff
                $sequences[$unit->getId()] = $unit->getAssessables()->count() + 1;

                if ($implementation['imported']) {
                    // This has already been exported between now and when the request was made.
                    // That is probably fine to ignore
                    continue;
                }

                if (!$implementation['allowed']) {
                    // banhammer
                    $this->sendError(
                        'You do not have permission to write to the selected class',
                        [],
                        403
                    );
                    return;
                }

                // clone $assessment for this implementation
                $newImplementation = $assessment->cloneForCopy();
                $newImplementation
                    ->setContext($unit)
                    ->setSpecificationAssessment($assessment)
                    // Set sensible defaults for dates
                    ->setPublishAt($unit->getOpenDate())
                    ->setOpenAt($unit->getOpenDate())
                    ->setDueAt($unit->getDueDate())
                    ->setCloseAt($unit->getDueDate())
                ;
                $newImplementations->setOffset($unit->getId(), $newImplementation);
            }
        }

        // Save the newly created implementations (at the end of the unit)
        foreach ($newImplementations as $unitId => $newImplementation) {
            $this->assessmentDataSource->save($newImplementation);
        }

        // Decode the return URL, if it was set
        $encodedReturnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL, '');
        if ($encodedReturnUrl) {
            $returnUrl = Redirect::decode($encodedReturnUrl);
        } else {
            $returnUrl = $this->getRouter()->generate(
                'course.modifyCourse',
                ['id' => $assessment->getContext()->getId(),]
            );
        }

        // Redir back to the return URL (if set) or the course page if not
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([ Redirect::RETURN_URL => $returnUrl ]));
        } else {
            $this->getResponse()->redirect->afterPost($returnUrl);
        }
    }

    /**
     * Helper method: retrieves unit implementations for a spec assessment
     *
     * @param IAssessable $assessment
     * @return array[] unit:     The unit implementation that this assessment's spec uses
     *                 allowed:  Is the current user allowed to import this?
     *                 imported: Is this assessment already imported?
     */
    protected function retrieveUnitImplementationsForSpecification(IAssessable $assessment)
    {
        $result              = [];
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
                $this->assessmentDataSource->findAllByUnitImplementationId($unitImplementation->getId())
            );

            // Can I even import into $unitImplementation's folder?
            $accessLevel = $this->access->getFolderAccess(
                $this->getUser(),
                [$unitImplementation->getCourse()->getFolder()->getId()]
            );
            $isAllowed = $accessLevel >= UserAccess::ACCESS_WRITE;

            // Is $assessable imported into this unit yet?
            $isAlreadyImported = false;
            foreach ($unitImplementation->getAssessables() as $implementationAssessment) {
                // It's not guaranteed that an assessment in a implementation will have a spec.
                // An assessment added to an implementation post-spec won't have one.
                $spec = $implementationAssessment->getSpecificationAssessment();
                if ($spec instanceof IAssessable && $spec->getId() == $assessment->getId()) {
                    $isAlreadyImported = true;
                    break;
                }
            }

            $result[] = [
                'unit'     => $unitImplementation,
                'allowed'  => $isAllowed && !$isAlreadyImported,
                'imported' => $isAlreadyImported,
            ];
        }

        return $result;
    }
}
