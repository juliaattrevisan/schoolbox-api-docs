<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_Statistics_TopFilesController extends Component_Homepage_Statistics_ControllerAbstract
{

    private $files;

    public function execute()
    {

        $dateFilter = '';
        if (count($this->dateRange)) {
            $dateFilter = sprintf(
                "AND time BETWEEN '%s' AND '%s'",
                date('Y-m-d', $this->dateRange[0]),
                date('Y-m-d', $this->dateRange[1])
            );
        }

        $stmt = Database::getInstance()->prepare(
            "
            SELECT f.id, f.`realname`, f.submitted, f.file_size, COUNT(f.id) AS `count` FROM cmsLog cl
            LEFT JOIN `file` f ON cl.`objectId` = f.id AND cl.`object` = 'file'
            WHERE f.id IS NOT NULL
            AND f.`category` = :cid
            $dateFilter
            GROUP BY f.id
            ORDER BY `count` DESC"
        );

        $stmt->execute(array('cid' => $this->getContext()->getId()));

        $i = 0;
        while ($file = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->files[$i] = $file;
            $this->files[$i]['progress'] = (int)$this->files[$i]['count'] / $this->files[0]['count'] * 100;
            $this->files[$i]['hits'] = (int)$this->files[$i]['count'];
            ++$i;
        }

        return true;
    }

    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'topFiles')) {
            return false;
        }

        $this->setBlock($templateHandle, 'top_file', 'top_file_block');
        $this->setBlock($templateHandle, 'no_top_file', 'no_top_file_block');

        if (count($this->files)) {
            $allowedHandler = new AllowedFile();
            for ($i = 0; $i < 5 && $i < count($this->files); ++$i) {
                $mime = $allowedHandler->getMime(
                    substr($this->files[$i]['realname'], strripos($this->files[$i]['realname'], '.') + 1)
                );

                // check the mime icon can be determined, and that it exists, and if not, use a default
                $icon = sprintf('/images/mime/60x60/%s.png', $mime ? str_replace('/', '_', $mime) : 'source');
                if (!file_exists(WEB_root . $icon)) {
                    $icon = '/images/mime/60x60/source.png';
                }

                $rel = new Alaress_Date_Format($this->files[$i]['submitted'], 'Y-m-d H:i:s');
                $this->setVar(
                    array(
                        'FILE_ID' => $this->files[$i]['id'],
                        'FILE_THUMB' => $icon,
                        'FILE_NAME' => $this->files[$i]['realname'],
                        'FILE_SIZE' => Alaress_Byte_Format::bytesToString($this->files[$i]['file_size']),
                        'FILE_DATE' => $rel->getLong(),
                        'FILE_PROGRESS' => $this->files[$i]['progress'],
                        'FILE_HITS' => $this->files[$i]['hits'],
                    )
                );
                $this->parse('top_file_block', 'top_file', true);
            }

            $this->setVar(array(
                'no_top_file_block' => ''
            ));
        } else {
            $this->setVar(array(
                'top_file_block' => ''
            ));

            $this->parse('no_top_file_block', 'no_top_file');
        }

        return true;
    }

}
