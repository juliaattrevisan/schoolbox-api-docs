<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response\SubSource;

use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkObject;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

/**
 * Sub-source for due work responses.
 */
class DueWork extends AbstractSubSource
{
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

    /**
     * @inheritdoc
     */
    public function findForStudent(User $student)
    {
        return $this->mapperProxy->findByColumnList(['owner' => $student->getId()]);
    }
}
