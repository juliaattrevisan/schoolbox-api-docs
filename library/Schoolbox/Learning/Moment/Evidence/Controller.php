<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\Web\Controller\Base;
use ICollection;

/**
 * Controller for learning moments.
 */
class Controller extends Base
{
    /**
     * @param ICollection $users
     */
    public function getCreateForm(ICollection $users): void
    {
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningMomentEvidence/form.twig',
                [
                    'users' => $users,
                ]
            )
        );
    }
}
