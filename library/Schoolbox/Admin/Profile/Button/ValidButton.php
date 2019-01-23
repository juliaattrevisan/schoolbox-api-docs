<?php
/**
 * ValidButton
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Profile\Button;

class ValidButton
{

    /** Check whether you have all the pieces
     *   - Must have a title
     *   - Must have a url
     *   - Must have chosen a campus and role for Availability and also for Visibility

     * @param array $post
     * @return bool
     */
    public function isSatisfiedBy(array $post): bool
    {
        $hasTitle = \strlen(trim($post['title']));
        $hasUrl = \strlen(trim($post['url']));
        $hasCampusAvailability = \is_array($post['campusAvailability']) && \count($post['campusAvailability']);
        $hasRoleAvailability = \is_array($post['roleAvailability']) && \count($post['roleAvailability']);
        $hasCampusVisibility = \is_array($post['campusVisibility']) && \count($post['campusVisibility']);
        $hasRoleVisibility = \is_array($post['roleVisibility']) && \count($post['roleVisibility']);

        return $hasTitle && $hasUrl && $hasCampusAvailability && $hasRoleAvailability && $hasCampusVisibility && $hasRoleVisibility;
    }
}