<?php

namespace Alaress\Csv;

use Psr\Log\LoggerInterface;
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
        try {
            !$this->hasBOM() && $this->cleanHeaders() && $this->noUTF8();
            return true;
        } catch (\UnexpectedValueException $e) {
            return false;
        }
    }

    public function hasBOM(): bool
    {
        //check for bom
        $bom = "\xef\xbb\xbf";
        rewind($this->fp);
        if (fgets($this->fp, 4) === $bom) {
            throw new \UnexpectedValueException(
                'File contains BOM'
            );
        }
        return false;
    }

    public function skipBom()
    {
        try {
            $this->hasBOM();
            return $this->fp;
        } catch (\UnexpectedValueException $e) {
            rewind($this->fp);

            return $this->fp;
        }
    }

    public function cleanHeaders(): bool
    {
        //scans headers for utf8 characters, to make sure they are correct.
        $this->skipBom();
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

    /**
     * @param LoggerInterface $logger
     * @return void
     */
    public function locateUTF8(LoggerInterface $logger): void
    {
        //find and return all utf8 in the file
        //parse csv
        $lineCount = 0;
        while (!feof($this->fp) && ($line = fgetcsv($this->fp)) !== false) {
            $logger->debug(sprintf('checking line: "%d", "%s"', $lineCount, implode('","', $line)));
            $lineCount++;
            $cellCount = 0;
            foreach ($line as $cell) {
                $logger->debug(sprintf('checking cell: "%d", "%s"', $cellCount, $cell));
                $cellCount++;
                //check line for non ascii characters
                if (false === mb_detect_encoding($cell, 'ASCII', true)) {
                    $hexCode = join(' ', str_split(bin2hex($cell), 2));
                    $logger->notice(
                        sprintf('Line "%d", Cell "%d" contains non ascii character(s). Cell: "%s", HEX: "%s', $lineCount, $cellCount, $cell, $hexCode)
                    );
                }
            }
        }
    }
}
