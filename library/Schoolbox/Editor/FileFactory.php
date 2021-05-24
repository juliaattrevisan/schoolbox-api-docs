<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Editor;

use InvalidArgumentException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\My\Folder\Mapper as MyFolderMapper;
use UnexpectedValueException;

/**
 * Class FileFactory
 *
 * Normalised info about the node you chose in the picker.
 *
 * @package Schoolbox\Editor
 */
class FileFactory
{

    /**
     * A hash tuple e.g. browse:55
     *
     * @var string
     */
    private $hash;


    /**
     * Constructor
     *
     * @param string $hashTuple
     */
    public function __construct($hashTuple)
    {

        /* Ridiculous quick fix
         */
        $this->hash = str_replace('sbfs', 'browse', $hashTuple);
    }


    /**
     * Produce json_encoded data
     *
     * @return string
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function produce(): string
    {
        $folderMapper = false;
        $type = false;

        $data = [
            'filesystem' => '',
            'type' => '',
            'hash' => '',
            'name' => '',
            'id' => ''
        ];

        if ($this->hash) {
            /* When identifying a storage file via browse:xxx which is
             * a string used internally in resource browser messages
             */
            [$filesystem, $id] = explode(':', $this->hash);

            /* Folders treated uniquely
             * Regular folders and My Folder are also treated uniquely
             */
            if (strpos($filesystem, '.folder') !== false) {
                if ($filesystem === 'browse.folder') {
                    $folderMapper = FolderMapper::getInstance();
                    $type = 'folder';
                } else if ($filesystem === 'myfile.folder') {
                    $folderMapper = MyFolderMapper::getInstance();
                    $type = 'myfolder';
                }

                /* If it understood what kind of folder was chose,
                 * continue and check that it hasn't been deleted
                 * or otherwise made unavailable in the meantime
                 */
                if ($folderMapper) {
                    try {
                        $folder = $folderMapper->findById($id);

                        $data = [
                            'filesystem' => '',
                            'type'       => $type,
                            'hash'       => '',
                            'name'       => $folder->getName(),
                            'mime'       => 'folder',
                            'id'         => $folder->getId()
                        ];
                    } catch (\Exception $e) {
                    }
                }
            } else {
                /* Other files
                 */
                $collector = (new CollectorFactory())->build([$this->hash])->getFirst();

                $data = [
                    'filesystem' => $filesystem,
                    'type'       => $collector->getMimetype()->getType(),
                    'hash'       => $collector->getHash(),
                    'name'       => $collector->getFilename(),
                    'mime'       => $collector->getMime(),

                    /* Used for video/audio, sadly
                     */
                    'id'         => $id
                ];
            }
        }

        return json_encode($data);
    }
}
