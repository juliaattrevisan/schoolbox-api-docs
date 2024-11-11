<?php

/**
 * ValidButton
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Profile\Button;

use Schoolbox\Profile\Button\Model;

class ValidButton
{
    /** Check whether you have all the pieces
     *   - Must have a title
     *   - Must have a url
     *
     * @param string $type
     * @param array $post
     * @return bool
     */
    public function isSatisfiedBy(string $type, array $post): bool
    {
        $hasText = \strlen(trim($post['text']));

        /* Actual url existence only applies to Custom Buttons
         * Let System Buttons in freely
         */
        if (Model::BUTTON_CUSTOM === $type) {
            $hasUrl = \strlen(trim($post['url'] ?? ''));
        } else {
            $hasUrl = true;
        }

        return $hasText && $hasUrl;
    }
}
