<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response\SubSource;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkObject;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Response\IResponseDataSource;

/**
 * Sub-source for due work responses.
 */
class DueWork implements IResponseDataSource
{
    /**
     * @var MapperProxy
     */
    protected $mapperProxy;

    /**
     * Constructor
     *
     * @param MapperProxy $mapperProxy
     */
    public function __construct(MapperProxy $mapperProxy)
    {
        $this->mapperProxy = $mapperProxy;
    }

    /**
     * @inheritdoc
     */
    public function recognizes(IAssessable $assessment)
    {
        return $assessment instanceof DueWorkObject;
    }

    /**
     * @inheritdoc
     */
    public function findForAssessment(IAssessable $assessment)
    {
        if ($assessment instanceof DueWorkObject) {
            // Use the mapper proxy to get all the things
            return $this->mapperProxy->findByColumnList(['assessment_id' => $assessment->getId()]);
        }
        return new Collection();
    }
}
