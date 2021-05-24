<?php

/**
 * Homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Component\Homepage;

use Alaress_Date_Format;
use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\Assessment\DueDateSortStrategy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant\AssessmentListMutator;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use SitePage;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

abstract class Controller extends Component_Homepage_ControllerAbstract
{
    /** @var IAssessable[]|Collection */
    private $assessments;

    /** @var AssessmentMapperProxy $assessmentMapper */
    private $assessmentMapper;

    /** @var TermMapper $termMapper */
    private $termMapper;

    /**
     * Constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \RuntimeException
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);

        /** @var AssessmentMapperProxy $assMap */
        $assMap = $this->getSitePage()->getDi()->get('assessmentDataSource');

        $this->setAssessmentMapper($assMap);
        $this->setTermMapper(TermMapper::getInstance());
    }


    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    public function execute(): bool
    {
        if (!parent::execute()) {
            return false;
        }

        $now        = Alaress_Date_Format::now();
        $showClosed = $this->getConfig('showClosed');

        // Note: findAllByFolderIdForReview retrieves response+feedback for all
        // assessment types, not just tasks (as it did previously).
        $options = [
            'type'  => $this->getAssessmentType(),
            'order' => ['due_date' => 'asc'],
        ];

        if (!$showClosed) {
            $options['closed_before'] = $now;
        }

        // If a parent, we need to hydrate all participants so we can check parent visibility via child participation
        $user    = $this->getUser();
        $mutator = new AssessmentListMutator($this->getUser(), new Visibility($this->getAccess()), $now, $showClosed);
        $this->assessments = $this->getAssessmentMapper()
            ->findAllByFolderIdForReview(
                $this->getSource()->getId(),
                $user->getRole()->isParent() ? null : $user,
                $options
            )
            ->map([$mutator, 'map'])
            ->filter([$mutator, 'filter'])
            ->sortByCallback(new DueDateSortStrategy($this->getUser()))
            ->limit($this->getConfig('size') ?: null);

        return true;
    }

    /**
     * @return array
     */
    private function getTwigContext()
    {
        $term = $this->getTermMapper()->findForToday();
        return [
            'component_id' => $this->getInstance()->getId(),
            'compact'      => $this->getConfig('compactMode'),
            'context'      => $this->getSource(),
            'contextType'  => AssessmentContextType::TYPE_FOLDER . 's',
            'assessables'  => $this->assessments,
            'term'         => $term,
            'termCode'     => $term->getCode(),
            'readOnly'     => ($this->getContentPermission() <= UserAccess::ACCESS_READ),
            'canWrite'     => ($this->getContentPermission() >= UserAccess::ACCESS_WRITE),
        ];
    }

    /**
     * Renders view using Component_AbstractController::$templateObject.
     *
     * Responsible just for rendering objects already retrieved during execute()
     *
     * Should never be called if some sort of rendering is not to take place.
     *
     * @param String $templateHandle
     * @return boolean
     * @throws \RuntimeException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            $this->getTwigTemplate(),
            $this->getTwigContext(),
            true
        );
    }

    /**
     * @return string
     */
    abstract protected function getTwigTemplate();

    /**
     * Return the type of assessment to retrieve
     *
     * @return string
     */
    abstract protected function getAssessmentType();

    /**
     * @param AssessmentMapperProxy $assessmentDataMapper
     * @return $this
     */
    public function setAssessmentMapper(AssessmentMapperProxy $assessmentDataMapper)
    {
        $this->assessmentMapper = $assessmentDataMapper;
        return $this;
    }

    /**
     * @return AssessmentMapperProxy
     */
    public function getAssessmentMapper()
    {
        return $this->assessmentMapper;
    }

    /**
     * @param TermMapper $termMapper
     * @return $this
     */
    public function setTermMapper($termMapper)
    {
        $this->termMapper = $termMapper;
        return $this;
    }

    /**
     * @return TermMapper
     */
    public function getTermMapper()
    {
        return $this->termMapper;
    }
}
