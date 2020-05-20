<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Feedback;

use Schoolbox\Learning\Assessment\IAssessable;

class Visibility
{
    /**
     * Available visibilities for assessments
     * @return array
     */
    public static function getAvailableVisibilities(): array
    {
        $availableVisibilities = [
            IAssessable::MARK_VISIBILITY_PRIVATE,
        ];
        switch (\Config::get('submission_visibility_max')) {
            case IAssessable::MARK_VISIBILITY_PRIVATE:
                break;

            case IAssessable::MARK_VISIBILITY_STUDENT:
                $availableVisibilities[] = IAssessable::MARK_VISIBILITY_STUDENT;
                break;

            case IAssessable::MARK_VISIBILITY_PARENT_STUDENT:
            default:
                $availableVisibilities[] = IAssessable::MARK_VISIBILITY_STUDENT;
                $availableVisibilities[] = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
                break;
        }
        return $availableVisibilities;
    }
}
