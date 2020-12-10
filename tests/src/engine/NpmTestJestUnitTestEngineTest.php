<?php

use PHPUnit\Framework\TestCase;

class NpmTestJestUnitTestEngineTest extends TestCase
{
    public function testFileExists()
    {
        $this->assertFileExists("src/engine/NpmTestJestUnitTestEngine.php");
    }
}