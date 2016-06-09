<?php

function handle_file($filename)
{

    $name = explode(".", $filename)[0];

    $parts = explode("_", $name);

    $result["date"] = $parts[1];
    $result["author"] = ucfirst($parts[2]);

    return $result;
}