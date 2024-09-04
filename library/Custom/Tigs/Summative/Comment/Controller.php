<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\Comment;

use Alaress\Web;

class Controller extends Web\Controller\Base
{
//    /**
//     * Displays a form where a Staff member (eg the Dean) can give comments across all
//     * users in a selected student role
//     *
//     * @api   GET /modules/custom/tigs/summative/comment/role/{roleId}/term/{termSlug}
//     * @since 18.1.0
//     * @param int    $roleId
//     * @param string $termSlug
//     */
//    public function getDeanComments(int $roleId, string $termSlug): void
//    {
//        $this->response = $this->responseFromTemplate(
//            '@tigsSummativeComment/yearLevel.twig',
//            [
//                'roleId'   => $roleId,
//                'termSlug' => $termSlug,
//            ],
//            "TIGS Summative Reporting  - Dean Comments"
//        );
//    }


    /**
     * Displays a form where a Staff member can give comments across all
     * users in a selected folder
     *
     * @api   GET /modules/custom/tigs/summative/comment/folder/{folderId}/term/{termSlug}
     * @since 18.1.0
     * @param int $folderId
     * @param string $termSlug
     */
    public function getTeacherComments(int $folderId, string $termSlug): void
    {
        $this->response = $this->responseFromTemplate(
            '@tigsSummativeComment/folder.twig',
            [
                'folderId' => $folderId,
                'termSlug' => $termSlug,
            ],
            "TIGS Summative Reporting  - Teacher Comments"
        );
    }
}
