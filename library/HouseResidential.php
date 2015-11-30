<?php
 
class HouseResidential implements \Schoolbox\ValueLabelInterface {

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
