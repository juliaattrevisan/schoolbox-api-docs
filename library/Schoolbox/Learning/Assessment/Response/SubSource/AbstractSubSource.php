<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response\SubSource;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Response\IResponseDataSource;
use Schoolbox\Mapper\AbstractMapperProxy;

/**
 * Base class for response sub-sources.
 */
abstract class AbstractSubSource implements IResponseDataSource
{
    /**
     * @var AbstractMapperProxy
     */
    protected $mapperProxy;

    /**
     * Constructor
     *
     * @param AbstractMapperProxy $mapperProxy
     */
    public function __construct(AbstractMapperProxy $mapperProxy)
    {
        $this->mapperProxy = $mapperProxy;
    }

    /**
     * @param IAssessable $assessment
     * @return Collection|IResponse[]
     */
    public function findForAssessment(IAssessable $assessment)
    {
        if ($this->recognizes($assessment)) {
            return $this->mapperProxy->findByColumnList(['assessment_id' => $assessment->getId()])
                                     ->map(function ($item) use ($assessment) {
                                         /** @var IResponse $item */
                                         return $item->setAssessable($assessment);
                                     });
        }
        return new Collection();
    }

    public function save(IResponse $response)
    {
        // TODO: Implement save() method.
    }

    public function delete(IResponse $response)
    {
        // TODO: Implement delete() method.
    }
}
