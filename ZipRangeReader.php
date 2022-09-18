<?php

namespace Mingulay;

use Mingulay\Exception\NotResource;
use Mingulay\Exception\NotSeekable;
use Mingulay\Exception\InvalidZipFile;

class ZipRangeReader
{
    // Useful Zip constants

    // End of Central Directory signature
    const EOCD_SIG = 0x06054b50;
    // Central Directory File Header signature
    const CDFH_SIG = 0x02014b50;
    // Local File Header signature
    const LFH_SIG = 0x04034b50;

    // EOCD length in bytes
    const EOCD_LENGTH = 22;
    // Maximum extra bytes at the end of a file after the EOCD
    const MAX_EXTRA = 65535;

    protected $stream;
    protected $eocd_offset;
    protected $cdr_offset;
    protected $cdr_size;
    protected $cdr_total;

    public $files;


    /**
     * Create a new ZipRangeReader object, attempt to parse the zip file and populate a list with file details
     *
     * Parameters:
     * @param $stream - A file handle to a seekable file
     *
     * Exceptions:
     * @throws NotResource - Returned if the $stream value is not a file handle
     * @throws NotSeekable - Returned if the $stream file handle is not seekable
     * @throws InvalidZipFile - Returned if the Zip file is invalid
     *
     * Example Usage:
     * $zip_info = new ZipRangeReader('example.zip')
     * var_dump($zip_info->files)
     */

    public function __construct($stream)
    {
        $this->stream = $stream;
        $this->files = array();

        // Failsafes //
        // Check that the provided argument is a file handler
        // It would be nice to do this with a type hint, but PHP does not yet support this
        if (!is_resource($this->stream)) {
            throw new NotResource("The argument to ZipRangeReader must be a file pointer");
        }
        // Check the file handler is seekable
        if (!stream_get_meta_data($this->stream)['seekable']) {
            throw new NotSeekable("The file pointer passed to ZipRangeReader must be seekable");
        }

        // Look for the EOCD Record
        if(!$this->findEOCD()) {
            throw new InvalidZipFile("The provided file does not have an EOCD record");
        }

        // Retrieve Central Directory record details
        if(!$this->retrieveCDRInfo()) {
            throw new InvalidZipFile("The EOCD record does not have valid Central Directory information");
        }

        $this->populateFiles();
    }

    private function findEOCD(): bool
    {
        $current_pos = 0 - self::EOCD_LENGTH;
        $found_eocd_header = false;

        while(!$found_eocd_header && $current_pos > (0-self::EOCD_LENGTH-self::MAX_EXTRA)) {
            $seek_success = fseek($this->stream, $current_pos, SEEK_END);
            // Check we didn't try and seek past the start of the file
            if($seek_success === -1) {
                return false;
            }
            $data = fread($this->stream, 4);
            $data_int = @unpack("I*", $data)[1];
            if($data_int === self::EOCD_SIG) {
                $found_eocd_header = true;
                $this->eocd_offset = $current_pos;
            }
            else {
                $current_pos--;
            }
        }
        return $found_eocd_header;
    }

    private function retrieveCDRInfo(): bool
    {
        // We only need a part of the EOCD to get the Central Directory information
        // Data            | Offset | Bytes
        // Total # of CDRs | 10     | 2
        // Size of CD      | 12     | 4
        // Offset of CDR   | 16     | 4

        // We can retrieve all the bytes at once and use unpack to split them into shorts/longs
        fseek($this->stream, $this->eocd_offset+10, SEEK_END);
        $data = fread($this->stream, 10);

        // Unpack bytes to short (2 bytes) or long (4 bytes)
        // Short is used instead of int to ensure it is 16 bits
        // Additionally we force little-endian types, as this is what Zip format uses
        // We also name the elements, otherwise they overwrite each other and we only get the last one (thanks PHP!)
        // See https://www.php.net/manual/en/function.unpack.php Caution box
        $unpacked = unpack("vtotal/Vsize/Voffset", $data);
        if(count($unpacked) !== 3) {
            return false;
        }
        [$this->cdr_total, $this->cdr_size, $this->cdr_offset] = array_values($unpacked);

        // If any of the values are 0, something is wrong with the file
        // Technically, this could happen with a completely empty zip file, containing only the EOCD
        // but if you pass one of those, I think an error is ok
        if($this->cdr_total > 0 && $this->cdr_size > 0 && $this->cdr_offset > 0) {
            return true;
        }
        else {
            return false;
        }
    }

    private function populateFiles()
    {
        // Seek to start of first CDR
        fseek($this->stream, $this->cdr_offset, SEEK_SET);

        for($i = 0; $i < $this->cdr_total; $i++) {
            // Retrieve the fixed length parts of the record and unpack them
            $data = fread($this->stream, 46);
            $unpacked = unpack("Vheader/vversion/vextract/vgeneral/vcompression/vtime/vdate/H8crc/Vcsize/Vusize/vfnlength/veflength/vfclength/vdisk/viattrib/Veattrib/Voffset", $data);
            // Use the length data in fnlength, eflength, and fclength to retrieve the rest of the record
            $file_name = fread($this->stream, $unpacked['fnlength']);
            if ($unpacked["eflength"] > 0) {
                $extra_field = fread($this->stream, $unpacked['eflength']);
            }
            else {
                $extra_field = null;
            }

            if ($unpacked["fclength"] > 0) {
                $file_comment = fread($this->stream, $unpacked['fclength']);
            }
            else {
                $file_comment = "";
            }

            // Check the directory header is valid
            if($unpacked["header"] != self::CDFH_SIG) {
                // TODO: Raise a warning here
                continue;
            }

            $this->files[] = array(
                "file_name" => $file_name,
                "offset" => $unpacked["offset"],
                "compressed_size" => $unpacked["csize"],
                "uncompressed_size" => $unpacked["usize"],
                "CRC32" => $this->correctCRC($unpacked["crc"]),
                "comment" => $file_comment
            );
        }
    }

    private function correctCRC(string $crc): string
    {
        return strtoupper(implode(array_reverse(str_split($crc, 2))));
    }
}