<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

use Alaress\DebugBar\Instance as DebugBar;
use DebugBar\DataCollector\PDO as DebugPDO;

class Database implements Serializable {
    /**
     * An instance of database
     *
     * @var Database
     */
    static private $instance;

    /**
     * Database source name
     *
     * @var String
     */
    private $dsn;

    /**
     * Username for authenticated connection
     *
     * @var String
     */
    private $username;

    /**
     * Password for authenticated connection
     *
     * @var String
     */
    private $password;

    /**
     * An array of additional PDO connection options
     *
     * @var Array
     */
    private $options;

    /**
     * The database connection, and instance of PDO
     *
     * @var PDO
     */
    private $connection;

    /**
     * Returns the singleton instance of Database. If it doesn't exist it is instantiated.
     *
     * @static
     * @return Database
     */
    static public function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

	/**
	 * Explicitly dis- and re-connect the singleton database connection
	 */
	static public function reinitialise() {
		self::$instance->reconnect();
	}

	/**
	 * Establishes the database connection
	 *
	 * @param String $dsn
	 * @param String $username
	 * @param String $password
	 * @param Array $options
	 * @throws Schoolbox\External\Exception
	 * @return Bool
	 */
    public function connect($dsn, $username, $password, $options = array()) {
        if (empty($dsn) || !is_string($dsn)) {
            return false;
        }

        /* Set connection parameters */
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;

        /* Create PDO connection */
        try {
            $this->connection = new PDO($dsn, $username, $password, array(
                PDO::ATTR_PERSISTENT => empty($options['debug']),
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            if(isset($options['throw']) && $options['throw']) {
		        throw new \Schoolbox\External\Exception($exception->getMessage(), $exception->getCode(), $exception);
	        }
            $this->error($exception);
        }

        return true;
    }

	/**
	 * Re-establishes a lost database connection if the reason supplied by $e is acceptable.
	 *
	 * @throws PDOException
	 * @return bool
	 */
	public function reconnect() {
		// try to call some sort of PDO destructor
		$this->connection = null;
		unset($this->connection);
		return $this->connect($this->dsn, $this->username, $this->password, $this->options);
	}

    /**
     * Attempt to set the connection's timezone.
     *
     * @param string $timezone A valid timezone.
     * @return bool
     * @throws PDOException if the timezone can't be set
     */
    public function setTimezone($timezone)
    {
        return $this->connection
            ->prepare("SET time_zone = ?")
            ->execute([$timezone])
        ;
    }

    /**
     * Retrieves the query collector from this connection.
     *
     * Will fail if $this->options[debug] is off.
     *
     * @return \DebugBar\DataCollector\PDO\PDOCollector
     */
    public function getCollector() {
        $this->connection = new DebugPDO\TraceablePDO($this->connection);
        return new DebugPDO\PDOCollector($this->connection);
    }

    /**
     * Wrapper for PDO::exec
     * PDO::exec() executes an SQL statement in a single function call, returning the number
     * of rows affected by the statement.
     *
     * @param String $statement
     * @return Integer
     */
    public function exec($statement) {

        return $this->connection->exec($statement);

    }

    /**
     * Wrapper for PDO::prepare
     * PDO::prepare prepares a statement for execution and returns a PDOStatement object.
     *
     * @param String $statement
     * @param Array $driverOptions
     * @return PDOStatement
     */
    public function prepare($statement, $driverOptions = array()) {

        return $this->connection->prepare($statement, $driverOptions);

    }

    /**
     * Wrapper for PDO::query
     * PDO::query() executes an SQL statement in a single function call, returning the result set (if any)
     * returned by the statement as a PDOStatement object.
     *
     * @param String $statement
     * @param array  $args
     * @return PDOStatement
     */
    public function query($statement, $args = null) {
        if (empty($args)) {
            return $this->connection->query($statement);
        }
        $stmt = $this->prepare($statement);
        $stmt->execute($args);
        return $stmt;
    }

    /**
     * Wrapper for PDO::quote
     *
     * @param $string
     * @param int $parameter_type
     * @return string
     */
    public function quote($string, $parameter_type = PDO::PARAM_STR) {
        return $this->connection->quote($string, $parameter_type);
    }

    /**
     * Quotes column, table, etc. names so they may be safely used in queries.
     * For MySQL: ` in an identifier is replaced with ``, and the string is
     * wrapped with ` characters.
     *
     * @param  string $string
     * @return string
     */
    public function quoteIdentifier($string) {
        return '`'.str_replace('`', '``', $string).'`';
    }

    /**
     * Escapes _ and % characters within $string, so they don't have unintended
     * effects in LIKE queries.
     *
     * This doesn't quote $string for safe use in string-interpolated queries,
     * it only escapes LIKE chars.
     *
     * @param  string $string
     * @return string
     */
    public function quoteForLike($string) {
        return strtr(
            $string,
            array(
                '_' => '\\_',
                '%' => '\\%',
            )
        );
    }

    /**
     * Tries to create a unique parameter name
     *
     * @param string $param_name      The base name to attempt to add
     * @param array  $existing_params The existing list of parameters to add to
     *                                a query
     *
     * @return string The param name, or null if it couldn't be created after
     *                100 attempts.
     */
    public function createUniqueParameterName($param_name, $existing_params) {
        $new_name = $param_name;
        $count    = 0;
        while ($count < 100) {
            if (!array_key_exists($new_name, $existing_params)) {
                return $new_name;
            }
            $new_name = $param_name . '_' . $count;
            $count++;
        }
        return null;
    }

    /**
     * Wrapper for PDO::lastInsertId
     * Returns the ID of the last inserted row, or the last value from a sequence object, depending
     * on the underlying driver. For example, PDO_PGSQL() requires you to specify the name of a sequence object
     * for the name parameter.
     *
     * @param Null $name
     * @return String
     */
    public function lastInsertId($name = null) {

        return $this->connection->lastInsertId($name);

    }

    /**
     * Wrapper for PDO::beginTransaction
     * Initiates a transaction
     * @return bool true on success or false on failure.
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Wrapper for PDO::commit
     * Commits a transaction
     * @link http://php.net/manual/en/pdo.commit.php
     * @return bool true on success or false on failure.
     */
    public function commit() {
        return $this->connection->commit();

    }

    /**
     * Wrapper for PDO::rollBack
     * Rolls back a transaction
     * @link http://php.net/manual/en/pdo.rollback.php
     * @return bool true on success or false on failure.
     */
    public function rollBack() {
        return $this->connection->rollBack();
    }

    /**
     * Wrapper for PDO::inTransaction
     * Checks if inside a transaction
     * @link http://php.net/manual/en/pdo.intransaction.php
     * @return bool true if a transaction is currently active, and false if not.
     */
    public function inTransaction() {
        return $this->connection->inTransaction();
    }

    /**
     * Returns whether or not the database connection has been initialised.
     *
     * @return Bool
     */
    public function isConnected() {
        if (isset($this->connection) && !empty($this->connection)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Prints the PDOException error message and dies
     *
     * @param PDOException $exception
     * @return void
     */
    private function error(PDOException $exception) {
        die("Database Error: {$exception->getMessage()}");
    }

	/**
	 * String representation of object
	 *
	 * @return string
	 */
	public function serialize() {
		return null;
	}

	/**
	 * Constructs the object
	 *
	 * @param string $serialized
	 * @return void
	 */
	public function unserialize($serialized) {
		$this->connect(SQL_dsn, SQL_user, SQL_passwd, array('debug' => DebugBar::isEnabled()));
		$this->setTimezone(\Schoolbox\Config\ConfigMapper::getInstance()->get('date_timezone'));
	}
}
