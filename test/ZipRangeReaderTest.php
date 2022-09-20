<?php

namespace Mingulay;

use PHPUnit\Framework\TestCase;
use Mingulay\Seeker\LocalFileSeeker;
use Mingulay\Exception\InvalidZipFile;


/**
 * Test suite for Mingulay ZipRangeReader.
 */
class ZipRangeReaderTest extends TestCase
{

    const FIXTURE_PATH = "test/fixtures/";

    /**
     * Test the construction flow with a valid Zip containing a single file.
     */
    public function testConstructWithSingleFile()
    {
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH . "single-file.zip");
        $zip_info = new ZipRangeReader($seeker);

        $this->assertEquals(-22, $zip_info->eocd_offset);
        $this->assertEquals(82, $zip_info->cdr_offset);
        $this->assertEquals(91, $zip_info->cdr_size);
        $this->assertEquals(1, $zip_info->cdr_total);
        $this->assertCount(1, $zip_info->files);
        $this->assertEquals("README.md", $zip_info->files[0]["file_name"]);
        $this->assertEquals(43, $zip_info->files[0]["uncompressed_size"]);
        $this->assertEquals(43, $zip_info->files[0]["compressed_size"]);
        $this->assertEquals("C6E036CC", $zip_info->files[0]["CRC32"]);
    }

    /**
     * Test the construction flow with a valid Zip containing a multiple files.
     */
    public function testConstructWithMultipleFiles()
    {
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH . "multiple-files.zip");
        $zip_info = new ZipRangeReader($seeker);

        $this->assertEquals(-22, $zip_info->eocd_offset);
        $this->assertEquals(11342, $zip_info->cdr_offset);
        $this->assertEquals(180, $zip_info->cdr_size);
        $this->assertEquals(2, $zip_info->cdr_total);
        $this->assertCount(2, $zip_info->files);
        $this->assertEquals("LICENSE", $zip_info->files[0]["file_name"]);
        $this->assertEquals(34523, $zip_info->files[0]["uncompressed_size"]);
        $this->assertEquals(11223, $zip_info->files[0]["compressed_size"]);
        $this->assertEquals("README.md", $zip_info->files[1]["file_name"]);
    }

    /**
     * Test the construction flow with a valid Zip containing a single file and a whole-file comment.
     */
    public function testConstructWithSingleFileWithComment()
    {
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH . "single-file-with-comment.zip");
        $zip_info = new ZipRangeReader($seeker);

        $this->assertEquals(-67, $zip_info->eocd_offset);
    }

    /**
     * Test the construction flow with a valid Zip containing multiple files and a whole-file comment.
     */
    public function testConstructWithMultipleFilesWithComment()
    {
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH . "multiple-files-with-comment.zip");
        $zip_info = new ZipRangeReader($seeker);

        $this->assertEquals(-56, $zip_info->eocd_offset);
    }

    /**
     * Test the construction flow with a valid Zip containing a single file and a file-level comment.
     */
    public function testConstructWithSingleFileWithFileComment()
    {
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH . "single-file-with-file-comment.zip");
        $zip_info = new ZipRangeReader($seeker);

        $this->assertEquals(-22, $zip_info->eocd_offset);
        $this->assertEquals(82, $zip_info->cdr_offset);
        $this->assertEquals(125, $zip_info->cdr_size);
        $this->assertEquals("This is an individual file comment", $zip_info->files[0]["comment"]);
    }

    /**
     * Test that the constructor errors out if you pass it something that is not a zip file.
     */
    public function testConstructWithInvalidFile()
    {
        $this->expectException(InvalidZipFile::class);
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH . "invalid-file.zip");
        $zip_info = new ZipRangeReader($seeker);
    }

    /**
     * Test that the constructor errors out if the EOCD is not valid.
     */
    public function testConstructWithInvalidEOCD()
    {
        $this->expectException(InvalidZipFile::class);
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH . "invalid-eocd.zip");
        $zip_info = new ZipRangeReader($seeker);
    }

    /**
     * Test that the constructor errors out if the CDR is not valid.
     */
    public function testConstructWithInvalidCDR()
    {
        $this->expectWarning();
        $this->expectWarningMessage("Invalid Central Directory Header detected");
        $seeker = new LocalFileSeeker(self::FIXTURE_PATH . "invalid-cdr.zip");
        $zip_info = new ZipRangeReader($seeker);
    }
}
