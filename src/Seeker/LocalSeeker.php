<?php

namespace Mingulay\Seeker;

use Exception;
use InvalidArgumentException;
use Mingulay\Exception\NotSeekable;
use Mingulay\SeekerInterface;

class LocalSeeker implements SeekerInterface
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
     * Create a new LocalSeeker object.
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
            throw new NotSeekable("The file passed to LocalSeeker must be seekable");
        }
    }

    /**
     * Destructor function to ensure the file descriptor is closed.
     */
    public function __destruct() {
        fclose($this->stream);
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
            // Move to correct position if necessary
            if($offset > 0) {
                $seek_success = fseek($this->stream, $offset, SEEK_SET);
                if ($seek_success === -1) {
                    return null;
                }
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
}