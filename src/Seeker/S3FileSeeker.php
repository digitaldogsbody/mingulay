<?php

namespace Mingulay\Seeker;

use Exception;
use InvalidArgumentException;
use Mingulay\SeekerInterface;

class S3FileSeeker implements SeekerInterface {

  /**
   * The valid s3:// wrapper path to an S3 Object.
   *
   * @var string
   */
  protected $path;

  /**
   * The file pointer.
   *
   * @var false|resource
   */
  protected $stream;

  /**
   * The size of the file.
   *
   * @var int
   */
  protected $size;


  /**
   * Create a new LocalFileSeeker object.
   *
   * @param string $path The  streamwrapper prefixed path to an S3 Object.
   *
   * @throws InvalidArgumentException if the path is not accessible.
   */
  public function __construct(string $path) {
    $this->path = $path;
    try {
      $this->stream = fopen($this->path, "r", FALSE);
      if (!$this->stream) {
        throw new InvalidArgumentException(
          "The provided S3 Object could not be opened"
        );
      }
    } catch (Exception $e) {
      throw new InvalidArgumentException(
        "The provided S3 Object could not be opened: " . $e->getMessage()
      );
    }
    // We close it, that fopen was just to make sure it exists.
    fclose($this->stream);
    // Will fetch Content-length via a head request.
    $this->size = filesize($path);
  }

  /**
   * Destructor function to ensure the file descriptor is closed.
   */
  public function __destruct() {
    if ($this->stream) {
      fclose($this->stream);
    }
  }

  /**
   * @inheritDoc
   */
  public function retrieveStart(int $length, int $offset = 0): ?string {
    // Bail out if the parameters are invalid
    if ($length <= 0 || $offset < 0 || ($offset + $length) > $this->size) {
      return NULL;
    }

    try {
      $context = stream_context_create(
        [
          's3' => [
            'Range'    => "bytes=" . $offset . "-" . ($offset + $length - 1),
            'seekable' => FALSE,
          ],
        ]
      );

      $this->stream = fopen($this->path, "r", FALSE, $context);
      $data = fread($this->stream, $length);
      if (!$data) {
        return NULL;
      }
      return $data;
    } catch (Exception $e) {
      user_error("Caught exception: " . $e->getMessage(), E_USER_WARNING);
      return NULL;
    }
  }

  /**
   * @inheritDoc
   */
  public function retrieveEnd(int $length, int $offset = 0): ?string {
    if ($length <= 0 || ($offset + $length) > $this->size) {
      return NULL;
    }

    // Make sure we can handle both positive and negative expressions of the offset, just in case.
    $offset = abs($offset);

    // Make sure we are not trying to seek past the start of the file.
    if ($offset > $this->size) {
      $offset = $this->size;
    }

    try {
      $context = stream_context_create(
        [
          's3' => [
            'Range'    => "bytes=" . ((int)$this->size - $offset) - $length - 1
              . "-" . ((int) $this->size - $offset) - 1,
            'seekable' => FALSE,
          ],
        ]
      );

      $this->stream = fopen($this->path, "r", FALSE, $context);

      // Get the data
      $data = fread($this->stream, $length);
      if (!$data) {
        return NULL;
      }
      return $data;
    } catch (Exception $e) {
      user_error("Caught exception: " . $e->getMessage(), E_USER_WARNING);
      return NULL;
    }
  }

}