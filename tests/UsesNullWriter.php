<?php

use Illuminate\Container\Container;
use Valet\OperatingSystem;

trait UsesNullWriter
{
    public function setNullWriter()
    {
        Container::getInstance()->instance('writer', new NullWriter);
        Container::getInstance()->instance(OperatingSystem::class, new OperatingSystem('Darwin'));
    }
}

class NullWriter
{
    public function writeLn($msg)
    {
        // do nothing
    }
}
