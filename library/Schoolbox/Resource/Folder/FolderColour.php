<?php

/**
 * Folder colour helper
 * If there are folders without colours, this will help add unique colours
 */

namespace Schoolbox\Resource\Folder;

use Alaress\Color\Color;
use Alaress\Color\ColorFactory;
use Schoolbox\GenericCollection;

class FolderColour
{
    /**
     * Timetable Colour Mapper
     * @var \Timetable_ColourMapper
     */
    protected $timetableColourMapper;

    /**
     * list of all available colours (coming from the timetable colour list)
     * This will always have at least some data in side it, unless someone manually goes in the DB and removed it
     * @var GenericCollection
     */
    protected $colourList;

    /**
     * the colour index we are currently up to
     * @var int
     */
    protected $colourIndex = 0;

    /**
     * the colour factory
     * @var ColorFactory
     */
    protected $colourFactory;

    /**
     * The current cache of the used colours
     * @var GenericCollection
     */
    protected $colourCache;

    /**
     * @param \Timetable_ColourMapper $timetableColourMapper
     * @return void
     */
    public function __construct(\Timetable_ColourMapper $timetableColourMapper)
    {
        $this->timetableColourMapper = $timetableColourMapper;
        $this->colourList = $this->timetableColourMapper->findAll();
        $this->colourFactory = new ColorFactory();
        $this->colourCache   = new GenericCollection(Color::class);
    }

    /**
     * creates a new colour for use for folders and adds that colour to the list, for reuse once all colours have been consumed
     * @return mixed
     */
    public function getNewColour()
    {
        $hexColour = $this->colourList[$this->colourIndex]->getHexColour();

        // Build colour object from hex string and cache the colour object
        if (!$this->colourCache->offsetExists($hexColour)) {
            $this->colourCache->setOffset($hexColour, $this->colourFactory->build($hexColour));
        }

        //sets or loops the colour index back to the start if we overflow
        $this->colourIndex = ($this->colourIndex + 1) % $this->colourList->count();

        return $this->colourCache->getOffset($hexColour);
    }

    /**
     * If a folder does not have a colour, sets its colour from the configured timetable colours
     *
     * @param GenericCollection|Model[] $folderList
     */
    public function applyFolderColours(GenericCollection $folderList): void
    {
        foreach ($folderList as $folder) {
            if (!$folder->getColour()) {
                $folder->setColour($this->getNewColour());
            }
        }
    }
}
