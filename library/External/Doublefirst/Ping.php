<?php

/**
 * DoubleFirst Ping Object
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 *         ,--.--._
 * ------" _, \___)
 *       2 / _/____)
 *       x \//(____)
 * ------\     (__)
 *        `-----"
 */
class External_Doublefirst_Ping implements External_PingInterface
{
    use External_MssqlPingTrait;
}
