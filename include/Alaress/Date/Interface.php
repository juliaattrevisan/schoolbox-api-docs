<?php
/**
 * Methods used in your app
 */
interface Alaress_Date_Interface {
	public function getUnixTimestamp();
	public function getDatabase();
	public function getDateOnly();
	public function getAustralian();
	public function getAmerican();
}