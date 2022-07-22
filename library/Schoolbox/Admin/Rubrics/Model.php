<?php

/**
 * Rubrics Mapper
 *
 * @author    Tom McDonnell <tom.mcdonnell@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Rubrics;

class Model
{
    /**
     * @var int
     */
    private int $resultset_id;

    /**
     * @var string
     */
    private string $value;

    /**
     * @var string
     */
    private string $label;

    /**
     * @return int
     */
    public function getResultsetId(): int
    {
        return $this->resultset_id;
    }

    /**
     * @param int $resultset_id
     * @return Model
     */
    public function setResultsetId(int $resultset_id): Model
    {
        $this->resultset_id = $resultset_id;
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
     * @param string $value
     * @return Model
     */
    public function setValue(string $value): Model
    {
        $this->value = $value;
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
     * @return Model
     */
    public function setLabel(string $label): Model
    {
        $this->label = $label;
        return $this;
    }
}
