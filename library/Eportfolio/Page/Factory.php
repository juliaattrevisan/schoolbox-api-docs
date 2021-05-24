<?php

/**
 * Eportfolio_Page_Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Eportfolio_Page_Factory
{

    /**
     * Decide which kind of page to manufacture
     *
     * @param SitePage   $page
     * @param int        $userId
     * @param int|string $pageId
     * @param int        $permissionValue
     * @return Eportfolio_Page_Abstract|null
     */
    public function produce($page, $userId, $pageId, $permissionValue)
    {
        if (is_numeric($pageId)) {
            return new Eportfolio_Page_Instance($page, $userId, $pageId, $permissionValue);
        } else if ($pageId == 'showcase') {
            return new Eportfolio_Page_Showcase($page, $userId, $pageId, $permissionValue);
        } else if ($pageId == 'create') {
            return new Eportfolio_Page_Create($page, $userId, $pageId, $permissionValue);
        } else if ($pageId == 'profile') {
            return new Eportfolio_Page_Profile($page, $userId, $pageId, $permissionValue);
        }

        return null;
    }
}
