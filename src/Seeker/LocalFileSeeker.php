<?php

namespace Mingulay\Seeker;

use Exception;
use InvalidArgumentException;
use Mingulay\Exception\NotSeekable;
use Mingulay\SeekerInterface;

class LocalFileSeeker implements SeekerInterface
{

    /**
     * The path of the local file.
     * @var string
     */
    protected $path;

    /**
     * The file pointer.
     * @var false|resource
     */
    protected $stream;

    /**
     * The size of the file.
     * @var int
     */
    protected $size;


    /**
     * Create a new LocalFileSeeker object.
     *
     * @param string $path The path to the local file.
     * @throws NotSeekable Thrown if the opened file pointer is not seekable.
     */
    public function __construct(string $path) {
        $this->path = $path;
        try {
            $this->stream = fopen($path, "rb");
            if (!$this->stream) {
                throw new InvalidArgumentException("The provided file could not be opened");
            }
        }
        catch (Exception $e) {
            throw new InvalidArgumentException("The provided file could not be opened: " . $e->getMessage());
        }
        $this->size = fstat($this->stream)["size"];

        if (!stream_get_meta_data($this->stream)['seekable']) {
            throw new NotSeekable("The file passed to LocalFileSeeker must be seekable");
        }
    }

    /**
     * Destructor function to ensure the file descriptor is closed.
     */
    public function __destruct() {
        if($this->stream) {
            fclose($this->stream);
        }
    }

    /**
     * @inheritDoc
     */
    public function retrieveStart(int $length, int $offset = 0): ?string
    {
        // Bail out if the parameters are invalid
        if($length <= 0 || $offset < 0) {
            return null;
        }

        try {
            // Move to correct position
            $seek_success = fseek($this->stream, $offset, SEEK_SET);
            if ($seek_success === -1) {
                return null;
            }

            // Get the data
            $data = fread($this->stream, $length);
            if(!$data) {
                return null;
            }
            return $data;
        }
        catch (Exception $e) {
            user_error("Caught exception: " . $e->getMessage(), E_USER_WARNING);
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function retrieveEnd(int $length, int $offset = 0): ?string
    {
        if($length <= 0) {
            return null;
        }

        // If no offset is provided, we just want to return `$length` bytes, so that is the offset
        if($offset === 0) {
            $offset = $length;
        }
        // Make sure we can handle both positive and negative expressions of the offset, just in case.
        $offset = abs($offset);

        // Make sure we are not trying to seek past the start of the file.
        if($offset >  $this->size) {
            $offset = $this->size;
        }

        // Convert offset to negative for SEEK_END
        $offset = 0 - $offset;

        try {
            // Move to correct position
            $seek_success = fseek($this->stream, $offset, SEEK_END);
            if ($seek_success === -1) {
                return null;
            }

            // Get the data
            $data = fread($this->stream, $length);
            if (!$data) {
                return null;
            }
            return $data;
        }
        catch (Exception $e) {
            user_error("Caught exception: " . $e->getMessage(), E_USER_WARNING);
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function getStream(int $length, int $offset = 0, int $compression = 0)
    {
        // Open a temporary file for the data
        $fp = fopen("php://temp", "wb");
        if(!$fp) {
            return null;
        }

        // Determine if file is compressed with DEFLATE and set up the decompression filter if required
        switch($compression) {
            case 0:
                $deflate = false;
                break;
            case 8:
                stream_filter_append($fp, "zlib.inflate", STREAM_FILTER_READ);
                break;
            default:
                // Unsupported compression type
                trigger_error("Compression type " . $compression . " is unsupported by LocalFileSeeker", E_USER_WARNING);
                return null;
        }

        // Copy the data
        stream_copy_to_stream($this->stream, $fp, $length, $offset);

        // Seek to the start so the user doesn't have to
        fseek($fp, 0);

        return $fp;
    }

}