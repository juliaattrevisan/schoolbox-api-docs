<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace External;

/**
 * An offline connector
 *
 * Classes which implement this interface should not have any dependencies on other processes or systems.
 * They are offline.
 */
interface IOfflineConnector extends IConnector
{

}
