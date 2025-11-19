<?php

namespace Schoolbox\DifferentiationProfile\FileParser;

use PhpOffice\PhpWord\IOFactory;
use Schoolbox\File\Mimetype;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\IStorage;

class ParserFactory
{
    public const string PARSER_PDF = 'pdf';
    public const string PARSER_WORD2007 = 'Word2007';
    public const string PARSER_ODTEXT = 'ODText';
    public const string PARSER_MSDOC = 'MsDoc';
    private PDFParser $pdfParser;

    public function __construct()
    {
    }

    public function produce(
        IStorage $storage,
    ): ?IParser {
        $parserType = $this->getParserType($storage->getMimetype());
        return match ($parserType) {
            self::PARSER_PDF => $this->pdfParser ??= new PDFParser(),
            self::PARSER_WORD2007,
                self::PARSER_MSDOC,
                self::PARSER_ODTEXT => new WordParser($parserType),
            default => null,
        };
    }

    private function getParserType(Mimetype $mimetype): ?string
    {
        if ($mimetype->isPDF()) {
            return self::PARSER_PDF;
        } else if ($mimetype->matches('application/vnd.openxmlformats-officedocument.wordprocessingml.document')) {
            return self::PARSER_WORD2007;
        } else if ($mimetype->matches('application/msword')) {
            return self::PARSER_MSDOC;
        } else if ($mimetype->matches('application/vnd.oasis.opendocument.text')) {
            return self::PARSER_ODTEXT;
        } else {
            return null;
        }
    }
}
