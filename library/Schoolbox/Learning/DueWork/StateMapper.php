<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\DueWork;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Mapper;

/**
 * Class StateMapper
 *
 * @package Schoolbox\Learning\DueWork
 *
 * @method State findOneByColumn($columnName, $columnValue, array $options = [])
 * @method \Schoolbox\Collection|State[] findByColumn($columnName, $columnValue, array $options = [])
 * @method State findOneByPredicates(PredicateBuilder $pb, array $options = [])
 */
class StateMapper extends Mapper
{

    protected function mapping()
    {
        $this->hasTable('submission_errors');
        $this->hasModel(State::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('code', self::TYPE_STRING);
        $this->uses('display_as', self::TYPE_STRING);
        $this->uses('normalise_as', self::TYPE_DECIMAL);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyUniqueCode']);
    }


    /**
     * Returns an list of the options
     * @return array
     */
    public function listDisplayValues()
    {
        $errs = $this->findAll();
        $disp = array();
        foreach ($errs as $e) {
            /* @var $e State */
            $disp[] = $e->getDisplayAs();
        }
        return $disp;
    }


    /**
     * @inheritDoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }


    /**
     * Ensure that Submission States are unique
     *
     * Detect if this submission state already exists
     *
     * If it already exists,
     * throw a mapper exception because you're not meant to have dupes
     *
     * @param State $object
     * @return bool
     * @throws Mapper\Exception
     */
    public function verifyUniqueCode(State $object): bool
    {
        if ($object->getId()) {
            /* If the object existed in the past,
             * see if any exist with the same name that -isn't- that particular instance
             * because renaming it is a possibility that should be allowed
             */
            $pb = $this->getPredicateBuilder()
                ->notEquals('id', $object->getId())
                ->equals('code', $object->getCode());
        } else {
            /* Otherwise you're creating a new row and we just need to check
             * if that code was previously in use
             */
            $pb = $this->getPredicateBuilder()
                ->equals('code', $object->getCode());
        }

        $existing = $this->findByPredicates($pb)->first();

        if (null === $existing) {
            return true;
        }

        throw new \Schoolbox\Mapper\Exception(sprintf(
            'A submission state %s (%s) already exists.',
            $existing->getDisplayAs(),
            $existing->getCode()
        ));
    }
}
