<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Report;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_report');
        $this->hasModel(Model::class);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('severity_id', PastoralSeverityMapper::class, 'id', null, null, 'severity', 'severity');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('before_date', self::TYPE_DATETIME);
        $this->uses('after_date', self::TYPE_DATETIME);
        $this->uses('involved', self::TYPE_BOOLEAN);

        // Filter criteria CSV strings

        $this->uses('types', self::TYPE_STRING, null, 'typesCsv');
        $this->uses('authors', self::TYPE_STRING, null, 'authorsCsv');
        $this->uses('students', self::TYPE_STRING, null, 'studentsCsv');
        $this->uses('tags', self::TYPE_STRING, null, 'tagsCsv');
        $this->uses('order', self::TYPE_STRING, null, 'orderCsv');
        $this->uses('years', self::TYPE_STRING, null, 'yearsCsv');

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');            
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = new Collection();

        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            if ($this->getConfigLoader()->get('pastoral_severity')) {
                if (!empty($row['severity_id'])) {
                    /** @var PastoralSeverity $severity */
                    $severity = $this->getJoinMapper('severity')->findById($row['severity_id']);
                    $model->setSeverity($severity);
                }
            }

            $model->setInvolved($row['involved'] == 1);

            // Should probably be storing report criteria as a bunch of pivot tables, but this will do for now.

            // This takes the report criteria which are stored in the database as CSV strings and turns them into collections
            // (of hydrated objects where applicable)

            $model->setOrder(new Collection(explode(',', $row['order'])));
            $model->setYears(new Collection(explode(',', $row['years'])));
            $model->setTypes($this->getCollection(PastoralTypeMapper::getInstance(), $row['types']));
            $model->setAuthors($this->getCollection(UserMapper::getInstance(), $row['authors']));
            $model->setStudents($this->getCollection(UserMapper::getInstance(), $row['students']));
            $model->setTags($this->getCollection(PastoralTagMapper::getInstance(), $row['tags']));

            $collection->add($model);
        }

        return $collection;
    }

    /**
     * Helper function to convert CSV filters to a collection of objects
     *
     * @param BaseMapper $mapper
     * @param string $ids
     * @return Collection
     */
    private function getCollection($mapper, $ids)
    {
        $collection = new Collection();

        $ids = trim($ids);

        if (!empty($ids)) {
            $array = explode(",", $ids);

            foreach ($array as $id) {
                try {
                    if (is_numeric($id)) {
                        $object = $mapper->findById($id);
                    } else {
                        $object = $mapper->findOneByColumn('name', $id);
                    }
                    $collection->add($object);
                } catch (MapperException $e) {
                }
            }
        }

        return $collection;
    }
}