<?php

/**
 * Created by JetBrains PhpStorm.
 * User: Steve
 * Date: 24/08/11
 * Time: 12:41 PM
 * To change this template use File | Settings | File Templates.
 */

require_once __DIR__ . '/../../conf/core/require.php';

// sorry.
Schoolbox\Mapper::di(new Aura\Di\Container(new Aura\Di\Factory()));
