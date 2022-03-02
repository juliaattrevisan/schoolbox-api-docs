<?php

namespace Alaress\Csv;

use Schoolbox\Resource\Exception;

class ValidCSV
{

    /**
     */
    private $fp;

    /**
     * @param $filePointer
     * @return void
     * @throws Exception
     */
    public function __construct($filePointer)
    {
        $this->fp = $filePointer;

        if (!is_resource($this->fp)) {
            throw new Exception("File is not a file.");
        }

        $metadata = stream_get_meta_data($this->fp);
        $seekable = $metadata['seekable'] ?? false;
        if (!$seekable) {
            throw new Exception("File does not support seeking");
        }
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        //check the lot?
        return !$this->hasBOM() && $this->cleanHeaders() && $this->noUTF8();
    }

    public function hasBOM(): bool
    {
        //check for bom
        $bom = "\xef\xbb\xbf";
        if (fgets($this->fp, 4) === $bom) {
            throw new \UnexpectedValueException(
                'File contains BOM'
            );
        }
        return false;
    }

    public function skipBom()
    {
        if ($this->hasBOM()) {
            return $this->fp;
        }
        rewind($this->fp);

        return $this->fp;
    }

    public function cleanHeaders(): bool
    {
        //scans headers for utf8 characters, to make sure they are correct.
        $headers = fgetcsv($this->fp, 0);
        $cellCount = 0;
        foreach ($headers as $heading) {
            $cellCount++;
            if (false === mb_detect_encoding($heading, 'ASCII', true)) {
                $hexCode = join(' ', str_split(bin2hex($heading), 2));
                throw new \UnexpectedValueException(
                    sprintf('Header contains non ASCII character(s), Cell "%d". Cell: "%s", HEX: "%s', $cellCount, $heading, $hexCode)
                );
            }
        }
        return true;
    }

    public function noUTF8(): bool
    {
        //is there utf8 in the file anywhere? usually run after the clean headers, so really just checking the main data
        //parse csv
        $lineCount = 0;
        while (!feof($this->fp) && ($line = fgetcsv($this->fp)) !== false) {
            $lineCount++;
            $cellCount = 0;
            foreach ($line as $cell) {
                $cellCount++;
                //check line for non ascii characters
                if (false === mb_detect_encoding($cell, 'ASCII', true)) {
                    $hexCode = join(' ', str_split(bin2hex($cell), 2));
                    throw new \UnexpectedValueException(
                        sprintf('Line "%d", Cell "%d" contains non ascii character(s). Cell: "%s", HEX: "%s', $lineCount, $cellCount, $cell, $hexCode)
                    );
                }
            }
        }
        return true;
    }

    public function test()
    {
        $filename = $this->getOpt(self::OPT_LONG_FILE);
        if (null === $filename) {
            $this->getLogger()->error("no --file specified");
            exit();
        }
        //load file
        $fp = fopen($filename, 'r');



        //check for bom
        $bom = "\xef\xbb\xbf";
        if (fgets($fp, 4) === $bom) {
            $this->getLogger()->notice('file contains BOM (\xef\xbb\xbf) in the first line SA-13246 fixes this for the user import.');
        }
        if (fgets($fp, 4) !== $bom) {
            // BOM not found - rewind pointer to start of file.
            rewind($fp);
        }

        //parse csv
        $lineCount = 0;
        while(!feof($fp) && ($line = fgetcsv($fp)) !== false) {
            $lineCount++;
            $cellCount = 0;
            foreach ($line as $cell) {
                $cellCount++;
                //check line for non ascii characters
                if (false === mb_detect_encoding($cell, 'ASCII', true)) {
                    $this->getLogger()->notice(sprintf('Line "%d", Cell "%d" contains non ascii character(s)', $lineCount, $cellCount));
                    //build a nice hex code, separating each hex code by a space
                    $hexCode = join(' ', str_split(bin2hex($cell), 2));
                    $this->getLogger()->notice(sprintf('Cell: "%s" Hex: "%s"', $cell, $hexCode));
                }
            }

        }
    }
}
