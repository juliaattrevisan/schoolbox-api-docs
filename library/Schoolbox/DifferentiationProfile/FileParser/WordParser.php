<?php

namespace Schoolbox\DifferentiationProfile\FileParser;

use PhpOffice\PhpWord\IOFactory;

class WordParser implements IParser
{
    /**
     * Constructor
     *
     * @param string $readerType The reader type to use for loading the Word document
     */
    public function __construct(
        private readonly string $readerType
    ) {
    }
    /**
     * @inheritdoc
     */
    public function flattenText(string $filePath): string
    {
        $phpWord = IOFactory::load($filePath, $this->readerType);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    foreach ($element->getElements() as $textRunElement) {
                        if (method_exists($textRunElement, 'getText')) {
                            $text .= $textRunElement->getText();
                        }
                    }
                    $text .= "\n";
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                    // Handle table elements
                    foreach ($element->getRows() as $row) {
                        foreach ($row->getCells() as $cell) {
                            foreach ($cell->getElements() as $cellElement) {
                                if (method_exists($cellElement, 'getText')) {
                                    $text .= $cellElement->getText() . "\t";
                                } elseif ($cellElement instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                    foreach ($cellElement->getElements() as $textRunElement) {
                                        if (method_exists($textRunElement, 'getText')) {
                                            $text .= $textRunElement->getText();
                                        }
                                    }
                                    $text .= "\t";
                                }
                            }
                        }
                        $text .= "\n";
                    }
                }
            }
        }
        return $text;
    }
}
