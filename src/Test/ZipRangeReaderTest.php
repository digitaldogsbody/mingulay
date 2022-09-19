<?php

namespace Mingulay;

use Mingulay\Exception\NotResource;
use PHPUnit\Framework\TestCase;
use Mingulay\Exception\InvalidZipFile;
use Mingulay\Exception\NotSeekable;


/**
 * Test suite for Mingulay ZipRangeReader.
 */
class ZipRangeReaderTest extends TestCase
{

    /**
     * Test the construction flow with a valid Zip containing a single file.
     */
    public function testConstructWithSingleFile()
    {
        $fp = fopen("src/Test/fixtures/single-file.zip", "rb");
        $zip_info = new ZipRangeReader($fp);

        $this->assertEquals(-22, $zip_info->eocd_offset);
        $this->assertEquals(82, $zip_info->cdr_offset);
        $this->assertEquals(91, $zip_info->cdr_size);
        $this->assertEquals(1, $zip_info->cdr_total);
        $this->assertCount(1, $zip_info->files);
        $this->assertEquals("README.md", $zip_info->files[0]["file_name"]);
        $this->assertEquals(43, $zip_info->files[0]["uncompressed_size"]);
        $this->assertEquals(43, $zip_info->files[0]["compressed_size"]);
        $this->assertEquals("C6E036CC", $zip_info->files[0]["CRC32"]);

        fclose($fp);
    }

    /**
     * Test the construction flow with a valid Zip containing a multiple files.
     */
    public function testConstructWithMultipleFiles()
    {
        $fp = fopen("src/Test/fixtures/multiple-files.zip", "rb");
        $zip_info = new ZipRangeReader($fp);

        $this->assertEquals(-22, $zip_info->eocd_offset);
        $this->assertEquals(11342, $zip_info->cdr_offset);
        $this->assertEquals(180, $zip_info->cdr_size);
        $this->assertEquals(2, $zip_info->cdr_total);
        $this->assertCount(2, $zip_info->files);
        $this->assertEquals("LICENSE", $zip_info->files[0]["file_name"]);
        $this->assertEquals(34523, $zip_info->files[0]["uncompressed_size"]);
        $this->assertEquals(11223, $zip_info->files[0]["compressed_size"]);
        $this->assertEquals("README.md", $zip_info->files[1]["file_name"]);

        fclose($fp);
    }

    /**
     * Test the construction flow with a valid Zip containing a single file and a whole-file comment.
     */
    public function testConstructWithSingleFileWithComment()
    {
        $fp = fopen("src/Test/fixtures/single-file-with-comment.zip", "rb");
        $zip_info = new ZipRangeReader($fp);

        $this->assertEquals(-67, $zip_info->eocd_offset);

        fclose($fp);
    }

    /**
     * Test the construction flow with a valid Zip containing multiple files and a whole-file comment.
     */
    public function testConstructWithMultipleFilesWithComment()
    {
        $fp = fopen("src/Test/fixtures/multiple-files-with-comment.zip", "rb");
        $zip_info = new ZipRangeReader($fp);

        $this->assertEquals(-56, $zip_info->eocd_offset);

        fclose($fp);
    }

    /**
     * Test the construction flow with a valid Zip containing a single file and a file-level comment.
     */
    public function testConstructWithSingleFileWithFileComment()
    {
        $fp = fopen("src/Test/fixtures/single-file-with-file-comment.zip", "rb");
        $zip_info = new ZipRangeReader($fp);

        $this->assertEquals(-22, $zip_info->eocd_offset);
        $this->assertEquals(82, $zip_info->cdr_offset);
        $this->assertEquals(125, $zip_info->cdr_size);
        $this->assertEquals("This is an individual file comment", $zip_info->files[0]["comment"]);

        fclose($fp);
    }

    /**
     * Test that the constructor errors out if you pass it a non-resource object.
     */
    public function testConstructWithInvalidFD()
    {
        $this->expectException(NotResource::class);
        $zip_info = new ZipRangeReader("you can't pass a string!");
    }

    /**
     * Test that the constructor errors out if you pass it something that is not a zip file.
     */
    public function testConstructWithInvalidFile()
    {
        $this->expectException(InvalidZipFile::class);
        $fp = fopen("src/Test/fixtures/invalid-file.zip", "rb");
        $zip_info = new ZipRangeReader($fp);
        fclose($fp);
    }

    /**
     * Test that the constructor errors out if the EOCD is not valid.
     */
    public function testConstructWithInvalidEOCD()
    {
        $this->expectException(InvalidZipFile::class);
        $fp = fopen("src/Test/fixtures/invalid-eocd.zip", "rb");
        $zip_info = new ZipRangeReader($fp);
        fclose($fp);
    }

    /**
     * Test that the constructor errors out if the CDR is not valid.
     */
    public function testConstructWithInvalidCDR()
    {
        $this->expectWarning();
        $this->expectWarningMessage("Invalid Central Directory Header detected");
        $fp = fopen("src/Test/fixtures/invalid-cdr.zip", "rb");
        $zip_info = new ZipRangeReader($fp);
        fclose($fp);
    }

    /**
     * Test that the constructor errors out if you pass it a non-seekable resource.
     */
    public function testConstructWithNonSeekableFile()
    {
        $this->expectException(NotSeekable::class);
        $fp = fopen("php://stdin", "rb");
        $zip_info = new ZipRangeReader($fp);
        fclose($fp);
    }
}
