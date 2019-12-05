<?php

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Redirect;

/**
 * Really basic component renderer, copied out of /homepage/configForm.php
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Component_Renderer
{
    /**
     * Renders all components for an admin page.
     *
     * @param SitePage                                  $page
     * @param Collection|Component_ControllerAbstract[] $componentCollection
     */
    public function renderAllAdminComponents(SitePage $page, $componentCollection)
    {
        $page->setFile(TEMPLATE_content, 'adminv2/component/layout.html');

        $page->setBlock(TEMPLATE_content, 'position_top', 'position_top_block');
        $page->setBlock(TEMPLATE_content, 'position_left', 'position_left_block');
        $page->setBlock(TEMPLATE_content, 'position_right', 'position_right_block');

        $occupiedPositionList = [];

        foreach ($componentCollection as $component) {
            $instance_no  = $component->getInstance()->getId();
            $position_no  = $component->getInstance()->getPosition();
            $position     = "position_{$position_no}";
            $definitionId = $component->getInstance()->getDefinition()->getId();

            if (!$page->template->exists("{$position}_block")) {
                $page->setFile($position, 'adminv2/component/placeholder.html');
            }

            $component->setup();

            $page->setVar([
                'ID'                        => $instance_no,
                'COMPONENT_INSTANCE_ID'     => $instance_no,
                'COMPONENT_DEFINITION_ID'   => $definitionId,
                'COMPONENT_HANDLE'          => $instance_no,
                'component.instance.id'     => $instance_no,
                'component.definition.id'   => $definitionId,
                'component.definition.name' => $component->getInstance()->getDefinition()->getName(),
            ]);

            $component->setConfigurationTemplate('component/config/_templatewrapper.html');
            $component->parseConfigForTemplate();

            $page->parse("{$position}_block", $position, true);

            if (!in_array($position_no, $occupiedPositionList)) {
                $occupiedPositionList[] = $position_no;
            }
        }

        $emptyPositionList = array_diff(['top', 'left', 'right'], $occupiedPositionList);

        foreach ($emptyPositionList as $emptyPosition) {
            $page->setVar("position_{$emptyPosition}_block", '');
        }
    }

    /**
     * Renders a single component for an admin page.
     *
     * @param SitePage                     $page
     * @param Component_ControllerAbstract $component
     */
    public function renderAdminComponent(SitePage $page, Component_ControllerAbstract $component)
    {
        $page->setFile(TEMPLATE_content, 'adminv2/component/placeholder.html');

        $component->setup();

        $instance_no  = $component->getInstance()->getId();
        $definitionId = $component->getInstance()->getDefinition()->getId();
        $page->setVar([
            'ID'                        => $instance_no,
            'COMPONENT_INSTANCE_ID'     => $instance_no,
            'COMPONENT_DEFINITION_ID'   => $definitionId,
            'COMPONENT_HANDLE'          => $instance_no,
            'component.instance.id'     => $instance_no,
            'component.definition.id'   => $definitionId,
            'component.definition.name' => $component->getInstance()->getDefinition()->getName(),
        ]);

        $component->setConfigurationTemplate('component/config/_templatewrapper.html');
        $component->parseConfigForTemplate();
    }

    /**
     * Renders a template into the Sitepage within $component.
     *
     * @param \Schoolbox\Resource\Folder\Model      $folder
     * @param Component_Homepage_ControllerAbstract $component
     */
    public function renderHomepageComponent(
        Schoolbox\Resource\Folder\Model $folder,
        Component_Homepage_ControllerAbstract $component
    ) {
        $templateTarget = "component_position_{$component->getInstance()->getPosition()}";
        $templateHandle = 'component_' . $component->getInstance()->getId();

        $component->setup($folder);

        if ($component->execute() !== false) {
            $component->parseConfig();
            if ($component->renderTemplate($templateHandle) !== false) {
                $component->parse($templateTarget, $templateHandle, true);
            }
        }

        /* Accumulate folder codes
         */
        $temp = [];

        foreach ($folder->getCodes() as $currentCode) {
            $temp[] = $currentCode->getCode();
        }

        $folderCodes = implode(', ', $temp);

        $component->getPageTemplate()->setVar('ID', (string)$folder->getId());
        $component->getPageTemplate()->setVar('CODE', urlencode(urlencode($folderCodes)));
        $component->getPageTemplate()->setVar('HEADING', Scrub::text($folder->getName()));
        $component->getPageTemplate()->setVar('SITE_LOCATION', Config::get('site_location'));
        $component->getPageTemplate()->setVar('RETURN_URL_NAME', Redirect::RETURN_URL);
        $component->getPageTemplate()->setVar('RETURN_URL', Redirect::encode('/homepage/' . $_POST["cid"]));
        $component->getPageTemplate()->pParse($templateTarget, $templateHandle);
    }

    /**
     * Renders an ePortfolio template into the Sitepage within $component.
     *
     * @param Eportfolio_Page_Instance                $eportfolioPage
     * @param Component_Eportfolio_ControllerAbstract $component
     */
    public function renderEportfolioComponent(
        Eportfolio_Page_Instance $eportfolioPage,
        Component_Eportfolio_ControllerAbstract $component
    ) {
        $templateTarget = "component_position_{$component->getInstance()->getPosition()}";
        $templateHandle = 'component_' . $component->getInstance()->getId();

        $component->setup($eportfolioPage);
        if ($component->execute() !== false) {
            $component->parseConfig();
            if ($component->renderTemplate($templateHandle) !== false) {
                $component->setVar('COMPONENT_NAME', $component->getConfig('componentname'));
                $component->parse($templateTarget, $templateHandle, true);

                // These aren't normally parsed so have to do it explicitly

                $component->getPageTemplate()->setVar([
                    'PAGE_ID'                 => $eportfolioPage->getPageId(),
                    'EPORTFOLIO_PAGE_USER_ID' => $eportfolioPage->getUserId(),
                ]);

                $component->getPageTemplate()->pParse($templateTarget, $templateHandle);
            }
        }
    }
}
