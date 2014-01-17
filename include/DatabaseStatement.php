<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Unfortunately, PDO doesn't even come close to handling multiprocessing, so this wrapper prevents "went away"
 * exceptions from causing problems.
 *
 * Class DatabaseStatement
 */
class DatabaseStatement extends PDOStatement {

	/** @var Database */
	protected $conn;


	protected function __construct() {
		// yay singletons! -_-
		$this->conn = Database::getInstance();
	}

	public function execute(array $input_parameters = null) {
		try{
			return parent::execute($input_parameters);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::execute($input_parameters);
		}
	}

	public function fetch($fetch_style = null, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0) {
		try{
			return parent::fetch($fetch_style, $cursor_orientation, $cursor_offset);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::fetch($fetch_style, $cursor_orientation, $cursor_offset);

		}
	}

	public function bindParam($parameter, &$variable, $data_type = PDO::PARAM_STR, $length = null, $driver_options = null) {
		try{
			return parent::bindParam($parameter, $variable, $data_type, $length, $driver_options);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::bindParam($parameter, $variable, $data_type, $length, $driver_options);

		}
	}

	public function bindColumn($column, &$param, $type = null, $maxlen = null, $driverdata = null) {
		try{
			return parent::bindColumn($column, $param, $type, $maxlen, $driverdata);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::bindColumn($column, $param, $type, $maxlen, $driverdata);
		}
	}

	public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR) {
		try{
			return parent::bindValue($parameter, $value, $data_type);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::bindValue($parameter, $value, $data_type);
		}
	}

	public function rowCount() {
		try{
			return parent::rowCount();
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::rowCount();
		}
	}

	public function fetchColumn($column_number = 0) {
		try{
			return parent::fetchColumn($column_number);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::fetchColumn($column_number);
		}
	}

	public function fetchAll($fetch_style = null, $fetch_argument = null, array $ctor_args = array()) {
		try{
			return parent::fetchAll($fetch_style);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::fetchAll($fetch_style);
		}
	}

	public function fetchObject($class_name = "stdClass", array $ctor_args = null) {
		try{
			return parent::fetchObject($class_name, $ctor_args);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::fetchObject($class_name, $ctor_args);
		}
	}

	public function errorCode() {
		try{
			return parent::errorCode();
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::errorCode();
		}
	}

	public function errorInfo() {
		try{
			return parent::errorInfo();
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::errorInfo();
		}
	}

	public function setAttribute($attribute, $value) {
		try{
			return parent::setAttribute($attribute, $value);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::setAttribute($attribute, $value);
		}
	}

	public function getAttribute($attribute) {
		try{
			return parent::getAttribute($attribute);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::getAttribute($attribute);
		}
	}

	public function columnCount() {
		try{
			return parent::columnCount();
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::columnCount();
		}
	}

	public function getColumnMeta($column) {
		try{
			return parent::getColumnMeta($column);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::getColumnMeta($column);
		}
	}

	public function setFetchMode($mode) {
		try{
			return parent::setFetchMode($mode);
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::setFetchMode($mode);
		}
	}

	public function nextRowset() {
		try{
			return parent::nextRowset();
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::nextRowset();
		}
	}

	public function closeCursor() {
		try{
			return parent::closeCursor();
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::closeCursor();
		}
	}

	public function debugDumpParams() {
		try{
			return parent::debugDumpParams();
		}
		catch(PDOException $e) {
			$this->conn->reconnect($e);
			return parent::debugDumpParams();
		}
	}
} 