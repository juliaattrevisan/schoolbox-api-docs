<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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

/**
 * Class Mapper
 * @package Schoolbox\Learning\Quiz\Section\Question
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    public const COL_SECTION_ID = 'section_id';

    protected function mapping()
    {
        $this->hasTable('assess_question');
        $this->hasModel(QuizQuestion::class);
        $this->uses('question_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins(self::COL_SECTION_ID, QuizSectionMapper::class, 'section_id', null, null, 'section', 'section');
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins('type_id', QuizQuestionTypeMapper::class, 'type_id', null, null, 'type', 'type');
        $this->uses('marks', self::TYPE_DECIMAL);
        $this->uses('question', self::TYPE_STRING);
        $this->uses('config', self::TYPE_JSON);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsReverseSingle('question_id', QuizSolutionMapper::class, 'solution', 'solution');

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteSolution'));
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

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC))
        {
            $question = $this->hydrate($row);

            /** @var QuizSolution $solution */
            $solution = $this->hydrateJoin($row, 'solution', 's');

            if ($solution) {
                // solution is for some reason optional, so only set it if it actually came through
                $question->setSolution($solution);
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
}
