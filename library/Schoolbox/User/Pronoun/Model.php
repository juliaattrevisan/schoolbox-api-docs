<?php

namespace Schoolbox\User\Pronoun;

use Schoolbox\ISoftDeletable;
use Schoolbox\Sequential;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements ISoftDeletable, Sequential
{
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\UpdatedBy;
    use ModelTrait\CreateCollection;
    use ModelTrait\HasSequence;

    public const SUBJECTIVE_DEFAULT = 'they';
    public const OBJECTIVE_DEFAULT = 'them';
    public const POSSESSIVE_DEFAULT = 'their';


    protected int $id;

    protected string $subjective = self::SUBJECTIVE_DEFAULT;
    protected string $objective = self::OBJECTIVE_DEFAULT;
    protected string $possessive = self::POSSESSIVE_DEFAULT;

    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $subjective
     */
    public function setSubjective(string $subjective): void
    {
        $this->subjective = $subjective;
    }

    /**
     * @return string
     */
    public function getSubjective(): string
    {
        return $this->subjective;
    }

    /**
     * @param string $objective
     */
    public function setObjective(string $objective): void
    {
        $this->objective = $objective;
    }

    /**
     * @return string
     */
    public function getObjective(): string
    {
        return $this->objective;
    }

    /**
     * @param string $possessive
     */

    public function setPossessive(string $possessive): void
    {
        $this->possessive = $possessive;
    }

    /**
     * @return string
     */

    public function getPossessive(): string
    {
        return $this->possessive;
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
    public function getTerm()
    {
        return [
            'id' => $this->getId(),
            'term' => sprintf("%s / %s", $this->subjective, $this->objective)
        ];
    }
}
