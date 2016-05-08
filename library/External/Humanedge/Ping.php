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
    use External_MssqlPingTrait;
}
