<?php
/**
 * Standardised interface for all SynergeticSync Strategies. These are methods
 * which must exist in all SynergeticSync Strategies.
 */
interface SynergeticSyncStrategyInterface {

	/**
	 * Get a list of records to process.
	 *
	 * @return void
	 */
	public function generateList();

	/**
	 * Process them
	 *
	 * @param  $performUpdate
	 * @return void
	 */
	public function createIfExists();
}