<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * HumanEdge implementation of ping for responsiveness.
 */
class External_Humanedge_Ping implements External_PingInterface
{
    /**
     * @inheritdoc
     */
    public function isResponsive()
    {
        if (function_exists('mssql_connect')) {
            return @mssql_connect(
                Cfg::get('external_db_host'),
                Cfg::get('external_db_user'),
                Cfg::get('external_db_passwd')
            ) !== false;
        }
        return false;
    }
}
