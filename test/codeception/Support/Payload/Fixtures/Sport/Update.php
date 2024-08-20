<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Fixtures\Sport;

use Tests\Support\Payload\Request;
use Tests\Support\Payload\PostTrait;
use Schoolbox\Collection;

class Update extends Request
{
    use PostTrait;

    private \Schoolbox\Fixtures\Sport\Model $sport;
    /** @var Collection positions */
    private $positions;
    private int $positions_amount;

    public function __construct(\Schoolbox\Fixtures\Sport\Model $sport)
    {
        $this->sport = $sport;

        $this->setReferer('/fixtures/sports/' . $this->sport->getId() . '/edit');

        $this->setFormParam('id', $sport->getId());
        $this->setFormParam('name', $sport->getName());
        $this->setFormParam('url', $sport->getUrl());

        $this->positions = $sport->getPositions();

        $this->buildPositionParams();
    }

    private function removeAllPositionsParams()
    {
        while (0 < $this->positions_amount) {
            $this->unsetFormParam('positions[sportId][' . ($this->positions_amount - 1) . ']');
            $this->unsetFormParam('positions[id][' . ($this->positions_amount - 1) . ']');
            $this->unsetFormParam('positions[name][' . ($this->positions_amount - 1) . ']');
            $this->positions_amount--;
        }
    }

    private function buildPositionParams()
    {
        $amount_of_positions = 0;
        foreach ($this->positions as $position) {
            $this->setFormParam('positions[sportId][' . $amount_of_positions . ']', $position->getSport()->getId());
            if ($position->getId() != null) {
                $this->setFormParam('positions[id][' . $amount_of_positions . ']', $position->getId());
            } else {
                $this->setFormParam('positions[id][' . $amount_of_positions . ']', '');
            }
            $this->setFormParam('positions[name][' . $amount_of_positions . ']', $position->getName());
            $amount_of_positions++;
        }

        $this->positions_amount = $amount_of_positions;
    }

    /**
     * Just a note on position_number, this is not the array position. This is the position number as seen by users. So array_position+1
     * @param int $position_number
     * @throws \Exception
     */
    public function deletePosition(int $position_number)
    {
        if (0 < $position_number && $position_number <= $this->positions_amount) {
            $new_positions = new Collection();
            $counter = 0;
            foreach ($this->positions as $position) {
                if ($position->getId() != $this->getFormParam('positions[id][' . ($position_number - 1) . ']')) {
                    $new_positions->add($position);
                    $counter++;
                }
            }
            $this->positions = $new_positions;
            $this->removeAllPositionsParams();
            $this->buildPositionParams();
        } else {
            throw new \Exception('ERROR - The position number for sport does not exist.');
        }
    }

    public function addPosition(string $positionName)
    {
        $new_position = new \Schoolbox\Fixtures\Position\Model();
        $new_position->setName($positionName);
        $new_position->setSport($this->sport);
        $this->positions->add($new_position);
        $this->removeAllPositionsParams();
        $this->buildPositionParams();
    }

    /**
     * Just a note on position_number, this is not the array position. This is the position number as seen by users. So array_position+1
     * @param int $position_number
     * @param string $new_name
     */
    public function editPosition(int $position_number, string $new_name)
    {
        $this->positions[($position_number - 1)]->setName($new_name);
        $this->setFormParam('positions[name][' . ($position_number - 1) . ']', $new_name);
    }

    public function setName(string $new_sport_name)
    {
        $this->sport->setName($new_sport_name);
        $this->setFormParam('name', $new_sport_name);
    }

    public function getUrl(): string
    {
        return '/fixtures/sports/' . $this->sport->getId();
    }
}
