<?php

namespace Schoolbox\DifferentiationProfile\FileParser;

use Schoolbox\File\Mimetype;
use Schoolbox\Resource\Storage\IStorage;

class ParserFactory
{
    /* Parser types
     */
    public const string PARSER_PDF = 'pdf';
    public const string PARSER_WORD2007 = 'Word2007';
    public const string PARSER_ODTEXT = 'ODText';
    public const string PARSER_MSDOC = 'MsDoc';
    /* Mime types for word docs
     */
    public const string MIME_WORD2007 = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    public const string MIME_MSDOC = 'application/msword';
    public const string MIME_ODTEXT = 'application/vnd.oasis.opendocument.text';
    private PDFParser $pdfParser;

    public function __construct()
    {
    }

    /**
     * Produce a parser based on the storage mimetype
     *
     * @param IStorage $storage The storage to produce a parser for
     * @return IParser|null The produced parser or null if no parser is available for the mimetype
     */
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

    /**
     * Get the parser type for a given mimetype
     *
     * @param Mimetype $mimetype The mimetype to get the parser type for
     * @return string|null The parser type or null if no parser is available for the mimetype
     */
    private function getParserType(Mimetype $mimetype): ?string
    {
        if ($mimetype->isPDF()) {
            return self::PARSER_PDF;
        } else if ($mimetype->matches(self::MIME_WORD2007)) {
            return self::PARSER_WORD2007;
        } else if ($mimetype->matches(self::MIME_MSDOC)) {
            return self::PARSER_MSDOC;
        } else if ($mimetype->matches(self::MIME_ODTEXT)) {
            return self::PARSER_ODTEXT;
        } else {
            return null;
        }
    }
}
