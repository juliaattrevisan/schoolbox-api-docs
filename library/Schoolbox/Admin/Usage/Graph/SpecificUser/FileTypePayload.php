<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Usage\Graph\SpecificUser;

use Alaress\Database\Connector\IConnector;
use Schoolbox\User;

/**
 * Class FileTypePayload
 *
 * Storage usage, grouped by mime type
 *
 * @package Schoolbox\Admin\Usage\Graph
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
     * Generate bytes used by each of the following areas where we can
     * easily and conclusively determine user ownership
     *
     * Image in file, myFile, student_submission_file, submission_return
     * Video in file, myFile, student_submission_file, submission_return
     * Application in file, myFile, student_submission_file, submission_return
     * Audio in file, myFile, student_submission_file, submission_return
     * Text in file, myFile, student_submission_file, submission_return
     *
     * @param User $user
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function generate(User $user): array
    {
        $query = "
            SELECT 
                'Image' AS label,
                SUM(filesize) AS size
            FROM (
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN file f ON storage.hash = f.hash
                INNER JOIN allowedFileExtension afe ON storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'image/%'
                AND f.deleted_at IS NULL
                AND f.owner = :userId
    
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN myFile mf ON storage.hash = mf.hash
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'image/%'
                AND mf.deleted_at IS NULL
                AND mf.owner = :userId
                
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN student_submission_file ssf ON storage.hash = ssf.hash
                INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'image/%'
                AND ssf.deleted_at IS NULL
                AND ss.deleted_at IS NULL
                AND ss.owner = :userId
                
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN submission_return_file srf ON storage.hash = srf.hash
                INNER JOIN submission_return sr ON srf.submission_return_id = sr.id
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'image/%'
                AND srf.deleted_at IS NULL
                AND sr.deleted_at IS NULL
                AND sr.owner = :userId
            ) imageUsage
            
            UNION
            
            SELECT 
                'Video' AS label,
                SUM(filesize) AS size
            FROM (
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN file f ON storage.hash = f.hash
                INNER JOIN allowedFileExtension afe ON storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'video/%'
                AND f.deleted_at IS NULL
                AND f.owner = :userId
    
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN myFile mf ON storage.hash = mf.hash
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'video/%'
                AND mf.deleted_at IS NULL
                AND mf.owner = :userId
                
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN student_submission_file ssf ON storage.hash = ssf.hash
                INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'video/%'
                AND ssf.deleted_at IS NULL
                AND ss.deleted_at IS NULL
                AND ss.owner = :userId
                
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN submission_return_file srf ON storage.hash = srf.hash
                INNER JOIN submission_return sr ON srf.submission_return_id = sr.id
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'video/%'
                AND srf.deleted_at IS NULL
                AND sr.deleted_at IS NULL
                AND sr.owner = :userId
            ) videoUsage
            
            UNION
            
            SELECT 
                'Application' AS label,
                SUM(filesize) AS size
            FROM (
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN file f ON storage.hash = f.hash
                INNER JOIN allowedFileExtension afe ON storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'application/%'
                AND f.deleted_at IS NULL
                AND f.owner = :userId
    
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN myFile mf ON storage.hash = mf.hash
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'application/%'
                AND mf.deleted_at IS NULL
                AND mf.owner = :userId
                
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN student_submission_file ssf ON storage.hash = ssf.hash
                INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'application/%'
                AND ssf.deleted_at IS NULL
                AND ss.deleted_at IS NULL
                AND ss.owner = :userId
                
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN submission_return_file srf ON storage.hash = srf.hash
                INNER JOIN submission_return sr ON srf.submission_return_id = sr.id
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'application/%'
                AND srf.deleted_at IS NULL
                AND sr.deleted_at IS NULL
                AND sr.owner = :userId
            ) applicationUsage
            
            UNION
            
            SELECT 
                'Audio' AS label,
                SUM(filesize) AS size
            FROM (
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN file f ON storage.hash = f.hash
                INNER JOIN allowedFileExtension afe ON storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'audio/%'
                AND f.deleted_at IS NULL
                AND f.owner = :userId
    
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN myFile mf ON storage.hash = mf.hash
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'audio/%'
                AND mf.deleted_at IS NULL
                AND mf.owner = :userId
                
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN student_submission_file ssf ON storage.hash = ssf.hash
                INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'audio/%'
                AND ssf.deleted_at IS NULL
                AND ss.deleted_at IS NULL
                AND ss.owner = :userId
                
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN submission_return_file srf ON storage.hash = srf.hash
                INNER JOIN submission_return sr ON srf.submission_return_id = sr.id
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'audio/%'
                AND srf.deleted_at IS NULL
                AND sr.deleted_at IS NULL
                AND sr.owner = :userId
            ) audioUsage
            
            UNION
            
            SELECT 
                'Text' AS label,
                SUM(filesize) AS size
            FROM (
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN file f ON storage.hash = f.hash
                INNER JOIN allowedFileExtension afe ON storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'text/%'
                AND f.deleted_at IS NULL
                AND f.owner = :userId
    
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN myFile mf ON storage.hash = mf.hash
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'text/%'
                AND mf.deleted_at IS NULL
                AND mf.owner = :userId
                
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN student_submission_file ssf ON storage.hash = ssf.hash
                INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'text/%'
                AND ssf.deleted_at IS NULL
                AND ss.deleted_at IS NULL
                AND ss.owner = :userId
                
                UNION 
                            
                SELECT SUM(filesize) AS filesize
                FROM storage
                INNER JOIN submission_return_file srf ON storage.hash = srf.hash
                INNER JOIN submission_return sr ON srf.submission_return_id = sr.id
                INNER JOIN allowedFileExtension afe on storage.ext = afe.extension
                INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                WHERE mime LIKE 'text/%'
                AND srf.deleted_at IS NULL
                AND sr.deleted_at IS NULL
                AND sr.owner = :userId
            ) textUsage
        ";

        $result = $this->database->executeQuery($query, [
            'userId' => $user->getId()
        ]);

        $stack = [];

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $stack[] = $row;
        }

        return $stack;
    }
}
