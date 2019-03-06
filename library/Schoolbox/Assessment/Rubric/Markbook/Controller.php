<?php
/**
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Resource\Folder\Model as Folder;

class Controller extends Base
{
    /**
     * Displays a list of rubric marks for students in the specified class
     *
     * @api   GET /learning/rubricMarkbook/{folderId}
     * @level 190
     * @param Folder $folder                    Folder for this class
     * @throws ServiceNotFound                       If unable to locate HTML web Output
     * @throws \UnexpectedValueException             If folder not provided or could not be found
     */
    public function getMarkbook(Folder $folder = null): void
    {
        if (null === $folder) {
            throw new \UnexpectedValueException('Cannot render rubric markbook - folder not found');
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@rubricMarkbook/markbookOuter.twig', [
                'folder'      => $folder
            ])->setTab('submission')->setTitle('Rubric Markbook for ' . $folder->getName())
        );
    }
}
