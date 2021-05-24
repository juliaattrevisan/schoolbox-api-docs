<?php

/**
 * ExtensionMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\File;

use Schoolbox\Collection;
use Schoolbox\Mapper;

/**
 * Class ExtensionMapper
 *
 * @package Schoolbox\File
 * @method Collection|Extension[] findByColumn($columnName, $columnValue, $options = [])
 */
class ExtensionMapper extends Mapper
{

    /**
     * Mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('allowedFileExtension');
        $this->hasModel(Extension::class);

        $this->hasMap(array(
            'column'     => 'fileTypeId',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'extension',
            'property'   => 'extension',
            'type'       => self::TYPE_STRING,
        ));
    }


    /**
     * Find the file extension from a given mimetype
     * If it can't find something that matches, throw an exception
     * since we're now dealing with potential security hazard
     *
     * @param string $mimeType
     * @return string
     * @throws \Schoolbox\Mapper\Exception
     * @throws \PDOException
     */
    public function findExtensionFromMimeType(string $mimeType): string
    {
        $query = "
            SELECT extension
            FROM {$this->table} e
            INNER JOIN allowedFileTypes aft ON e.fileTypeID = aft.fileTypeID
            WHERE aft.mime = :mimeType
            LIMIT 1
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'mimeType' => $mimeType
        ]);

        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($result && \count($result)) {
            return $result['extension'];
        }

        throw new Mapper\Exception('Unknown file extension.');
    }


    /**
     * All the allowed file extensions
     *
     * @return Collection
     * @throws Mapper\Exception
     * @throws \PDOException
     */
    public function findAllowedExtensions(): Collection
    {
        $query = "
            SELECT {$this->getColumnString()}
            FROM allowedFileExtension
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();

        $collection = new Collection();

        foreach ($statement as $row) {
            $extension = $this->hydrate($row);

            $collection->add($extension);
        }

        return $collection;
    }
}
