<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Filter\AbstractDefinition;

abstract class Definition extends AbstractDefinition
{
    const KEY_CLASSES = 'classes';
    const KEY_GROUPS = 'groups';
}
