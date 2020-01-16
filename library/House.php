<?php

use Schoolbox\ISoftDeletable;
use Schoolbox\Sequential;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\ValueLabelInterface;

class House implements ValueLabelInterface, ISoftDeletable, Sequential
{

    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\UpdatedBy;
    use ModelTrait\CreateCollection;
    use ModelTrait\HasSequence;

    protected $id;

    protected $house;

    public function setHouse($house)
    {
        $this->house = $house;
    }

    public function getHouse()
    {
        return $this->house;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
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
            'label' => $this->getHouse()
        ];
    }
}
