<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkResponseDataSource;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Resource\Storage\Mapper as StorageDataSource;

/**
 * A DAV collection representing a single submission within an assessment.
 */
class Response extends DAV\Collection
{
    /**
     * @var IResponse
     */
    private $response;

    /**
     * @var StorageDataSource
     */
    private $storageDataSource;

    /**
     * @var DueWorkResponseDataSource
     */
    private $dueWorkResponseDataSource;

    /**
     * Constructor
     *
     * @param IResponse                 $response
     * @param StorageDataSource         $storageDataSource
     * @param DueWorkResponseDataSource $dueWorkResponseDataSource
     */
    public function __construct(
        IResponse $response,
        StorageDataSource $storageDataSource,
        DueWorkResponseDataSource $dueWorkResponseDataSource
    ) {
        $this->response                  = $response;
        $this->storageDataSource         = $storageDataSource;
        $this->dueWorkResponseDataSource = $dueWorkResponseDataSource;
    }

    /**
     * Gets the time which this file was uploaded.
     *
     * @return int
     */
    public function getLastModified()
    {
        return $this->response->getUpdatedAt()->getUnixTimeStamp();
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        if (!$this->response->isSubmitted()) {
            return sprintf('Draft (%s)', $this->response->getCreatedAt()->getDatabase());
        }
        return sprintf('Submission (%s)', $this->response->getSubmittedAt()->getDatabase());
    }

    /**
     * Loads this submission's files.
     *
     * @return DAV\INode[]
     */
    public function getChildren()
    {
        $children = [];
        if ($this->response instanceof DueWorkResponse) {
            foreach ($this->response->getAllFiles() as $file) {
                $children[] = new ResponseFile(
                    $file,
                    $this->response,
                    $this->storageDataSource,
                    $this->dueWorkResponseDataSource
                );
            }
        }
        return $children;
    }
}
