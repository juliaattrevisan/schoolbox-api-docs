<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\CMS\Log;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\File\Model as File;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Resource\Link;
use Schoolbox\Resource\My\File\Mapper as MyFileMapper;
use Schoolbox\Resource\My\File\Model as MyFile;
use Schoolbox\Resource\My\Folder\Mapper as MyFolderMapper;
use Schoolbox\Resource\My\Folder\Model as MyFolder;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Resource\Textbox\Model as Textbox;

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
                'mapper' => FileMapper::class,
                'other_key' => 'id',
                'this_key' => 'objectId',
                'multiple' => false,
            )
        );
        $this->hasJoin(
            'folder',
            array(
                'mapper' => FolderMapper::class,
                'other_key' => 'id',
                'this_key' => 'objectId',
                'multiple' => false,
            )
        );

        $this->hasJoin(
            'link',
            array(
                'mapper' => LinkMapper::class,
                'other_key' => 'id',
                'this_key' => 'objectId',
                'multiple' => false,
            )
        );
        $this->hasJoin(
            'myFile',
            array(
                'mapper' => MyFileMapper::class,
                'other_key' => 'id',
                'this_key' => 'objectId',
                'multiple' => false,
            )
        );
        $this->hasJoin(
            'myFolder',
            array(
                'mapper' => MyFolderMapper::class,
                'other_key' => 'id',
                'this_key' => 'objectId',
                'multiple' => false,
            )
        );
        $this->hasJoin(
            'textBox',
            array(
                'mapper' => TextboxMapper::class,
                'other_key' => 'textBoxId',
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
                {$this->getJoinColumnString('link', 'li')},
                {$this->getJoinColumnString('textBox', 't')},
                {$this->getJoinColumnString('myFile', 'mf')},                
                {$this->getJoinColumnString('myFolder', 'ml')}
        ";
    }
    /**
     * @return string
     */
    protected function getFromString()
    {
        return parent::getFromString() . "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('folder', '', 'fo')} AND object = 'folder'
            LEFT JOIN {$this->getJoinSql('file', '', 'fi')} AND object = 'file'
            LEFT JOIN {$this->getJoinSql('link', '', 'li')} AND object = 'link'
            LEFT JOIN {$this->getJoinSql('textBox', '', 't')} AND object = 'content'
            LEFT JOIN {$this->getJoinSql('myFile', '', 'mf')} AND object = 'myfile'
            LEFT JOIN {$this->getJoinSql('myFolder', '', 'ml')} AND object = 'myfolder'
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $records = $this->createCollection();

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
            if (!empty($row['t.textboxID'])) {
                /** @var Textbox $textbox */
                $textbox = $this->hydrateJoin($row, 'textBox', 't');
                $record->setTextbox($textbox);
            }
            if (!empty($row['mf.id'])) {
                /** @var Myfile $myFile */
                $myFile = $this->hydrateJoin($row, 'myFile', 'mf');
                $record->setMyfile($myFile);
            }
            if (!empty($row['ml.id'])) {
                /** @var MyFolder $myFolder */
                $myFolder = $this->hydrateJoin($row, 'myFolder', 'ml');
                $record->setMyfolder($myFolder);
            }

            $records->add($record);
        }

        return $records;
    }
}
