<?php
/**
 * Ping a PCSchools database for creating an external database class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Pcschools_Ping implements External_PingInterface
{
    use External_MssqlPingTrait;
}
