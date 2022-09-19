<?php

namespace Mingulay;

use PHPUnit\Framework\TestCase;
use Mingulay\Exception\NotSeekable;
use Mingulay\Seeker\LocalSeeker;

/**
 * Test suite for LocalSeeker.
 */
class LocalSeekerTest extends TestCase
{

    const FIXTURE_PATH = "src/Test/fixtures/";

    /**
     * Test retrieval from the start of the file.
     */
    public function testRetrieveStart()
    {
        $seeker = new LocalSeeker(self::FIXTURE_PATH .  "single-file.zip");
        $this->assertEquals("504b", unpack("H*",$seeker->retrieveStart(2))[1]);
    }

    /**
     * Test retrieval from the start of the file with an offset.
     */
    public function testRetrieveStartWithOffset()
    {
        $seeker = new LocalSeeker(self::FIXTURE_PATH .  "single-file.zip");
        $this->assertEquals("0304", unpack("H*",$seeker->retrieveStart(2, 2))[1]);
    }

    /**
     * Test retrieval from the end of the file.
     */
    public function testRetrieveEnd()
    {
        $seeker = new LocalSeeker(self::FIXTURE_PATH .  "single-file.zip");
        $this->assertEquals("520000000000", unpack("H*",$seeker->retrieveEnd(6))[1]);
    }

    /**
     * Test retrieval from the end of the file with an offset.
     */
    public function testRetrieveEndWithOffset()
    {
        $seeker = new LocalSeeker(self::FIXTURE_PATH .  "single-file.zip");
        $this->assertEquals("01005b00", unpack("H*",$seeker->retrieveEnd(4, 12))[1]);
    }

    /**
     * Test that the constructor errors out if you pass it a non-seekable resource.
     */
    public function testConstructWithNonExistentFile()
    {
        $this->expectException(\InvalidArgumentException::class);
        $seeker = new LocalSeeker("invalid");
    }

    /**
     * Test that the constructor errors out if you pass it a non-seekable resource.
     */
    public function testConstructWithNonSeekableFile()
    {
        $this->expectException(NotSeekable::class);
        $seeker = new LocalSeeker("php://stdin");
    }
}