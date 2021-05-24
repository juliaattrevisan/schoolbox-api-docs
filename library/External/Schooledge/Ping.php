<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/**
 * Schooledge implementation of ping for responsiveness.
 */
class External_Schooledge_Ping implements External_PingInterface
{
    use External_MssqlPingTrait;
}
