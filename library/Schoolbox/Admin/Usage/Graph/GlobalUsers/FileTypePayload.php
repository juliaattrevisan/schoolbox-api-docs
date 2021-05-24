<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Usage\Graph\GlobalUsers;

use Alaress\Database\Connector\IConnector;

/**
 * Class FileTypePayload
 *
 * Storage usage, grouped by mime type
 *
 * @package Schoolbox\Admin\Usage\Graph\GlobalUsers
 */
class FileTypePayload
{

    /**
     * @var IConnector
     */
    private IConnector $database;


    /**
     * System constructor.
     *
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }


    /**
     * Generate usage occupied by each mime type in storage
     *
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function generate(): array
    {
        $query = "
            SELECT 
                'Image' AS label,
                SUM(filesize) AS size
            FROM storage
            INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
            INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
            WHERE mime LIKE 'image/%'
            
            UNION
            
            SELECT 
                'Video' AS label,
                SUM(filesize) AS size
            FROM storage
            INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
            INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
            WHERE mime LIKE 'video/%'
            
            UNION
            
            SELECT 
                'Application' AS label,
                SUM(filesize) AS size
            FROM storage
            INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
            INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
            WHERE mime LIKE 'application/%'
            
            UNION
            
            SELECT 
                'Audio' AS label,
                SUM(filesize) AS size
            FROM storage
            INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
            INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
            WHERE mime LIKE 'audio/%'
            
            UNION
            
            SELECT 
                'Text' AS label,
                SUM(filesize) AS size
            FROM storage
            INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
            INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
            WHERE mime LIKE 'text/%'
            
            UNION
            
            SELECT 
                'Other' AS label,
                SUM(filesize) AS size               
            FROM storage
            LEFT JOIN allowedFileExtension afe on storage.ext = afe.extension
            WHERE fileTypeID IS NULL
        ";

        $result = $this->database->executeQuery($query);

        $stack = [];

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $stack[] = $row;
        }

        return $stack;
    }
}
