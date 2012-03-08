<?php
/**
 * Created by Steve Chan.
 *
 * User: Steve
 * Date: 19/07/11
 * Time: 6:17 PM
 *
 * TwitterFilterInterface
 *
 * Interface to which all TwitterFilters must adhere
 */
interface Alaress_Twitter_Filter_Interface {
	public function filterText($text);
	public function filterDate($date);
}
