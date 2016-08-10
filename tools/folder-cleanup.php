<?php

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;

$folderName = "Lost & Found";

require_once __DIR__ . '/../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
new \Schoolbox\Application\Daemon\Kernel("Orphaned Folder Cleanup");

$db      = Database::getInstance();
$logger  = new Alaress\Lumberjack\Logger(STDOUT, null, Monolog\Logger::INFO, [], isset($options['porcelain']));

$folderName = $db->quote($folderName);

$lostQuery = $db->prepare("SELECT `id` FROM `folder` WHERE `name` = {$folderName}");
$lostQuery->execute();

if ($lostQuery->rowCount() > 0) {
    $row = $lostQuery->fetch();
    $parent_id = $row['id'];
}
else
{
    $db->exec("INSERT INTO `folder` (name, parent, inherit) VALUES ({$folderName}, 0, 0)");
    $parent_id = $db->lastInsertId();
}

$moveQuery = $db->prepare("UPDATE `folder` SET `parent` = :parent_id WHERE `id` = :id;");

// Do a recursive lookup on every item and make sure the ancestry is valid

$folderMapper = FolderMapper::getInstance();

do {

    $errors = 0;

    $folders = $folderMapper->findAll();

    /** @var FolderModel $folder */
    foreach ($folders as $folder)
    {
        // Keep track of nodes we have traversed so we don't end up in a loop

        $folderCurrent = $folder;
        $found = [];

        try
        {
            while ($folderCurrent->getParent()->getId() != 0)
            {
                $found[$folderCurrent->getId()] = 1;

                /** @var FolderModel $folderCurrent */
                $folderCurrent = $folderMapper->findCheaplyById($folderCurrent->getParent()->getId());

                if ($folderCurrent->getParent()->getId() == null)
                {
                    // Node has no parent set

                    $logger->info(sprintf("Orphaned Folder '%s' (%d) moved to {$folderName} (%d)", $folder->getName(),
                        $folder->getId(), $parent_id));

                    $moveQuery->execute([
                        'parent_id' => $parent_id,
                        'id' => $folder->getId()
                    ]);

                    $errors++;

                    break;
                }
                else if (isset($found[$folderCurrent->getId()]))
                {
                    // Found a looping family tree

                    $loopString = "";

                    foreach ($found as $id => $item)
                        $loopString .= "{$id},";

                    $loopString = $loopString . $folderCurrent->getId();

                    $logger->info(sprintf("Looping Folder '%s' (%s) moved to {$folderName} (%d)", $folder->getName(),
                        $loopString, $parent_id));
                    $moveQuery->execute([
                        'parent_id' => $parent_id,
                        'id' => $folder->getId()
                    ]);

                    $errors++;

                    break;
                }
            }
        }
        catch (\Exception $e)
        {
            $logger->info(sprintf("Folder '%s' (%d) missing Parent (%d) moved to {$folderName} (%d)", $folder->getName(),
                $folder->getId(), $folder->getParent()->getId(), $parent_id));

            $moveQuery->execute([
                'parent_id' => $parent_id,
                'id' => $folder->getId()
            ]);

            $errors++;
        }
    }

} while ($errors > 0);
