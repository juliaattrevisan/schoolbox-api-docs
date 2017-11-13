<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response\SubSource;

use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkObject;
use Schoolbox\Learning\Assessment\DueWork\Response\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Sub-source for due work responses.
 */
class DueWork extends AbstractSubSource
{
    /**
     * @inheritdoc
     */
    protected $studentField = 'owner';

    /**
     * Constructor
     *
     * @param MapperProxy $mapperProxy
     */
    public function __construct(MapperProxy $mapperProxy)
    {
        parent::__construct($mapperProxy);
    }

    /**
     * @inheritdoc
     */
    public function recognizes(IAssessable $assessment)
    {
        return $assessment instanceof DueWorkObject;
    }
}
