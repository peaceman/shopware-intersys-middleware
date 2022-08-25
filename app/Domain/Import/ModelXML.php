<?php
/**
 * lel since 11.08.18
 */
namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class ModelXML implements ModelDTO
{
    /**
     * @var ImportFile
     */
    protected $importFile;

    /**
     * @var string
     */
    protected $xmlString;

    private ?\SimpleXMLElement $modelNode = null;

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

    private function getModelNode(): \SimpleXMLElement
    {
        if (!$this->modelNode) {
            $this->modelNode = new \SimpleXMLElement($this->xmlString);
        }

        return $this->modelNode;
    }

    public function getModelName(): string
    {
        return (string) $this->modelNode->Moddeno;
    }

    public function getModelNumber(): string
    {
        return (string) $this->modelNode->Modno;
    }

    public function getVatPercentage(): float
    {
        return floatval((string) $this->modelNode->Percentvat);
    }

    public function getManufacturerName(): string
    {
        return (string) $this->modelNode->Branddeno;
    }

    public function getTargetGroupGender(): ?TargetGroupGender
    {
        if (empty($fedas = $this->modelNode->Fedas ?? null))
            return null;

        return TargetGroupGender::tryFromFedas($fedas);
    }

    public function getBranches(): Enumerable
    {
        return Collection::make($this->getModelNode()->xpath('/Model/Color/Size/Branch') ?: [])
            ->map(fn (\SimpleXMLElement $e): string => (string) $e->Branchno)
            ->values();
    }

    public function getColorVariations(): Enumerable
    {
        return Collection::make($this->getModelNode()->xpath('/Model/Color') ?: [])
            ->map(fn (\SimpleXMLElement $e): ModelColorDTO => new ModelColorXML($this, $e))
            ->values();
    }
}
