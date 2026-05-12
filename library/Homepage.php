<?php

/* This script controls homepage layout functions.  It is used for setting up templates and customising homepage */

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource;

class Homepage
{
    /* Used to remove homepage components and reset template
     */
    const REMOVE_HOMEPAGE_TEMPLATE_ID = -1;
    const KEEP_EXISTING_HOMEPAGE      = 0;

    /**
     * @var int
     */
    private $cid;

    /**
     * Homepage config mapper
     */
    private Resource\HomepageConfigMapper $homepageConfigMapper;

    /**
     * Homepage template mapper
     */
    private Resource\HomepageTemplateMapper $homepageTemplateMapper;

    public function __construct($cid)
    {
        $this->cid                    = $cid;
        $this->homepageConfigMapper   = Resource\HomepageConfigMapper::getInstance();
        $this->homepageTemplateMapper = Resource\HomepageTemplateMapper::getInstance();
    }

    public function getTemplate()
    {
        try {
            return $this->homepageConfigMapper->findById($this->cid)->getTemplate();
        } catch (MapperException) {
            return null;
        }
    }

    /**
     * Gets the column widths for a particular homepage
     *
     * @return array|bool
     */
    public function getColumnWidths()
    {
        try {
            $homepageConfig = $this->homepageConfigMapper->findById($this->cid);
            return [
                'left_column_width'  => $homepageConfig->getLeftColumnWidth() ?? Resource\HomepageTemplate::DEFAULT_LEFT_COLUMN_WIDTH,
                'right_column_width' => $homepageConfig->getLeftColumnWidth() ?? Resource\HomepageTemplate::DEFAULT_RIGHT_COLUMN_WIDTH,
            ];
        } catch (MapperException) {
            return [
                'left_column_width'  => Resource\HomepageTemplate::DEFAULT_LEFT_COLUMN_WIDTH,
                'right_column_width' => Resource\HomepageTemplate::DEFAULT_RIGHT_COLUMN_WIDTH,
            ];
        }
    }

    /**
     * Update column widths
     *
     * @param float $left
     * @param float $right
     * @return void
     */
    public function updateColumnWidths($left, $right)
    {
        $homepageConfig = $this->homepageConfigMapper->findById($this->cid);
        $homepageConfig->setLeftColumnWidth($left);
        $homepageConfig->setRightColumnWidth($right);
        $this->homepageConfigMapper->save($homepageConfig);
    }

    /**
     * Updates left and right columns widths based on the proportion (out of 12)
     * the left column takes up.
     *
     * @param int $leftProportion min: 3, max: 9
     */
    public function updateGrid($leftProportion)
    {
        $leftProportion = min(9, max(3, $leftProportion));
        $leftPercent    = round($leftProportion * (100 / 12)) - .5;
        $this->updateColumnWidths($leftPercent, 99 - $leftPercent);
    }

    public function getColorList()
    {
        $homepageConfig = $this->homepageConfigMapper->findById($this->cid);
        $colourList = [
            'left_head_font'     => $homepageConfig->getLeftHeadFont(),
            'left_head_bg'       => $homepageConfig->getLeftHeadBg(),
            'left_font_primary'  => $homepageConfig->getLeftFont(),
            'left_bg_primary'    => $homepageConfig->getLeftBgPrimary(),
            'right_head_font'    => $homepageConfig->getRightHeadFont(),
            'right_head_bg'      => $homepageConfig->getRightHeadBg(),
            'right_font_primary' => $homepageConfig->getRightFont(),
            'right_bg_primary'   => $homepageConfig->getRightBgPrimary(),
            'top_head_font'      => $homepageConfig->getTopHeadFont(),
            'top_head_bg'        => $homepageConfig->getTopHeadBg(),
            'top_font_primary'   => $homepageConfig->getTopFont(),
            'top_bg_primary'     => $homepageConfig->getTopBgPrimary(),
        ];

        if (Config::get('advanced_ui_enabled')) {
            $defaultHeadingColours       = $this->getDefaultHeadingColours();
            $storedDefaultHeadingColours = $this->getStoredDefaultHeadingColours();
            foreach (['left', 'right', 'top'] as $position) {
                if ($this->hasHeadingColours($colourList, $position, $storedDefaultHeadingColours)) {
                    $colourList[$position . '_head_font'] = $defaultHeadingColours['head_font'];
                    $colourList[$position . '_head_bg']   = $defaultHeadingColours['head_bg'];
                }
            }
        }

        return $colourList;
    }

    /**
     * Gets the default heading colours for a homepage, which are either the skin component heading colours
     * (if advanced UI is enabled) or the homepage heading colours (if not).
     *
     * @return array
     */
    private function getDefaultHeadingColours(): array
    {
        if (Config::get('advanced_ui_enabled')) {
            return [
                'head_font' => Config::get('skin_colour_component_heading_text'),
                'head_bg'   => Config::get('skin_colour_component_heading_background'),
            ];
        }

        return $this->getStoredDefaultHeadingColours();
    }

