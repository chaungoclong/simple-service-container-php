<?php

namespace Chaungoclong\Container;

class Test
{
    private SubTest $subTest;

    public function __construct(SubTest $subTest)
    {
        $this->subTest = $subTest;
    }
}