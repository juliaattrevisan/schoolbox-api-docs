<?php
/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Cumulative;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var string
     */
    private $date;
    
    /**
     * @var int
     */
    private $articleId;

    /**
     * @var string
     */
    private $firstOpen;


    /**
     * @var int
     */
    private $numImpressionsDashboard;

    
    /**
     * @var int
     */
    private $numImpressionsHomepage;

    
    /**
     * @var int
     */
    private $numImpressionsIndex;

    
    /**
     * @var int
     */
    private $numImpressionsArchive;

    
    /**
     * @var int
     */
    private $numImpressionsEmail;


    /**
     * @var int
     */
    private $numConversionsDashboard;


    /**
     * @var int
     */
    private $numConversionsHomepage;


    /**
     * @var int
     */
    private $numConversionsIndex;


    /**
     * @var int
     */
    private $numConversionsArchive;


    /**
     * @var int
     */
    private $numConversionsEmail;


    /**
     * @return null|int
     */
    public function getArticleId(): ?int
    {
        return $this->articleId;
    }


    /**
     * @param int $articleId
     * @return $this
     */
    public function setArticleId(int $articleId): self
    {
        $this->articleId = $articleId;
        return $this;
    }


    /**
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }


    /**
     * @param string $date
     * @return Model
     */
    public function setDate(string $date): self
    {
        $this->date = $date;
        return $this;
    }


    /**
     * @return string
     */
    public function getFirstOpen(): string
    {
        return $this->firstOpen;
    }


    /**
     * @param string $firstOpen
     * @return Model
     */
    public function setFirstOpen(string $firstOpen): self
    {
        $this->firstOpen = $firstOpen;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumImpressionsDashboard(): int
    {
        return $this->numImpressionsDashboard;
    }


    /**
     * @param int $numImpressionsDashboard
     * @return Model
     */
    public function setNumImpressionsDashboard(int $numImpressionsDashboard): self
    {
        $this->numImpressionsDashboard = $numImpressionsDashboard;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumImpressionsHomepage(): int
    {
        return $this->numImpressionsHomepage;
    }


    /**
     * @param int $numImpressionsHomepage
     * @return Model
     */
    public function setNumImpressionsHomepage(int $numImpressionsHomepage): self
    {
        $this->numImpressionsHomepage = $numImpressionsHomepage;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumImpressionsIndex(): int
    {
        return $this->numImpressionsIndex;
    }


    /**
     * @param int $numImpressionsIndex
     * @return Model
     */
    public function setNumImpressionsIndex(int $numImpressionsIndex): self
    {
        $this->numImpressionsIndex = $numImpressionsIndex;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumImpressionsArchive(): int
    {
        return $this->numImpressionsArchive;
    }


    /**
     * @param int $numImpressionsArchive
     * @return Model
     */
    public function setNumImpressionsArchive(int $numImpressionsArchive): self
    {
        $this->numImpressionsArchive = $numImpressionsArchive;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumImpressionsEmail(): int
    {
        return $this->numImpressionsEmail;
    }


    /**
     * @param int $numImpressionsEmail
     * @return Model
     */
    public function setNumImpressionsEmail(int $numImpressionsEmail): self
    {
        $this->numImpressionsEmail = $numImpressionsEmail;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumConversionsDashboard(): int
    {
        return $this->numConversionsDashboard;
    }


    /**
     * @param int $numConversionsDashboard
     * @return Model
     */
    public function setNumConversionsDashboard(int $numConversionsDashboard): self
    {
        $this->numConversionsDashboard = $numConversionsDashboard;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumConversionsHomepage(): int
    {
        return $this->numConversionsHomepage;
    }


    /**
     * @param int $numConversionsHomepage
     * @return Model
     */
    public function setNumConversionsHomepage(int $numConversionsHomepage): self
    {
        $this->numConversionsHomepage = $numConversionsHomepage;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumConversionsIndex(): int
    {
        return $this->numConversionsIndex;
    }


    /**
     * @param int $numConversionsIndex
     * @return Model
     */
    public function setNumConversionsIndex(int $numConversionsIndex): self
    {
        $this->numConversionsIndex = $numConversionsIndex;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumConversionsArchive(): int
    {
        return $this->numConversionsArchive;
    }


    /**
     * @param int $numConversionsArchive
     * @return Model
     */
    public function setNumConversionsArchive(int $numConversionsArchive): self
    {
        $this->numConversionsArchive = $numConversionsArchive;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumConversionsEmail(): int
    {
        return $this->numConversionsEmail;
    }


    /**
     * @param int $numConversionsEmail
     * @return $this
     */
    public function setNumConversionsEmail(int $numConversionsEmail): self
    {
        $this->numConversionsEmail = $numConversionsEmail;
        return $this;
    }
}
