<?php
 
class HouseResidential extends Model {

    public $id;

    public $house;

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
}
