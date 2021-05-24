<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section\Question\QuestionType;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType\Model as QuizQuestionType;

/**
 * Teachers may create Questions of the these QuestionTypes
 */
class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->hasTable('assess_luQuestionType');
        $this->hasModel(QuizQuestionType::class);
        $this->uses('type_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('classname', self::TYPE_STRING, null, 'className');
        $this->uses('filename', self::TYPE_STRING, null, 'fileName');
        $this->uses('description', self::TYPE_STRING);
        $this->uses('moodle', self::TYPE_STRING);
        $this->uses('automated_marking', self::TYPE_BOOL_ENUM);
        $this->uses('availability', self::TYPE_STRING);
        $this->uses('has_legend', self::TYPE_BOOLEAN);
        $this->uses('has_config', self::TYPE_BOOLEAN);
    }
}
