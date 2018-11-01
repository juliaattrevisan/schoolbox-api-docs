<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Comment;

use Alaress\Web;
use Aura\Di\Exception\ServiceNotFound;

class Controller extends Web\Controller\Base
{

    /**
     * Displays a form where a Staff member (eg the Dean) can give comments across all
     * users in a selected student role
     *
     * @api   GET /modules/custom/tigs/summative/comment/role/{roleId}/term/{termSlug}
     * @level 18.1.0
     * @param int    $roleId
     * @param string $termSlug
     * @throws ServiceNotFound
     */
    public function getDeanComments(int $roleId, string $termSlug): void
    {
        $this
            ->getResponse()->content
            ->set($this->buildHtmlOutput('@tigsSummativeComment/yearLevel.twig', [
                'roleId'   => $roleId,
                'termSlug' => $termSlug,
            ])
            ->setTitle("TIGS Summative Reporting  - Dean Comments"));
    }


    /**
     * Displays a form where a Staff member can give comments across all
     * users in a selected folder
     *
     * @api   GET /modules/custom/tigs/summative/comment/folder/{folderId}/term/{termSlug}
     * @level 18.1.0
     * @param int $folderId
     * @param string $termSlug
     * @throws ServiceNotFound
     */
    public function getTeacherComments(int $folderId, string $termSlug): void
    {
        $this
            ->getResponse()->content
            ->set($this->buildHtmlOutput('@tigsSummativeComment/folder.twig', [
                'folderId' => $folderId,
                'termSlug' => $termSlug,
            ])
            ->setTitle("TIGS Summative Reporting  - Teacher Comments"));
    }
}