    private function getStoredDefaultHeadingColours(): array
    {
        return [
            'head_font' => Config::get('homepage_colour_head_font'),
            'head_bg'   => Config::get('homepage_colour_head_bg'),
        ];
    }

    private function hasHeadingColours(array $colourList, string $position, array $headingColours): bool
    {
        $headFont = $colourList[$position . '_head_font'] ?? '';
        $headBg   = $colourList[$position . '_head_bg'] ?? '';

        return strcasecmp((string)$headFont, (string)$headingColours['head_font']) === 0
            && strcasecmp((string)$headBg, (string)$headingColours['head_bg']) === 0;
    }

    private function normaliseDefaultHeadingColoursForSave(array $listColor): array
    {
        if (!Config::get('advanced_ui_enabled')) {
            return $listColor;
        }

        $defaultHeadingColours       = $this->getDefaultHeadingColours();
        $storedDefaultHeadingColours = $this->getStoredDefaultHeadingColours();

        foreach (['left', 'right', 'top'] as $position) {
            if ($this->hasHeadingColours($listColor, $position, $defaultHeadingColours)) {
                $listColor[$position . '_head_font'] = $storedDefaultHeadingColours['head_font'];
                $listColor[$position . '_head_bg']   = $storedDefaultHeadingColours['head_bg'];
            }
        }

        return $listColor;
    }

    public function getDefaultColorList(): array
    {
        $colourSet             = [];
        $defaultHeadingColours = $this->getDefaultHeadingColours();

        foreach (['top', 'left', 'right'] as $position) {
            $colourSet = array_merge($colourSet, [
                $position . '_head_font'    => $defaultHeadingColours['head_font'],
                $position . '_head_bg'      => $defaultHeadingColours['head_bg'],
                $position . '_font_primary' => Config::get('homepage_colour_font'),
                $position . '_bg_primary'   => Config::get('homepage_colour_bg_primary'),
            ]);
        }

        return $colourSet;
    }

    /**
     * Removes componentry from homepage and resets to the template that was used to create it.
     * Also soft-deletes homepage content where applicable
     *
     * @return void
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function emptyHomepage(): void
    {
        /** @var \Schoolbox\IHomepageCleanable[] $homepageCleanables */
        $homepageCleanables = [
            new Assess_Main(),
            new Chat(),
            new Poll(),
            new Survey_Main(),
            new Wiki(),

            Schoolbox\Calendar\EventMapper::getInstance(),
            Resource\File\Mapper::getInstance(),
            Schoolbox\Dropbox\Mapper::getInstance(),
            \SocialStream\PostMapper::getInstance(),
            Schoolbox\Comms\News\Audience\Simple\Mapper::getInstance(),
            Schoolbox\Comms\News\Audience\Complex\Mapper::getInstance(),
            Schoolbox\Blog\Homepage\Post\Mapper::getInstance(),
            Schoolbox\Forum\ThreadMapper::getInstance(),
            Schoolbox\Resource\Textbox\Mapper::getInstance(),
        ];

        foreach ($homepageCleanables as $homepageCleanable) {
            $homepageCleanable->homepageClean($this->cid);
        }

        // reset the homepage if one exists
        try {
            $category = Resource\Folder\Mapper::getInstance()->findById($this->cid);

            /* Only reset folder's homepage templates if one was selected and in existence
             * All this does is reset the components on the homepage to whatever is specified
             * in the template itself. "No Homepage" folders have nothing to reset.
             */
            if ($category->getHomepage() && $category->getHomepage()->getTemplate()) {
                $this->resetHomepageToTemplate($category->getHomepage()->getTemplate()->getId());
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop - no template exists for this folder, no components to reset
        }
    }

    /**
     * Resets a homepage to a template, or destroys the homepage if it is to be removed.
     * Returns the ID of the template used, or 0 if none.
     *
     * @param int $templateID
     * @return int
     */
    public function resetHomepageToTemplate($templateID)
    {
        if ($templateID != Homepage::KEEP_EXISTING_HOMEPAGE) {
            // if 'remove homepage',
            // delete config and components, then set the templateID to zero
            if ($templateID == Homepage::REMOVE_HOMEPAGE_TEMPLATE_ID) {
                $templateID = $this->removeAllComponentsFromHomepage();
            }

            // now continue as normal
            if ($templateID > 0) {
                $this->createHomepageFromTemplate($templateID);
            } else {
                // we don't want an invalid template ID going into the database
                $templateID = 0;
            }

            // deal with the template
            $homepageConfig = $this->homepageConfigMapper->findById($this->cid);
            $homepageConfig->setTemplate(
                $templateID
                    ? $this->homepageTemplateMapper->findById($templateID)
                    : null
            );
            $this->homepageConfigMapper->save($homepageConfig);
        }

        return $templateID;
    }


