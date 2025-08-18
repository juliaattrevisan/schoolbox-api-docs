<?php

namespace Alaress\Csv;

use Psr\Log\LoggerInterface;
use Schoolbox\Resource\Exception;

class ValidCSV
{
    /**
     * @var resource
     */
    private $fp;

    /**
     * @param resource $filePointer
     * @return void
     * @throws Exception
     */
    public function __construct($filePointer)
    {
        if (!is_resource($filePointer)) {
            // die with a more helpful message since we can't enforce the Resource datatype
            throw new Exception('$filePointer was not a valid file resource');
        }

        $this->fp = $filePointer;

        if (!is_resource($this->fp)) {
            throw new Exception("File is not a file.");
        }

        $metadata = stream_get_meta_data($this->fp);
        $seekable = $metadata['seekable'];
        if (!$seekable) {
            throw new Exception("File does not support seeking");
        }
    }

    /**
     * scan the file, for BOM, non ascii in the header
     * This will ignore the rest of the file, as we should be able to have UTF8 in the body of the cells, but the headers and BOM are more important
     * for making sure they import into the system without issues
     * @return bool
     */
    public function isValid(): bool
    {
        //check the lot?
        return !$this->hasBOM() && $this->hasAsciiHeaderRow();
    }

    /**
     * checks the file for a byte order mark, (weird UTF8 character), that we can safely ignore but is good to check
     * if there is no BOM it will rewind the file pointer to the start of the file, so we can keep using it as normal
     * @return bool
     */
    public function hasBOM(): bool
    {
        //check for bom
        $bom = "\xef\xbb\xbf";
        rewind($this->fp);
        if (fgets($this->fp, 4) === $bom) {
            return true;
        }
        return false;
    }

    /**
     * skips the Byte order mark at the start of the file
     * @return resource
     */
    public function skipBom()
    {
        if (!$this->hasBOM()) {
            //there is no Byte order mark, rewind so we don't break the headers.
            rewind($this->fp);
        }
        return $this->fp;
    }

    /**
     * checks the header of the csv (skipping the BOM) and confirms all cells contain only ASCII characters
     * if there are non ascii characters, will return false.
     * @return bool
     */
    public function hasAsciiHeaderRow(): bool
    {
        //scans headers for utf8 characters, to make sure they are correct.
        $this->skipBom();
        $headers = fgetcsv($this->fp, 0, escape: '');
        $cellCount = 0;
        foreach ($headers as $heading) {
            $cellCount++;
            if (false === mb_detect_encoding($heading, 'ASCII', true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * scans the while file and will confirm where the non ASCII (UTF8) characters exist in the file.
     * including the BOM character
     * @param LoggerInterface $logger
     * @return void
     */
    public function locateUTF8(LoggerInterface $logger): void
    {
        //find and return all utf8 in the file
        rewind($this->fp);
        //parse csv
        $lineCount = 0;
        while (!feof($this->fp) && ($line = fgetcsv($this->fp, escape: '')) !== false) {
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
