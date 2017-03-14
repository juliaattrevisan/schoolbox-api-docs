<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section\Question;

use PDOStatement;
use Schoolbox\GenericCollection;
use \Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Quiz\Section\Question\Model as QuizQuestion;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType\Mapper as QuizQuestionTypeMapper;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Model as QuizSolution;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Mapper as QuizSolutionMapper;

class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->hasTable('assess_question');
        $this->hasModel(QuizQuestion::class);
        $this->uses('question_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('section_id', QuizSectionMapper::class, 'section_id', null, null, 'section', 'section');
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins('type_id', QuizQuestionTypeMapper::class, 'type_id', null, null, 'type', 'type');
        $this->uses('marks', self::TYPE_DECIMAL);
        $this->uses('question', self::TYPE_STRING);
        $this->uses('config', self::TYPE_JSON);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsReverseMulti('question_id', QuizSolutionMapper::class, 'question_id', 'solution', 'solution');

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteSolution'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreSolution'));
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
          SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('solution', 's')}
        ";
    }

    /**
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            LEFT JOIN {$this->getJoinSql('solution', '', 's')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Model[]|GenericCollection $questions */
        $questions = $this->createCollection();

        while($row = $statement->fetch(\PDO::FETCH_ASSOC))
        {
            /** @var Model $question */
            $question = $this->hydrate($row)
                ->setSolution($this->hydrateJoin($row, 'solution', 's'));

            // Does this Question have a Solution?
            if(!$question->getSolution()->getId()){
                $question->setSolution(null);
            }
            $questions->add($question);
        }
        return $questions;
    }

    /**
     * Soft deletes the Solution to the specified Question
     *
     * @param QuizQuestion $question
     */
    public function softDeleteSolution(QuizQuestion $question)
    {
        $quizSolutionMapper = QuizSolutionMapper::getInstance();
        // Don't re-delete deleted leaf-nodes. Unlike non-leaf nodes, these will retain original deletion date.
        $quizSolutionMapper->deleteByColumn('question_id', $question->getId());
    }

    /**
     * Restores all soft-deleted Answers for the specified Attempt
     *
     * @param QuizQuestion $question
     */
    public function restoreSolution(QuizQuestion $question)
    {
        $quizSolutionMapper = QuizSolutionMapper::getInstance();
        // TODO SA-1277 - should only restore items that were part of the parent's cascade delete
        $solution = $quizSolutionMapper->findByColumn('question_id', $question->getId(), ['deleted' => true]);
        $quizSolutionMapper->restoreSoftDeleted($solution);
    }
}
