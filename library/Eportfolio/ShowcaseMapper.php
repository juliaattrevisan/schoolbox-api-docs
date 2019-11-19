<?php
/**
 * Eportfolio_ShowcaseMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Eportfolio;

use PDOStatement;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

/**
 * @method Showcase hydrate(array $row, $alias = '', $callback = null)
 * @method Schoolbox\GenericCollection|Showcase[] createCollection($contents = [])
 */
class ShowcaseMapper extends Schoolbox\Mapper
{

    /**
     * @inheritdoc
     * @TODO - join this table to assessment_id instead of submission_box_id
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_showcase_item');
        $this->hasModel(Showcase::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('submission_box_id', SubmissionBoxMapper::class, 'id', self::TYPE_MODEL, null, 'submissionBox', 'submissionBox');
        $this->uses('uid', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @inheritdoc
     * @throws Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString()
    {
        $submissionBoxMapper = $this->getJoinMapper('submissionBox');
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinMapper('submissionBox')->getColumnString('sb')},
                {$submissionBoxMapper->getJoinMapper('assessmentModel')->getColumnString('am')}
        ";
    }


    /**
     * @inheritdoc
     * @throws Schoolbox\Mapper\DefinitionException
     */
    protected function getFromString()
    {
        $submissionBoxMapper = $this->getJoinMapper('submissionBox');
        return "
            FROM {$this->getTable()}
            JOIN {$this->getJoinSql('submissionBox', '', 'sb')}
            JOIN {$submissionBoxMapper->getJoinSql('assessmentModel', 'sb', 'am', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Schoolbox\GenericCollection|Showcase[]
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     * @throws
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $submissionBoxMapper = $this->getJoinMapper('submissionBox');
        $showcaseItems       = $this->createCollection();
        $assessmentIds       = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($row);
            $model->setSubmissionBox($submissionBoxMapper->hydrate($row, 'sb'));
            $assessmentId                 = $model->getSubmissionBox()->getAssessmentModel()->getId();
            $assessmentIds[$assessmentId] = $assessmentId;
            $showcaseItems->setOffset($model->getId(), $model);
        }

        $assessmentMapper = $submissionBoxMapper->getJoinMapper('assessmentModel');
        $assessmentModels = $assessmentMapper
            ->findByColumn($assessmentMapper->getIdentifierColumn(), $assessmentIds, ['deleted' => true])
            ->indexBy($assessmentMapper->getIdentifierColumn());

        foreach ($showcaseItems as $showcaseItem) {
            $assessmentId = $showcaseItem->getSubmissionBox()->getAssessmentModel()->getId();
            $showcaseItem->getSubmissionBox()->setAssessmentModel($assessmentModels[$assessmentId]);
        }

        return $showcaseItems;
    }


    /**
     * Get all the showcased submission boxes for a particular user,
     * ordered alphabetically by their parent category name
     *
     * @param User $user
     * @return Collection
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     */
    public function getShowcasedSubmissionBoxes(User $user)
    {
        return $this->findByColumn('uid', $user->getId())->sortByCallback(function (Showcase $left, Showcase $right) {
            $leftFolder  = $left->getSubmissionBox()->getAssessmentModel()->getAssessmentContext()->getFolder();
            $rightFolder = $right->getSubmissionBox()->getAssessmentModel()->getAssessmentContext()->getFolder();
            return strcasecmp($leftFolder->getName(), $rightFolder->getName());
        });
    }


    /**
     * Get all the showcased submission boxes for a particular user,
     * ordered alphabetically by the time they were showcased.
     *
     * This is used on the profile screen
     *
     * @param \Schoolbox\User $user
     * @param int             $limit
     * @return Schoolbox\GenericCollection|Showcase[]
     * @throws Schoolbox\Mapper\DefinitionException
     */
    public function getRecentShowcasedSubmissionBoxes(User $user, $limit = 5)
    {
        $options = [
            'order' => ["{$this->getTable()}.created_at" => 'DESC'],
            'limit' => $limit,
        ];
        return $this->findByColumn('uid', $user->getId(), $options);
    }
}
