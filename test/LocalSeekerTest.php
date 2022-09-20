<?php

namespace Mingulay;

use PHPUnit\Framework\TestCase;
use Mingulay\Exception\NotSeekable;
use Mingulay\Seeker\LocalFileSeeker;

/**
 * Test suite for LocalFileSeeker.
 */
class LocalSeekerTest extends TestCase
{

    const FIXTURE_PATH = "test/fixtures/";

    /**
     * Test retrieval from the start of the file.
     */
    public function testRetrieveStart()
    {
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH .  "single-file.zip");
        $this->assertEquals("504b", unpack("H*",$seeker->retrieveStart(2))[1]);
    }

    /**
     * Test retrieval from the start of the file with an offset.
     */
    public function testRetrieveStartWithOffset()
    {
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH .  "single-file.zip");
        $this->assertEquals("0304", unpack("H*",$seeker->retrieveStart(2, 2))[1]);
    }

    /**
     * Test retrieval from the end of the file.
     */
    public function testRetrieveEnd()
    {
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH .  "single-file.zip");
        $this->assertEquals("520000000000", unpack("H*",$seeker->retrieveEnd(6))[1]);
    }

    /**
     * Test retrieval from the end of the file with an offset.
     */
    public function testRetrieveEndWithOffset()
    {
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH .  "single-file.zip");
        $this->assertEquals("01005b00", unpack("H*",$seeker->retrieveEnd(4, 12))[1]);
    }

    /**
     * Test that the constructor errors out if you pass it a non-seekable resource.
     */
    public function testConstructWithNonExistentFile()
    {
        $this->expectException(\InvalidArgumentException::class);
        $seeker = new LocalFileSeeker("invalid");
    }

    /**
     * Test that the constructor errors out if you pass it a non-seekable resource.
     */
    public function testConstructWithNonSeekableFile()
    {
        $this->expectException(NotSeekable::class);
        $seeker = new LocalFileSeeker("php://stdin");
    }
}