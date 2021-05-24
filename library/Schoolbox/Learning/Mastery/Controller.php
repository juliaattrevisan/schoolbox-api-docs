<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Mastery;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Application\Exception\Exception as ApplicationException;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Term;

class Controller extends Base
{
    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * @var Assessment\MapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var Term\Mapper
     */
    private $termMapper;

    /**
     * Constructor
     *
     * @param Error\Mapper                $errorMapper
     * @param Assessment\MapperProxy      $assessmentMapperProxy
     * @param Term\Mapper                 $termMapper
     */
    public function __construct(
        Error\Mapper $errorMapper,
        Assessment\MapperProxy $assessmentMapperProxy,
        Term\Mapper $termMapper
    ) {
        $this->errorMapper                = $errorMapper;
        $this->assessmentMapperProxy      = $assessmentMapperProxy;
        $this->termMapper                 = $termMapper;
    }

    /**
     * @api /learning/mastery/{classId}
     * @param Folder\Model $class
     * @throws ApplicationException
     * @throws ServiceNotFound If unable to fetch WebOutput from DI
     */
    public function getMastery(Folder\Model $class)
    {
        $this->getMasteryForTerm($class, $this->termMapper->findForToday());
    }

    /**
     * @api /learning/mastery/{classId}/term/{termCode}
     * @param Folder\Model $class
     * @param Term\Model $term
     * @throws ApplicationException
     * @throws ServiceNotFound If unable to fetch WebOutput from DI
     */
    public function getMasteryForTerm(Folder\Model $class, Term\Model $term)
    {
        // Does this class exist ?
        if (!$class->getId()) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_PAGE_NOT_FOUND);
        }

        // Generate data: assessments and their Participants
        $options = [
            'term'  => $term,
            'order' => ['due_date' => 'ASC'],
        ];
        /** @var Collection|IAssessable[] $assessments */
        $assessments = $this->assessmentMapperProxy->findAllByFolderIdForReview($class->getId(), null, $options)
                                                        ->filter(function (IAssessable $assessable) {
                                                            return !$assessable instanceof Project;
                                                        });

        // Build a markbook
        $markSchemeFactory = new Factory($this->getConfigLoader());
        $markbook = new Markbook($assessments, $markSchemeFactory->spawnForFolder($class, $this->getUser()->getRole()->isStaff()), $this->getUser());

        // And what is required for a context selector
        $terms = $this->termMapper->findAll(['order' => ['start' => 'desc']]);

        // And the previous and next term
        $previousTerm = null;
        $nextTerm     = null;
        foreach ($terms as $i => $termInList) {
            if ($termInList->getId() === $term->getId()) {
                if (isset($terms[$i + 1])) {
                    $previousTerm = $terms[$i + 1];
                }
                if (isset($terms[$i - 1])) {
                    $nextTerm = $terms[$i - 1];
                }
                break;
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningMastery/mastery.twig', [
                'class'        => $class,
                'markbook'     => $markbook,
                'terms'        => $terms,
                'currentTerm'  => $term,
                'previousTerm' => $previousTerm,
                'nextTerm'     => $nextTerm
        ])->setTitle("{$class->getTitle()} Mastery Report"));
    }

    /* Getters/Setters
     */
}
