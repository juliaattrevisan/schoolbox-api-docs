<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Breadcrumbs;

SitePage::extendExecutionTime(300);

$groupHandler = new Group();

if ($groupHandler->getPermission($_POST['homepageId']) < WRITE_ACCESS) {
    error(19);
} else {
    $user = $_SESSION['user'];
    $author = $_SESSION['user'];
    $categoryList = array($_POST['homepageId']);
    $groupList = array();
    $campusList = array();

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($_POST['homepageId']);


        if (isset($_POST['attachment'])) {
            $factory = new CollectorFactory();
            try {
                /** @var Storage\Model $result */
                $result = $factory->build($_POST['attachment'])->getFirst();
            } catch (\Schoolbox\Resource\Exception $e) {
                error(2);
                exit;
            }
            // Ensure the file is available locally
            $file = tempnam(Config::get('xml_dir'), 'cal');
            $fp       = fopen($file, 'wb');
            stream_copy_to_stream($result->readStream(), $fp);
            fclose($fp);

            $factory = new \Calendar\Import\Factory($user, $author, $categoryList, $groupList, $campusList, \Schoolbox\Calendar\Event::ACCESS_GROUP, $file);
            $strategy = $factory->build();
            $strategy->load();

            $allEvents = $strategy->getOutput();

            $this->setFile(TEMPLATE_content, 'calendar/importForm.html');
            $this->setBlock(TEMPLATE_content, 'event', 'event_block');

            if ($allEvents && count($allEvents)) {
                foreach ($allEvents as $currentEvent) {
                    $this->setVar(
                        array(
                            'EVENT_NAME' => $currentEvent['name'],
                            'EVENT_STATUS' => $currentEvent['status'],
                            'EVENT_ERROR' => $currentEvent['error'],
                        )
                    );

                    $this->parse('event_block', 'event', true);
                }
            } else {
                $this->setVar('event_block', '');
            }

            $trail = new Breadcrumbs();
            $trail->renderIntoSitePage($this, 'BREADCRUMB', [
                [
                    'text' => $folder->getName(),
                    'href' => "/homepage/{$folder->getId()}",
                    'class' => '',
                ],
                [
                    'text' => 'Import Calendar',
                    'href' => '',
                    'class' => 'active',
                ]
            ]);

            $this->setVar(
                array(
                    'HOMEPAGE_ID' => $folder->getId(),
                    'HOMEPAGE_NAME' => $folder->getName()
                )
            );
        } else {
            error(2);
        }
    } catch (\Exception $e) {
        error(13);
    }
}
