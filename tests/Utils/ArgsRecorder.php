<?php

namespace Tests\Utils;

class ArgsRecorder
{
    private mixed $returnValue;
    public array $history = [];

    public function __construct(mixed $returnValue = true)
    {
        $this->returnValue = $returnValue;
    }

    public function __invoke(...$args): mixed
    {
        $this->history[] = $args;

        return $this->returnValue;
    }
}
