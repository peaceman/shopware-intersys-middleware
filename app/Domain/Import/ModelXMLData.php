<?php
/**
 * lel since 11.08.18
 */
namespace App\Domain\Import;

use App\ImportFile;

class ModelXMLData
{
    /**
     * @var ImportFile
     */
    protected $importFile;

    /**
     * @var string
     */
    protected $xmlString;

    /**
     * ModelXMLData constructor.
     * @param ImportFile $importFile
     * @param string $xmlString
     */
    public function __construct(ImportFile $importFile, string $xmlString)
    {
        $this->importFile = $importFile;
        $this->xmlString = $xmlString;
    }

    /**
     * @return ImportFile
     */
    public function getImportFile(): ImportFile
    {
        return $this->importFile;
    }

    /**
     * @return string
     */
    public function getXMLString(): string
    {
        return $this->xmlString;
    }

    public function getSimpleXMLElement(): \SimpleXMLElement
    {
        return new \SimpleXMLElement($this->xmlString);
    }
}