    /**
     * This simply copies a bunch of rows, changing 'homepage_template' to 'homepage'...quite simple, really.
     *
     * @param int $templateID
     */
    public function createHomepageFromTemplate($templateID)
    {
        // locate all the component instances associated with this homepage (if they exist), and clear them out
        $cim        = Component_InstanceMapper::getInstance();
        $collection = $cim->findByColumnList(['module' => 'homepage', 'identifier' => $this->cid]);
        $cim->delete($collection);

        $homepageTemplate = $templateID
            ? $this->homepageTemplateMapper->findById($templateID)
            : null;
        $homepageConfig = $this->homepageConfigMapper->createModel($this->cid);
        $homepageConfig->setTemplate($homepageTemplate);
        // TODO make width a const
        $homepageConfig->setLeftColumnWidth($homepageTemplate?->getLeftColumnWidth() ?? 70);
        $homepageConfig->setRightColumnWidth($homepageTemplate?->getRightColumnWidth() ?? 30);
        $defaultHeadingColours = $this->getStoredDefaultHeadingColours();
        $homepageConfig->setTopHeadFont($defaultHeadingColours['head_font']);
        $homepageConfig->setTopHeadBg($defaultHeadingColours['head_bg']);
        $homepageConfig->setTopFont(Config::get('homepage_colour_font'));
        $homepageConfig->setTopBgPrimary(Config::get('homepage_colour_bg_primary'));
        $homepageConfig->setLeftHeadFont($defaultHeadingColours['head_font']);
        $homepageConfig->setLeftHeadBg($defaultHeadingColours['head_bg']);
        $homepageConfig->setLeftFont(Config::get('homepage_colour_font'));
        $homepageConfig->setLeftBgPrimary(Config::get('homepage_colour_bg_primary'));
        $homepageConfig->setRightHeadFont($defaultHeadingColours['head_font']);
        $homepageConfig->setRightHeadBg($defaultHeadingColours['head_bg']);
        $homepageConfig->setRightFont(Config::get('homepage_colour_font'));
        $homepageConfig->setRightBgPrimary(Config::get('homepage_colour_bg_primary'));
        $this->homepageConfigMapper->save($homepageConfig);

        // retrieve, rearrange and reinsert the componentry
        $cim       = Component_InstanceMapper::getInstance();
        $ccm       = Component_ConfigMapper::getInstance();
        $instances = $cim->findByContext('homepage_template', $templateID);

        if (count($instances)) {
            foreach ($instances as $inst) {
                /* @var $inst Component_Instance */
                // preserve the config
                $configs = $inst->getConfig();

                $inst
                    ->setId(null)
                    ->setConfig(new \Schoolbox\Collection())
                    ->setModule('homepage')
                    ->setIdentifier($this->cid);

                $ids = $cim->save($inst);
                $id  = $ids[0];

                if (count($configs)) {
                    foreach ($configs as $c) {
                        /* @var $c Component_Config */
                        if ($c->getId()) {
                            $conf = new Component_Config();
                            $conf
                                ->setInstanceId($id)
                                ->setValue($c->getValue())
                                ->setConfigDefinitionId($c->getId());
                            $ccm->save($conf);
                        }
                    }
                }
            }
        }
    }

    /**
     * Delete all componentry from a homepage
     *
     * @return int
     */
    private function removeAllComponentsFromHomepage()
    {
        // locate all the component instances associated with this homepage, and clear them out
        $cim        = Component_InstanceMapper::getInstance();
        $collection = $cim->findByColumnList(['module' => 'homepage', 'identifier' => $this->cid]);
        $cim->delete($collection);

        return 0;
    }

    public function saveDisplayProperties($listColor)
    {
        $listColor = $this->normaliseDefaultHeadingColoursForSave($listColor);
        $homepageConfig = $this->homepageConfigMapper->findById($this->cid);
        $homepageConfig->setTopHeadFont($listColor['top_head_font']);
        $homepageConfig->setTopHeadBg($listColor['top_head_bg']);
        $homepageConfig->setTopFont($listColor['top_font_primary']);
        $homepageConfig->setTopBgPrimary($listColor['top_bg_primary']);
        $homepageConfig->setLeftHeadFont($listColor['left_head_font']);
        $homepageConfig->setLeftHeadBg($listColor['left_head_bg']);
        $homepageConfig->setLeftFont($listColor['left_font_primary']);
        $homepageConfig->setLeftBgPrimary($listColor['left_bg_primary']);
        $homepageConfig->setRightHeadFont($listColor['right_head_font']);
        $homepageConfig->setRightHeadBg($listColor['right_head_bg']);
        $homepageConfig->setRightFont($listColor['right_font_primary']);
        $homepageConfig->setRightBgPrimary($listColor['right_bg_primary']);
        $this->homepageConfigMapper->save($homepageConfig);
    }
}
