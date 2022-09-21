<?php

namespace Mingulay;

use Mingulay\Exception\FileNotFound;
use Mingulay\Exception\NoData;
use Mingulay\Exception\InvalidZipFile;
use Mingulay\Exception\UnsupportedCompression;

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
     * The Seeker to use.
     * @var SeekerInterface
     */
    protected $seeker;

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
     * @param SeekerInterface $seeker The Seeker instance providing data.
     * @throws InvalidZipFile Thrown if the Zip file is invalid.
     * @throws NoData Thrown if the Seeker provides a null response
     *
     */
    public function __construct(SeekerInterface $seeker)
    {
        $this->seeker = $seeker;
        $this->files = array();

        // Look for the EOCD Record
        $eocd = $this->findEOCD();

        // Retrieve Central Directory record details
        if(!$this->retrieveCDRInfo($eocd)) {
            throw new InvalidZipFile("The EOCD record does not have valid Central Directory information");
        }

        // Populate the files array
        $this->populateFiles();
    }

    /**
     * Find the End of Central Directory record.
     *
     * This should be the last data structure in the file, with an optional comment afterwards.
     * Therefore we seek back from the end of the file, starting at the default size of an EOCD, and look for the
     * magic 4 byte signature of the EOCD record. If not found, walk back 1 byte at a time up to the maximum permitted
     * offset until either the EOCD is found, the max offset is reached, or we run out of data from the Seeker.
     *
     * @return string The EOCD record
     * @throws NoData Thrown if the Seeker returns a null response
     * @throws InvalidZipFile Thrown if the Zip file does not have an EOCD Record
     */
    private function findEOCD(): string
    {
        $data = $this->seeker->retrieveEnd(self::EOCD_LENGTH + self::MAX_EXTRA);
        if(is_null($data)) {
            throw new NoData("No data could be read from the Seeker when attempting to retrieve EOCD");
        }

        $current_pos = 0 - self::EOCD_LENGTH;
        $found_eocd_header = false;

        while(!$found_eocd_header && $current_pos > (0 - strlen($data))) {
            $bytes = @unpack("I*", substr($data, $current_pos, 4))[1];
            if($bytes === self::EOCD_SIG) {
                $found_eocd_header = true;
                $this->eocd_offset = $current_pos;
            }
            else {
                $current_pos--;
            }
        }
        if(!$found_eocd_header) {
            throw new InvalidZipFile("No EOCD record was found");
        }
        return substr($data, $current_pos, 22);
    }

    /**
     * Read information about the Central Directory Records from the EOCD.
     *
     * @param string $eocd The EOCD record.
     * @return bool true if the CDR information was parsed successfully, false otherwise.
     */
    private function retrieveCDRInfo(string $eocd): bool
    {
        // We only need a part of the EOCD to get the Central Directory information
        // Data            | Offset | Bytes
        // Total # of CDRs | 10     | 2
        // Size of CD      | 12     | 4
        // Offset of CDR   | 16     | 4

        // Unpack bytes to short (2 bytes) or long (4 bytes)
        // Short is used instead of int to ensure it is 16 bits
        // Additionally we force little-endian types, as this is what Zip format uses
        // We also name the elements, otherwise they overwrite each other and we only get the last one (thanks PHP!)
        // See https://www.php.net/manual/en/function.unpack.php Caution box
        $unpacked = unpack("vtotal/Vsize/Voffset", substr($eocd, 10, 10));
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
     * @throws NoData Thrown if the Seeker provides a null response
     */
    private function populateFiles()
    {
        // Retrieve the CDR data
        $data = $this->seeker->retrieveStart($this->cdr_size, $this->cdr_offset);
        if(is_null($data)) {
            throw new NoData("No data could be read from the Seeker when attempting to retrieve CDRs");
        }

        // Track the current position within the data stream, since records can be variable length
        $current_position = 0;

        for($i = 0; $i < $this->cdr_total; $i++) {
            // Retrieve the fixed length parts of the record and unpack them
            $record = substr($data, $current_position, 46);
            $current_position += 46;

            $unpacked = unpack("Vheader/vversion/vextract/vgeneral/vcompression/vtime/vdate/H8crc/Vcsize/Vusize/vfnlength/veflength/vfclength/vdisk/viattrib/Veattrib/Voffset", $record);

            // Use the length data in fnlength, eflength, and fclength to retrieve the rest of the record
            $file_name = substr($data, $current_position, $unpacked['fnlength']);
            $current_position += $unpacked['fnlength'];
;
            if ($unpacked["eflength"] > 0) {
                $extra_field = substr($data, $current_position, $unpacked['eflength']);
                $current_position += $unpacked['eflength'];
            }
            else {
                $extra_field = null;
            }

            if ($unpacked["fclength"] > 0) {
                $file_comment = substr($data, $current_position, $unpacked['fclength']);
                $current_position += $unpacked['fclength'];
            }
            else {
                $file_comment = "";
            }

            // Check the directory header is valid
            if($unpacked["header"] != self::CDFH_SIG) {
                trigger_error("Invalid Central Directory Header detected", E_USER_WARNING);
                continue;
            }

            $this->files[$file_name] = array(
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

    /**
     * Return a file pointer to a decompressed byte stream of the file at `$path`.
     *
     * @param string $path The file path within the zip.
     * @return resource A file pointer to the decompressed byte stream.
     * @throws FileNotFound Thrown if the requested file path does not exist in the zip.
     * @throws UnsupportedCompression Thrown if the file is compressed with something other than DEFLATE.
     */
    public function getStream(string $path) {
        // Try and retrieve the file from the directory information
        $file = @$this->files[$path];
        if(!$file) {
            throw new FileNotFound("File " . $path . " not found in the zip file.");
        }

        // Retrieve the Local File Header
        $header = $this->seeker->retrieveStart(30, $file["offset"]);
        $file["local_header"] = $this->readLocalHeader($header);

        // Calculate the offset of the compressed data (File offset + LFH length + file name length + extra field length)
        $data_offset = $file["offset"] + 30 + $file["local_header"]["fnlength"] + $file["local_header"]["eflength"];

        // Determine if file is compressed with DEFLATE
        switch($file["local_header"]["compression"]) {
            case 0:
                $deflate = false;
                break;
            case 8:
                $deflate = true;
                break;
            default:
                // Unsupported compression type
                throw new UnsupportedCompression("File " . $path . " is compressed with an unsupported compression type");
        }

        // Return the file pointer to the user
        return $this->seeker->getStream($file["compressed_size"], $data_offset, $deflate);
    }

    /**
     * Decode a Local File Header and return it as an array of data.
     *
     * @param string $header The header data.
     * @return array An array of the information decoded from the header.
     */
    private function readLocalHeader(string $header): array {
        return unpack("Vheader/vversion/vgeneral/vcompression/vtime/vdate/H8crc/Vcsize/Vusize/vfnlength/veflength/", $header);
    }

}