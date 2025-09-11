<?php

namespace Schoolbox\User\Pronoun;

use Schoolbox\ISoftDeletable;
use Schoolbox\Sequential;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\ValueLabelInterface;

class Model implements ISoftDeletable, Sequential, ValueLabelInterface
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\CreateCollection;
    use ModelTrait\HasSequence;

    public const SUBJECTIVE_DEFAULT = 'they';
    public const OBJECTIVE_DEFAULT = 'them';
    public const POSSESSIVE_DEFAULT = 'their';
    public const POSSESSIVE_ADJECTIVE_DEFAULT = 'their';
    public const REFLEXIVE_DEFAULT = 'themselves';

    protected ?string $subjective = null;
    protected ?string $objective = null;
    protected ?string $possessive = null;
    protected ?string $possessiveAdjective = null;
    protected ?string $reflexive = null;

    /**
     * @param ?string $subjective
     * @return $this
     */
    public function setSubjective(?string $subjective): self
    {
        $this->subjective = $subjective;
        return $this;
    }

    /**
     * @return ?string
     */
    public function getSubjective(): ?string
    {
        return $this->subjective;
    }

    /**
     * @param string $objective
     * @return $this
     */
    public function setObjective(?string $objective): self
    {
        $this->objective = $objective;
        return $this;
    }

    /**
     * @return ?string
     */
    public function getObjective(): ?string
    {
        return $this->objective;
    }

    /**
     * @param string $possessive
     * @return $this
     */

    public function setPossessive(?string $possessive): self
    {
        $this->possessive = $possessive;
        return $this;
    }

    /**
     * @return ?string
     */

    public function getPossessive(): ?string
    {
        return $this->possessive;
    }

    /**
     * @param ?string $possessiveAdjective
     * @return $this
     */
    public function setPossessiveAdjective(?string $possessiveAdjective): self
    {
        $this->possessiveAdjective = $possessiveAdjective;
        return $this;
    }

    /**
     * @return ?string
     */
    public function getPossessiveAdjective(): ?string
    {
        return $this->possessiveAdjective;
    }

    /**
     * @param ?string $reflexive
     * @return $this
     */
    public function setReflexive(?string $reflexive): self
    {
        $this->reflexive = $reflexive;
        return $this;
    }

    /**
     * @return ?string
     */
    public function getReflexive(): ?string
    {
        return $this->reflexive;
    }

    /**
     * Find a key/value representation of this model's data
     * and return it as a 2D array of value (an identifier)
     * and label (a human-readable description, usually)
     *
     * return [
     *     'value' => ...
     *     'label' => ...
     * ];
     *
     * @return array
     */
    public function findValueLabelPair()
    {
        return [
            'value' => $this->getId(),
            'label' => $this->__toString(),
        ];
    }

    /**
     * helpful standard return / display function
     * @return string
     */
    public function __toString(): string
    {
        // if both pronoun terms are missing, then return a blank string
        if (empty($this->subjective) && empty($this->objective)) {
            return "";
        }
        // if missing a pronoun term, dont show a weird /
        // missing subjective, but objective exists
        if (empty($this->subjective) && !empty($this->objective)) {
            return $this->objective;
        }
        // missing objective, but subjective exists
        if (!empty($this->subjective) && empty($this->objective)) {
            return $this->subjective;
        }

        // both terms exist display the concat of them
        return sprintf("(%s/%s)", $this->subjective, $this->objective);
    }
}
