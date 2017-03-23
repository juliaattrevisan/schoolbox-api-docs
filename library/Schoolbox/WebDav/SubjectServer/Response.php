<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\IResponse;

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
     * Constructor
     *
     * @param IResponse $response
     */
    public function __construct(IResponse $response)
    {
        $this->response = $response;
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
                $children[] = new ResponseFile($file);
            }
        }
        return $children;
    }
}
