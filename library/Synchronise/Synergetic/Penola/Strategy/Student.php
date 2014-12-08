<?php
/**
 * Synchronise_Synergetic_Penola_Strategy_Student
 *
 * @author    Matt Day <matt.day@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Penola_Strategy_Student extends Synchronise_Base_Strategy_Student {

	/**
	 * Setup verbose output and hydrator classes.
	 */
	protected function setup() {
		$this->verbose_filter = new Synchronise_Synergetic_Penola_Verbose();

		$this
			->addHydrator(new Synchronise_Base_Hydrator_Default())
			->addHydrator(new Synchronise_Base_Hydrator_Student())
			->addHydrator(new Synchronise_Base_Hydrator_Webmail())
			->addHydrator(new Synchronise_Synergetic_Penola_Hydrator_Campus())
			->addHydrator(new Synchronise_Synergetic_Penola_Hydrator_Group_Student())
			->addHydrator(new Synchronise_Synergetic_Penola_Hydrator_House())
			->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
			->addHydrator(new Synchronise_Base_Hydrator_Clear());
	}
}
