<?php

namespace App\Domain\Import;

class MissingShopwareEntityException extends \RuntimeException
{
    private string $entity;
    private string $field;
    private mixed $value;

    public function __construct(string $entity, string $field, mixed $value)
    {
        $this->entity = $entity;
        $this->field = $field;
        $this->value = $value;

        parent::__construct("Couldn't find entity of type `{$entity}` where `{$field}` equals `{$value}`");
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
