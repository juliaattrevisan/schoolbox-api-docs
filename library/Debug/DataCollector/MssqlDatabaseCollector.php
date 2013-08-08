<?php
/**
 * Collector for calls to a MSSQL database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Debug\DataCollector;

class MssqlDatabaseCollector extends DeprecatedDatabaseCollector {

    protected $widget_name = 'externalDatabase';

    /**
     * {@inheritdoc}
     */
    protected function getResult($query) {
        return mssql_query($query, $this->connection);
    }

    /**
     * {@inheritdoc}
     */
    protected function getRowCount($result) {
        if ($result === true) {
            return mssql_rows_affected($this->connection);
        }
        elseif (is_resource($result)) {
            return mssql_num_rows($result);
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode() {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage() {
        return mssql_get_last_message();
    }
}
