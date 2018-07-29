<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Page;

use AcceptanceTester;

/**
 * Homepage page object
 */
class Homepage
{
    public const URL = '/homepage/{id}';

    public const ELEMENT_ADD_COMPONENT_TAB = '#tabs-1';
    public const ELEMENT_ADD_COMPONENT_SECTION_INFORMATION = '#tabs-sub-1';
    public const ELEMENT_ADD_COMPONENT_SECTION_COLLABORATION = '#tabs-sub-2';
    public const ELEMENT_ADD_COMPONENT_SECTION_LEARNING = '#tabs-sub-3';
    public const ELEMENT_ADD_COMPONENT_SECTION_MULTIMEDIA = '#tabs-sub-4';
    public const ELEMENT_ADD_COMPONENT_SECTION_RESOURCES = '#tabs-sub-5';
    public const ELEMENT_ADD_COMPONENT_SECTION_FEEDS = '#tabs-sub-6';

    public const ELEMENT_EDIT_COLUMNS_TAB = '#tabs-2';

    public const LINK_MENU = 'a[data-dropdown="tab-control"]';

    public const ELEMENT_COMPONENT_LAYOUT = '#component-layout';
    public const ELEMENT_COMPONENT_LAYOUT_TOP = self::ELEMENT_COMPONENT_LAYOUT . ' div.column-top';
    public const ELEMENT_COMPONENT_LAYOUT_LEFT = self::ELEMENT_COMPONENT_LAYOUT . ' div.column-left';
    public const ELEMENT_COMPONENT_LAYOUT_RIGHT = self::ELEMENT_COMPONENT_LAYOUT . ' div.column-right';

    /**
     * @var AcceptanceTester
     */
    private $tester;

    /**
     * Constructor
     *
     * @param AcceptanceTester $i
     */
    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * Generates route for this page.
     *
     * @param int $id
     * @return string
     */
    public function generateRoute(int $id): string
    {
        return str_replace('{id}', (string)$id, self::URL);
    }

    /**
     * Adds the given component.
     *
     * @param string $componentSection The section it's in: one of the
     *                                 `self::ELEMENT_ADD_COMPONENT_SECTION_*` constants
     * @param string $componentName    The name of the component, as seen in its link
     */
    public function addComponent(string $componentSection, string $componentName): void
    {
        // Animations off
        $this->tester->disableAnimation();

        // Open the component tab
        $this->openTabPanel(self::ELEMENT_ADD_COMPONENT_TAB);

        // Which component section is it in?
        $this->openTabPanel($componentSection);

        // Click to add the component
        $this->tester->click($componentName, $componentSection);

        // Wait for it to load
        $this->tester->waitForAjaxDone();

        // Animations on again
        $this->tester->enableAnimation();
    }

    /**
     * @param string $layout   The layout in which the component lives: one of the
     *                         `self::ELEMENT_COMPONENT_LAYOUT_*` constants
     * @param int    $position The position of the component (1-indexed)
     * @return string a locator for the component
     */
    public function locateComponent(string $layout, int $position): string
    {
        // TODO somehow fix this so that it is able to actually use nth-child
        return sprintf(
            '%s div.component-container',
            $layout
//            ,
//            // Offset position by 1, due to the presence of a placeholder in first position
//            $position + 1
        );
    }

    /**
     * Clicks a component empty state link.
     *
     * @param string $layout
     * @param int    $position
     */
    public function clickComponentEmptyStateLink(string $layout, int $position): void
    {
        $this->tester->click([
            'css' => $this->locateComponent($layout, $position) . ' .empty-state a',
        ]);
    }

    /**
     * Open this tab panel, as identified by a selector.
     *
     * @param string $selector
     */
    private function openTabPanel(string $selector): void
    {
        $this->tester->click(['css' => sprintf('[href="%s"]', $selector)]);
    }
}
