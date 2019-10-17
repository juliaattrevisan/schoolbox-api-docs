<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\TimetableEnrolment;

use Schoolbox\Admin\Import\UserAccount;

/**
 * Class Controller
 *
 * @package Schoolbox\Admin\Import\TimetableEnrolment
 */
class Controller extends UserAccount\Controller
{

    /**
     * @var string
     */
    protected $basename = 'adminImportTimetableEnrolment';
}
