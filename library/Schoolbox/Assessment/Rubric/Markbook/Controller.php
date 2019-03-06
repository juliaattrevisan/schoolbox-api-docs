<?php
/**
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\WebDav\FileServer\Folder;

class Controller extends Base
{
    /**
     * Displays a list of rubric marks for students in the specified class
     *
     * @api   GET /learning/rubricMarkbook/{folderId}
     * @level 190
     * @param Folder $classFolder                    Folder for this class
     * @throws ServiceNotFound                       If unable to locate HTML web Output
     * @throws \UnexpectedValueException             If folder not provided or could not be found
     */
    public function getMarkbook(Folder $classFolder = null): void
    {
        if (null === $classFolder) {
            throw new \UnexpectedValueException('Cannot render rubric markbook - folder not found');
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@rubricMarkbook/markbookOuter.twig', [
                'folder'      => $classFolder
            ])->setTab('submission')->setTitle('Rubric Markbook for ' . $classFolder->getName())
        );
    }
}
