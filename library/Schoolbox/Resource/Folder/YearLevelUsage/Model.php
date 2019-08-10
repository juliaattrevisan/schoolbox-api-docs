<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder\YearLevelUsage;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\HasSequence;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder;
use Schoolbox\Sequential;
use Schoolbox\YearLevel;

/**
 * Represents an association between a Folder and YearLevel
 */
class Model implements ISoftDeletable, Sequential
{
    use HasSequence;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var Folder\Model */
    private $folder;

    /** @var YearLevel\Model */
    private $yearLevel;

    /**
     * @return Folder\Model
     */
    public function getFolder(): ?Folder\Model
    {
        return $this->folder;
    }

    /**
     * @param Folder\Model $folder
     * @return Model
     */
    public function setFolder(Folder\Model $folder): Model
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return YearLevel\Model
     */
    public function getYearLevel(): ?YearLevel\Model
    {
        return $this->yearLevel;
    }

    /**
     * @param YearLevel\Model $yearLevel
     * @return Model
     */
    public function setYearLevel(YearLevel\Model $yearLevel): Model
    {
        $this->yearLevel = $yearLevel;
        return $this;
    }
}