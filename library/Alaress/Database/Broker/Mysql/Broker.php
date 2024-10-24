<?php

/**
 * MySQL Database connection broker
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Broker\Mysql;

use Alaress\Database\Broker\Base;
use Alaress\Database\Iterator\Mysql\Iterator;
use Alaress\Database\Statement\FunctionCall\Mysql\FunctionCall;
use Alaress\Database\Statement\StoredProcedure\Mysql\StoredProcedure;
use PDO;
use PDOException;

class Broker extends Base
{
    /**
     * InnoDB full-text search stopword table name.
     * @see https://mariadb.com/kb/en/innodb-system-variables/#innodb_ft_user_stopword_table
     */
    const INNODB_FT_STOPWORD_TABLE_NAME = 'innodb_ft_stopword';

    // These return MySQL specific statement objects

    /**
     * @inheritdoc
     */
    public static function isAvailable()
    {
        return in_array('mysql', PDO::getAvailableDrivers(), true);
    }

    /**
     * @inheritdoc
     */
    public function prepareFunctionCall($function)
    {
        return new FunctionCall($this, $function);
    }

    /**
     * @inheritdoc
     */
    public function prepareStoredProcedure($procedure)
    {
        return new StoredProcedure($this, $procedure);
    }

    /**
     * @inheritdoc
     */
    public function getIterator($statement, $pdoStatement)
    {
        return new Iterator($this, $statement, $pdoStatement);
    }

    /**
     * @inheritdoc
     */
    public function getDriver(): string
    {
        return 'mysql';
    }

    /**
     * @inheritdoc
     */
    public function getDsn()
    {
        $portString = '';

        if (!empty($this->connector->getPort())) {
            $portString = "port={$this->connector->getPort()};";
        }

        return "{$this->getDriver()}:host={$this->connector->getHostname()};{$portString}dbname={$this->connector->getDatabase()}";
    }

    /**
     * @inheritdoc
     */
    public function connect($options = [])
    {
        // When connecting: use the login timeout
        $this->connectionOptions = [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            PDO::ATTR_PERSISTENT         => false,
            PDO::ATTR_TIMEOUT            => self::LOGIN_TIMEOUT,
            PDO::MYSQL_ATTR_LOCAL_INFILE => true,
        ];

        // After connecting: use the query timeout
        $this->databaseAttributes = [
            PDO::ATTR_TIMEOUT => self::QUERY_TIMEOUT,
        ];

        $success = parent::connect($options);

        if ($success) {
            $this->setInnodbFullTextStopwordsTable();
            return true;
        }

        return false;
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
        return $this->pdo
            ->prepare("SET time_zone = ?")
            ->execute([$timezone])
            ;
    }

    /**
     * Set up a custom stopwords table for full-text searches.
     * @return void
     */
    private function setInnodbFullTextStopwordsTable(): void
    {
        try {
            $this->pdo->prepare('SET SESSION innodb_ft_user_stopword_table = ?')
                ->execute([$this->getDatabase() . '/' . self::INNODB_FT_STOPWORD_TABLE_NAME]);
        } catch (PDOException) {
            // Can't configure the stopwords table?
            // Let's not bring down the whole application.  We'll just live with the default stopwords table.
            // Carry on.
        }
    }
}
