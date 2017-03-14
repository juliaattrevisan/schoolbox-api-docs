<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\CMS\Log;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\File\Model as File;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Link;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('cmsLog');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('object', self::TYPE_STRING);
        $this->uses('objectId', self::TYPE_INTEGER);
        $this->uses('action', self::TYPE_STRING);
        $this->joins('user', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('time', self::TYPE_DATETIME);

        $this->hasJoin(
            'file',
            array(
                'mapper' => '\\Schoolbox\\Resource\\File\\Mapper',
                'other_key' => 'id',
                'this_key' => 'objectId',
                'multiple' => false,
            )
        );
        $this->hasJoin(
            'folder',
            array(
                'mapper' => '\\Schoolbox\\Resource\\Folder\\Mapper',
                'other_key' => 'id',
                'this_key' => 'objectId',
                'multiple' => false,
            )
        );

        $this->hasJoin(
            'link',
            array(
                'mapper' => '\\Schoolbox\\Resource\\LinkMapper',
                'other_key' => 'id',
                'this_key' => 'objectId',
                'multiple' => false,
            )
        );
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString('')},
                {$this->getJoinColumnString('folder', 'fo')},
                {$this->getJoinColumnString('file', 'fi')},
                {$this->getJoinColumnString('link', 'li')}
        ";
    }

    /**
     * @return string
     */
    protected function getFromString()
    {
        return parent::getFromString()."
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('folder', '', 'fo')}
            LEFT JOIN {$this->getJoinSql('file', '', 'fi')}
            LEFT JOIN {$this->getJoinSql('link', '', 'li')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $records = new Collection();

        foreach ($statement as $row) {
            /** @var Model $record */
            $record = $this->hydrate($row, '');

            // Hydrate everything on the record

            if (!empty($row['fi.id'])) {
                /** @var File $file */
                $file = $this->hydrateJoin($row, 'file', 'fi');
                $record->setFile($file);

            }

            if (!empty($row['fo.id'])) {
                /** @var Folder $folder */
                $folder = $this->hydrateJoin($row, 'folder', 'fo');
                $record->setFolder($folder);
            }

            if (!empty($row['li.id'])) {
                /** @var Link $link */
                $link = $this->hydrateJoin($row, 'link', 'li');
                $record->setLink($link);
            }

            $records->add($record);
        }

        return $records;
    }

}
