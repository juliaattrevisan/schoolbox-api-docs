<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Quiz\Section\Question\QuestionType;

use \Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType\Model as QuizQuestionType;

/**
 * Teachers may create Questions of the these QuestionTypes
 */
class Mapper extends BaseMapper
{
    protected function mapping () {
        $this->hasTable('assess_luQuestionType');
        $this->hasModel(QuizQuestionType::class);
        $this->hasMap(array(
            'column'     => 'type_id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'classname',
            'property'   => 'className',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'filename',
            'property'   => 'fileName',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'description',
            'property'   => 'description',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'moodle',
            'property'   => 'moodle',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'automated_marking',
            'property'   => 'automatedMarking',
            'type'       => self::TYPE_BOOL_ENUM,
        ));
        $this->hasMap(array(
            'column'     => 'availability',
            'property'   => 'availability',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'has_legend',
            'property'   => 'hasLegend',
            'type'       => self::TYPE_BOOLEAN,
        ));
        $this->hasMap(array(
            'column'     => 'has_config',
            'property'   => 'hasConfig',
            'type'       => self::TYPE_BOOLEAN,
        ));
    }
}