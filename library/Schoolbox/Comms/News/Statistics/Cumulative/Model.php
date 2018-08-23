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
     * @var \Alaress_Date_Format
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
     * @var int
     */
    private $numOpens;


    /**
     * The total number of Impressions for this model
     *
     * @return int
     */
    public function findTotalImpressions(): int
    {
        return $this->numImpressionsArchive +
            $this->numImpressionsDashboard +
            $this->numImpressionsEmail +
            $this->numImpressionsHomepage +
            $this->numImpressionsIndex;
    }


    /**
     * The total number of Conversions for this model
     *
     * @return int
     */
    public function findTotalConversions(): int
    {
        return $this->numConversionsArchive +
            $this->numConversionsDashboard +
            $this->numConversionsEmail +
            $this->numConversionsHomepage +
            $this->numConversionsIndex;
    }


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
     * @return \Alaress_Date_Format
     */
    public function getDate(): \Alaress_Date_Format
    {
        return $this->date;
    }


    /**
     * @param \Alaress_Date_Format $date
     * @return Model
     */
    public function setDate(\Alaress_Date_Format $date): self
    {
        $this->date = $date;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getFirstOpen(): ?string
    {
        return $this->firstOpen;
    }


    /**
     * @param null|string $firstOpen
     * @return Model
     */
    public function setFirstOpen(?string $firstOpen): self
    {
        $this->firstOpen = $firstOpen;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumImpressionsDashboard(): ?int
    {
        return $this->numImpressionsDashboard ?: 0;
    }


    /**
     * @param null|int $numImpressionsDashboard
     * @return Model
     */
    public function setNumImpressionsDashboard(?int $numImpressionsDashboard): self
    {
        $this->numImpressionsDashboard = $numImpressionsDashboard;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumImpressionsHomepage(): ?int
    {
        return $this->numImpressionsHomepage ?: 0;
    }


    /**
     * @param null|int $numImpressionsHomepage
     * @return Model
     */
    public function setNumImpressionsHomepage(?int $numImpressionsHomepage): self
    {
        $this->numImpressionsHomepage = $numImpressionsHomepage;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumImpressionsIndex(): ?int
    {
        return $this->numImpressionsIndex ?: 0;
    }


    /**
     * @param null|int $numImpressionsIndex
     * @return Model
     */
    public function setNumImpressionsIndex(?int $numImpressionsIndex): self
    {
        $this->numImpressionsIndex = $numImpressionsIndex;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumImpressionsArchive(): ?int
    {
        return $this->numImpressionsArchive ?: 0;
    }


    /**
     * @param null|int $numImpressionsArchive
     * @return Model
     */
    public function setNumImpressionsArchive(?int $numImpressionsArchive): self
    {
        $this->numImpressionsArchive = $numImpressionsArchive;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumImpressionsEmail(): ?int
    {
        return $this->numImpressionsEmail ?: 0;
    }


    /**
     * @param null|int $numImpressionsEmail
     * @return Model
     */
    public function setNumImpressionsEmail(?int $numImpressionsEmail): self
    {
        $this->numImpressionsEmail = $numImpressionsEmail;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumConversionsDashboard(): ?int
    {
        return $this->numConversionsDashboard ?: 0;
    }


    /**
     * @param null|int $numConversionsDashboard
     * @return Model
     */
    public function setNumConversionsDashboard(?int $numConversionsDashboard): self
    {
        $this->numConversionsDashboard = $numConversionsDashboard;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumConversionsHomepage(): ?int
    {
        return $this->numConversionsHomepage ?: 0;
    }


    /**
     * @param null|int $numConversionsHomepage
     * @return Model
     */
    public function setNumConversionsHomepage(?int $numConversionsHomepage): self
    {
        $this->numConversionsHomepage = $numConversionsHomepage;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumConversionsIndex(): ?int
    {
        return $this->numConversionsIndex ?: 0;
    }


    /**
     * @param null|int $numConversionsIndex
     * @return Model
     */
    public function setNumConversionsIndex(?int $numConversionsIndex): self
    {
        $this->numConversionsIndex = $numConversionsIndex;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumConversionsArchive(): ?int
    {
        return $this->numConversionsArchive ?: 0;
    }


    /**
     * @param null|int $numConversionsArchive
     * @return Model
     */
    public function setNumConversionsArchive(?int $numConversionsArchive): self
    {
        $this->numConversionsArchive = $numConversionsArchive;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumConversionsEmail(): ?int
    {
        return $this->numConversionsEmail ?: 0;
    }


    /**
     * @param null|int $numConversionsEmail
     * @return $this
     */
    public function setNumConversionsEmail(?int $numConversionsEmail): self
    {
        $this->numConversionsEmail = $numConversionsEmail;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getNumOpens(): ?int
    {
        return $this->numOpens ?: 0;
    }


    /**
     * @param null|int $numOpens
     * @return Model
     */
    public function setNumOpens(?int $numOpens): self
    {
        $this->numOpens = $numOpens;
        return $this;
    }
}
