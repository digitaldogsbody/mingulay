<?php

namespace Mingulay;

use Mingulay\Exception\NotResource;
use PHPUnit\Framework\TestCase;
use Mingulay\Exception\InvalidZipFile;
use Mingulay\Exception\NotSeekable;


class ZipRangeReaderTest extends TestCase
{

    public function testConstructWithSingleFile()
    {
        $fp = fopen("Test/fixtures/single-file.zip", "rb");
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

    public function testConstructWithMultipleFiles()
    {
        $fp = fopen("Test/fixtures/multiple-files.zip", "rb");
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

    public function testConstructWithSingleFileWithComment()
    {
        $fp = fopen("Test/fixtures/single-file-with-comment.zip", "rb");
        $zip_info = new ZipRangeReader($fp);

        $this->assertEquals(-67, $zip_info->eocd_offset);

        fclose($fp);
    }

    public function testConstructWithMultipleFilesWithComment()
    {
        $fp = fopen("Test/fixtures/multiple-files-with-comment.zip", "rb");
        $zip_info = new ZipRangeReader($fp);

        $this->assertEquals(-56, $zip_info->eocd_offset);

        fclose($fp);
    }

    public function testConstructWithSingleFileWithFileComment()
    {
        $fp = fopen("Test/fixtures/single-file-with-file-comment.zip", "rb");
        $zip_info = new ZipRangeReader($fp);

        $this->assertEquals(-22, $zip_info->eocd_offset);
        $this->assertEquals(82, $zip_info->cdr_offset);
        $this->assertEquals(125, $zip_info->cdr_size);
        $this->assertEquals("This is an individual file comment", $zip_info->files[0]["comment"]);

        fclose($fp);
    }

    public function testConstructWithInvalidFD()
    {
        $this->expectException(NotResource::class);
        $zip_info = new ZipRangeReader("you can't pass a string!");
    }

    public function testConstructWithInvalidFile()
    {
        $this->expectException(InvalidZipFile::class);
        $fp = fopen("Test/fixtures/invalid-file.zip", "rb");
        $zip_info = new ZipRangeReader($fp);
        fclose($fp);
    }

    public function testConstructWithInvalidEOCD()
    {
        $this->expectException(InvalidZipFile::class);
        $fp = fopen("Test/fixtures/invalid-eocd.zip", "rb");
        $zip_info = new ZipRangeReader($fp);
        fclose($fp);
    }

    public function testConstructWithInvalidCDR()
    {
        $this->expectWarning();
        $this->expectWarningMessage("Invalid Central Directory Header detected");
        $fp = fopen("Test/fixtures/invalid-cdr.zip", "rb");
        $zip_info = new ZipRangeReader($fp);
        fclose($fp);
    }

    public function testConstructWithNonSeekableFile()
    {
        $this->expectException(NotSeekable::class);
        $fp = fopen("php://stdin", "rb");
        $zip_info = new ZipRangeReader($fp);
        fclose($fp);
    }
}
