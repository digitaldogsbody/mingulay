<?php

namespace Mingulay;

use Mingulay\Exception\NotResource;
use Mingulay\Exception\NotSeekable;
use Mingulay\Exception\InvalidZipFile;

/**
 * Provides a utility to parse the directory records from a Zip file
 */
class ZipRangeReader
{
    // Useful Zip constants
    /**
     * End of Central Directory signature
     */
    const EOCD_SIG = 0x06054b50;
    /**
     * Central Directory File Header signature
     */
    const CDFH_SIG = 0x02014b50;
    /**
     * Local File Header signature
     */
    const LFH_SIG = 0x04034b50;
    /**
     * EOCD length in bytes
     */
    const EOCD_LENGTH = 22;
    /**
     * Maximum extra bytes at the end of a file after the EOCD
     */
    const MAX_EXTRA = 65535;

    /**
     * The file handle of the Zip object.
     * @var resource
     */
    protected $stream;

    /**
     * The byte offset of the EOCD from the end of the file. Expressed as a negative.
     * @var int $eocd_offset
     */
    public $eocd_offset;

    /**
     * The byte offset of the first Central Directory Record from the start of the file.
     * @var int
     */
    public $cdr_offset;

    /**
     * The total size of all the Central Directory Records in bytes.
     * @var int
     */
    public $cdr_size;

    /**
     * The number of Central Directory records expected.
     * @var int
     */
    public $cdr_total;

    /**
     * The list of files inside the zip.
     * @var array
     */
    public $files;


    /**
     * Create a new ZipRangeReader object, attempt to parse the zip file and populate a list with file details.
     *
     * @param resource $stream A file handle to a seekable file.
     *
     * @throws NotResource - Returned if the $stream value is not a file handle.
     * @throws NotSeekable - Returned if the $stream file handle is not seekable.
     * @throws InvalidZipFile - Returned if the Zip file is invalid.
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

    /**
     * Find the End of Central Directory record.
     *
     * This should be the last data structure in the file, with an optional comment afterwards.
     * Therefore we seek back from the end of the file, starting at the default size of an EOCD, and look for the
     * magic 4 byte signature of the EOCD record. If not found, walk back 1 byte at a time up to the maximum permitted
     * offset until either the EOCD is found, the max offset is reached, or the start of the file is reached.
     *
     * @return bool true if EOCD was found, false otherwise.
     */
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

    /**
     * Read information about the Central Directory Records from the EOCD.
     *
     * @return bool true if the CDR information was parsed successfully, false otherwise.
     */
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

    /**
     * Read the Central Directory Records and populate an array of information for each file in the ZIP.
     *
     * @return void
     */
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
                trigger_error("Invalid Central Directory Header detected", E_USER_WARNING);
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

    /**
     * Correct the CRC32 values read from the Central Directory Records.
     *
     * Due to the Zip format's little-endianness, and the nature of the parsing using `unpack`, we end up
     * with the hexadecimal byte values in reverse (as the H unpack parameter only ensures each individual byte
     * is correctly read as little-endian). This function simply splits the resultant CRC strings into byte pairs,
     * reverses the order and rejoins them, additionally uppercasing the result for convention's sake.
     *
     * @param string $crc The CRC read from the CDR.
     * @return string The corrected CRC value.
     */
    private function correctCRC(string $crc): string
    {
        return strtoupper(implode(array_reverse(str_split($crc, 2))));
    }
}