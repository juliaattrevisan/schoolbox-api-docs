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
     * @var int
     */
    private $numOpens;


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
     * @return null|string
     */
    public function getDate(): ?string
    {
        return $this->date;
    }


    /**
     * @param null|string $date
     * @return Model
     */
    public function setDate(?string $date): self
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
        return $this->numImpressionsDashboard;
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
        return $this->numImpressionsHomepage;
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
        return $this->numImpressionsIndex;
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
        return $this->numImpressionsArchive;
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
        return $this->numImpressionsEmail;
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
        return $this->numConversionsDashboard;
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
        return $this->numConversionsHomepage;
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
        return $this->numConversionsIndex;
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
        return $this->numConversionsArchive;
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
        return $this->numConversionsEmail;
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
        return $this->numOpens;
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
