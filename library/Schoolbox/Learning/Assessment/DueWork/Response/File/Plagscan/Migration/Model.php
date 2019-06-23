<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Migration;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Storage\IStorage;

class Model implements IStorage, ISoftDeletable
{
    use StorableTrait;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    protected $plagscanDocumentRecordId;

    /**
     * @return int
     */
    public function getplagscanDocumentRecordId()
    {
        return $this->plagscanDocumentRecordId;
    }

    /**
     * @param int $plagscanDocumentRecordId
     *
     * @return self|$this
     */
    public function setplagscanDocumentRecordId($plagscanDocumentRecordId)
    {
        $this->plagscanDocumentRecordId = $plagscanDocumentRecordId;

        return $this;
    }

}
