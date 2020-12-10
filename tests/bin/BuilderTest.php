<?php

use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    public function testFileExists()
    {
        $this->assertFileExists("bin/Builder.php");
    }
}