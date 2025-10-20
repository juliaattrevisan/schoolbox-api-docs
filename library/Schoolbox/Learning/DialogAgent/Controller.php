<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct() {
    }


    /**
     * Configure the dialog agent's settings
     *
     * @param DialogAgent $assessment
     * @return void
     * @throws \Twig\Error\Error
     */
    public function getUpdateForm(DialogAgent $assessment): void
    {
        $data = [];

        $this->response = $this->responseFromTemplate('@learningDialogAgent/update.twig', $data, 'Configure discussion');
    }


    /**
     * Update the configuration of a dialog agent
     *
     * @param DialogAgent $assessment
     * @return void
     */
    public function postUpdate(DialogAgent $assessment): void
    {

    }
}
