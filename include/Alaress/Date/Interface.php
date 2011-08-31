<?php
/**
 * Methods used in your app
 */
interface Alaress_Date_Interface {
	public function getUnixTimestamp();
	public function getDatabase();

	/* TODO: find out where these aren't being implemented correctly
	 */
//	public function getDateOnly();
//	public function getAustralian();
	public function getAmerican();
}