<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric;

/**
 * Object representation of a mark given for a descriptor.
 */
class DescriptorMark implements Calculable, Twiggable
{
    /**
     * @var int
     */
    private $id;


    /**
     * @var Descriptor
     */
    private $descriptor;


    /**
     * @var float
     */
    private $value;


    /**
     * Constructor
     *
     * @param Descriptor $descriptor
     * @param int|null   $id
     * @param float|null $value
     */
    public function __construct(Descriptor $descriptor, int $id = null, float $value = null)
    {
        $this->descriptor = $descriptor;
        $this->value      = $value;
    }


    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }


    /**
     * @param int|null $id
     * @return static
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getValue(): ?float
    {
        return $this->value;
    }


    /**
     * @param float|null $value
     * @return static
     */
    public function setValue(?float $value): self
    {
        $this->value = $value;
        return $this;
    }


    /**
     * Return the calculated sum value of this Calculable (NOT the total possible value)
     *
     * @return float|null
     */
    public function getSum(): ?float
    {
        // This should always be selected
        return $this->getValue();
    }


    /**
     * Returns the total possible value of this Calculable
     *
     * @alias getValue()
     * @return float|null
     */
    public function getTotal(): ?float
    {
        return $this->getValue();
    }


    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->descriptor->getText();
    }


    /**
     * Convert yourself into a Twig context array
     *
     * @return array
     */
    public function toContext(): array
    {
        return [
            'id'       => $this->getId(),
            'text'     => $this->descriptor->getText(),
            'value'    => $this->getValue(),
            'selected' => true,
            'sum'      => $this->getSum(),
            'total'    => $this->getTotal(),
            'position' => [
                'level' => [
                    'id' => $this->descriptor->getLevel()->getId(),
                    'name' => $this->descriptor->getLevel()->getName(),
                ],
                'criterion' => [
                    'id' => $this->descriptor->getCriterion()->getId(),
                    'name' => $this->descriptor->getCriterion()->getName(),
                ]
            ]
        ];
    }
}
