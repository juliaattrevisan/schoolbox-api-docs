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
    private $type;

    /**
     * @var string
     */
    private $referrer;

    /**
     * @var int
     */
    private $num;


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
    public function getType(): ?string
    {
        return $this->type;
    }


    /**
     * @param null|string $type
     * @return $this
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getReferrer(): ?string
    {
        return $this->referrer;
    }


    /**
     * @param null|string $referrer
     * @return $this
     */
    public function setReferrer(?string $referrer): self
    {
        $this->referrer = $referrer;
        return $this;
    }


    /**
     * @return int
     */
    public function getNum(): int
    {
        return $this->num;
    }


    /**
     * @param int $num
     * @return $this
     */
    public function setNum(int $num): self
    {
        $this->num = $num;
        return $this;
    }
}
