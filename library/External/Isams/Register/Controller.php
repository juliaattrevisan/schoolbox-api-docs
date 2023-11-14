<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Isams\Register;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\User;

/**
 * Controller for the iSams register.
 */
class Controller extends BaseController
{
    /**
     * @api GET /modules/isams/register/student/{studentId}
     * @since 23.1
     *
     * @param User $student
     * @return void
     */
    public function getStudent(User $student): void
    {
        $this->response = $this->responseFromTemplate(
            '@isamsRegister/student.twig',
            ['student' => $student],
        );
    }
}
