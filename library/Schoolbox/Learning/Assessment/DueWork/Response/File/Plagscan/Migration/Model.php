<?php
/**
 * Plagscan Document Migration
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Migration;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as PlagscanDocument;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Storage\IStorage;

class Model implements IStorage, ISoftDeletable
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use StorableTrait;

    /** @var PlagscanDocument $plagscanDocument */
    protected $plagscanDocument;

    /** @var Alaress_Date_Format $storageMigratedAt */
    protected $storageMigratedAt;

    /** @var Alaress_Date_Format $storageFailedAt */
    protected $storageFailedAt;

    /**
     * Return boolean flag indicating if the document has been successfully migrated to OTFS storage
     *
     * @return bool
     */
    public function isMigrated(): bool
    {
        if (!$this->getStorage() instanceof IStorage) {
            return false;
        }

        return !empty($this->getStorage()->getHash()) && !empty($this->getStorageMigratedAt());
    }

    // Getters/Setters

    /**
     * @return PlagscanDocument
     */
    public function getPlagscanDocument()
    {
        return $this->plagscanDocument;
    }

    /**
     * @param PlagscanDocument $plagscanDocument
     *
     * @return self|$this
     */
    public function setPlagscanDocument($plagscanDocument)
    {
        $this->plagscanDocument = $plagscanDocument;

        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getStorageMigratedAt()
    {
        return $this->storageMigratedAt;
    }

    /**
     * @param Alaress_Date_Format $storageMigratedAt
     *
     * @return self|$this
     */
    public function setStorageMigratedAt($storageMigratedAt)
    {
        $this->storageMigratedAt = $storageMigratedAt;

        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getStorageFailedAt()
    {
        return $this->storageFailedAt;
    }

    /**
     * @param Alaress_Date_Format $storageFailedAt
     *
     * @return self|$this
     */
    public function setStorageFailedAt($storageFailedAt)
    {
        $this->storageFailedAt = $storageFailedAt;

        return $this;
    }
}
