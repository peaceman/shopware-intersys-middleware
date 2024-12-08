<?php

function fixture_path(string $path): string
{
    return __DIR__ . "/../docs/fixtures/{$path}";
}

function fixture_content(string $path): string
{
    return file_get_contents(fixture_path($path));
}
