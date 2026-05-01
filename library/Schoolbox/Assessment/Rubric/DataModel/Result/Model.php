<?php

/**
 * Model for rubric result, these make up results in a result set that can be used to populate a rubric
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Result;

class Model
{
    /**
     * Maximum length of a rubric result set label as used in Admin > LMS > Rubrics
     *
     * The schema currently says 1024 chars, but we found that some multibyte
     * chars or other non-printable chars take up extra bytes and can bust the limit
     * somewhat inadvertently - we give them a few extra chars as a buffer for that
     */
    public const int LABEL_MAXLENGTH = 900;


    /**
     * @var int
     */
    private int $resultSetId;


    /**
     * @var string
     */
    private string $label;


    /**
     * @var int
     */
    private int $value;


    /**
     * @return int
     */
    public function getResultSetId(): int
    {
        return $this->resultSetId;
    }


    /**
     * @param int $resultSetId
     * @return $this
     */
    public function setResultSetId(int $resultSetId): self
    {
        $this->resultSetId = $resultSetId;
        return $this;
    }


    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }


    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }


    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }


    /**
     * @param int $value
     * @return $this
     */
    public function setValue(int $value): self
    {
        $this->value = $value;
        return $this;
    }
}
