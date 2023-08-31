<?php

/**
 * Created by PhpStorm.
 * User: tomoko.tanaka
 * Date: 27/06/18
 * Time: 11:44 AM
 */

declare(strict_types=1);

namespace Tests\Support\Page;

use Codeception\Util\Locator;
use Tests\Support\AcceptanceTester;

class Common
{
    /**
     * @var AcceptanceTester
     */
    protected $tester;

    /**
     * Constructor
     *
     * @param AcceptanceTester $i
     */
    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    //sales data
    public const USER_JACKCOLES = 'Mr Jack Coles';
    public const USER_ID_JACKCOLES = 22;
    public const USER_LOUISASMITH = 'Louisa Smith';
    public const USER_ID_LOUISASMITH = 55;
    public const USER_JAMALALI = 'Jamal Ali';
    public const USER_ID_JAMALALI = 60;

    public const CLASS_9AENG = '9A English';
    public const CLASS_ID_9AENG = 83;
    public const CLASS_ID_DEMO2 = 106;

    public const SELECTOR_JELLY_TOT = '[data-test=wc-actions-dropdown-header-options]';
    public const SELECTOR_JELLY_TOT_BUTTON = '[data-test=wc-actions-dropdown-header-options-button]';

    /**
     * Content element selector. This is where most of Schoolbox's dynamic
     * content goes.
     */
    public const ELEMENT_CONTENT = '#content';

    /**
     * Footer selector. This is where the copyright, policy, version number and
     * support link go.
     */
    public const ELEMENT_FOOTER = '#footer';

    /**
     * Top menu selector. This is where the configurable top menu items go.
     */
    public const ELEMENT_TOP_MENU = '#top-menu';

    /**
     * Search icon selector
     */
    public const SEARCH_ICON = 'button.icon-search.no-text';


    /**
     * Search field selector; used as a text input for site searches.
     */
    public const ELEMENT_SEARCH = '#search-field';

    /**
     * Side menu selector; this is where the configurable side menu items go,
     * as well as the current user's groups, classes and links.
     *
     * (for some reason, this has no ID?)
     */
    public const ELEMENT_SIDEBAR = '.left-off-canvas-menu';

    /**
     * Notification center selector; this is where new notifications go.
     */
    public const ELEMENT_NOTIFICATIONS = '#msgs';

    /**
     * Date format as used in form inputs.
     *
     * This should match `config.date_local`; you can set it if you want.
     */
    public const FORMAT_INPUT_DATE = 'd/m/Y';

    /**
     * Time format as used in form inputs.
     *
     * This should match `config.date_time`; you can set it if you want.
     */
    public const FORMAT_INPUT_TIME = 'g:ia';

    /**
     * Date+time format as used in form inputs.
     */
    public const FORMAT_INPUT_DATETIME = self::FORMAT_INPUT_DATE . ' ' . self::FORMAT_INPUT_TIME;


    public static function toDatepickerFormat(\Datetime $datetime)
    {
        return $datetime->format(self::FORMAT_INPUT_DATETIME);
    }

    public static function gentext(int $length)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz.012345678!@#$%*&^()-+';

        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            if ($length > 8 && rand(1, 7) === 5) {
                $randomString .= ' ';
                $i++;
            }
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    public static function surnameFirst($fullname)
    {
        $name = str_replace(['Mr ', 'Mrs '], '', $fullname);
        $parts = explode(' ', $name);
        return $parts[1] . ', ' . $parts[0];
    }

    /**
     * search by typing a text in the search bar
     * @param string $text
     * @return $this
     */
    public function search(string $text): self
    {
        $this->tester->click(self::SEARCH_ICON);
        $this->tester->fillField(self::ELEMENT_SEARCH, $text);
        $this->tester->click(self::SEARCH_ICON);
        return $this;
    }

    /**
     * click a section in the search results:
     * all, file, folder, textbox
     */
    public function clickSearchResultsSection(string $tabOption): void
    {
        $this->tester->click('a[data-section="' . $tabOption . '"]');
    }

    /**
     * clicks the submit button by locating the last element in the apge
     */
    public function clickSubmitButton(): void
    {
        $this->tester->click(Locator::lastElement('a.button.submit'));
    }
}
