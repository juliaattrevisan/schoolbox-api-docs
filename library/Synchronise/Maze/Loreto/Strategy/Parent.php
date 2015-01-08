<?php
/**
 * Synchronise_Maze_Loreto_Strategy_Parent
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Maze_Loreto_Strategy_Parent extends Synchronise_Base_Strategy_Parent {

	/**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_Maze_Loreto_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
			->addHydrator(new Synchronise_Base_Hydrator_DisableEportfolio())
			->addHydrator(new Synchronise_Maze_Loreto_Hydrator_ParentEmail())
            ->addHydrator(new Synchronise_Base_Hydrator_Parent())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Default())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Spouse())
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }
}
