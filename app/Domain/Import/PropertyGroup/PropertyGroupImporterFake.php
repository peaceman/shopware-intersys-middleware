<?php

namespace App\Domain\Import\PropertyGroup;

use Illuminate\Support\Str;

class PropertyGroupImporterFake implements PropertyGroupImporter
{
    protected array $data = [];

    public function import(string $groupName, array $optionNames): PropertyGroupDTO
    {
        if (!isset($this->data[$groupName])) {
            $this->data[$groupName] = [
                'id' => (string) Str::uuid()->getHex(),
                'name' => $groupName,
                'options' => [],
            ];
        }

        $groupData = &$this->data[$groupName];

        $existingOptionNames = array_map(fn (array $v): string => $v['name'], $groupData['options']);
        $newOptionNames = array_diff($optionNames, $existingOptionNames);

        foreach ($newOptionNames as $newOptionName) {
            $groupData['options'][] = [
                'id' => (string) Str::uuid()->getHex(),
                'name' => $newOptionName,
            ];
        }

        return new PropertyGroupDTORaw($groupData);

//        return new PropertyGroupDTORaw([
//            'id' => Str::random(32),
//            'name' => $groupName,
//            'options' => array_map(
//                fn (string $name): array => ['id' => Str::random(32), 'name' => $name],
//                $optionNames
//            ),
//        ]);
    }
}
