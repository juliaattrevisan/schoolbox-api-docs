<?php
/**
 * Really basic component renderer, copied out of /homepage/configForm.php
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015 Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
class Component_Renderer
{
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
        $templateHandle = TEMPLATE_content;

        $component->setup($folder, $component->getInstance()->getConfigArray());

        if ($component->execute() !== false) {
            $component->parseConfig();
            if ($component->renderTemplate($templateHandle) !== false) {
                $component->parse($templateTarget, $templateHandle, true);
            }
        }

        /* Accumulate folder codes
         */
        $temp = array();

        foreach ($folder->getCodes() as $currentCode) {
            $temp[] = $currentCode->getCode();
        }

        $folderCodes = implode(', ', $temp);

        $component->getPageTemplate()->setVar('CODE', urlencode(urlencode($folderCodes)));
        $component->getPageTemplate()->setVar('HEADING', Scrub::text($folder->getName()));
        $component->getPageTemplate()->setVar('SITE_LOCATION', Cfg::get('site_location'));
        $component->getPageTemplate()->pparse($templateTarget, $templateHandle);
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
        $templateHandle = TEMPLATE_content;

        $component->setup($eportfolioPage, $component->getInstance()->getConfigArray());
        if ($component->execute() !== false) {
            $component->parseConfig();
            if ($component->renderTemplate($templateHandle) !== false) {
                $component->setVar('COMPONENT_NAME', $component->getConfig('componentname'));
                $component->parse($templateTarget, $templateHandle, true);
            }
        }

        /* These aren't normally parsed so have to do it explicitly
         */
        $component->getPageTemplate()->setVar(array(
            'USER_ID' => $eportfolioPage->getUserId(),
            'PAGE_ID' => $eportfolioPage->getPageId(),
        ));
        $component->getPageTemplate()->pparse($templateTarget, $templateHandle);
    }
}
