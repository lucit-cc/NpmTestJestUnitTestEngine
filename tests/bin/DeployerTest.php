<?php

use PHPUnit\Framework\TestCase;

class DeployerTest extends TestCase
{
    public function testFileExists()
    {
        $this->assertFileExists("bin/Deployer.php");
    }
}