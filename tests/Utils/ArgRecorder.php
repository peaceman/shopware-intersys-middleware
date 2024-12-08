<?php

namespace Tests\Utils;

class ArgRecorder
{
    public $args = [];

    public function __invoke(mixed $arg): bool
    {
        $this->args[] = $arg;

        return true;
    }

    public function latest(): mixed
    {
        return empty($this->args) ? null : end($this->args);
    }
}
