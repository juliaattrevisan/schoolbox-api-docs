<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Newic;

use Alaress\Web\Controller\Base;

class Controller extends Base
{

    /**
     * Redirect to the canvas system from our own
     */
    public function getRedirectToCanvas(string $code): void
    {
        die (sprintf(
            'redirected to canvas/course/%s',
            $code
        ));
    }
}
