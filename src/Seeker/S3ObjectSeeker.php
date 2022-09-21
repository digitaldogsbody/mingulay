<?php

namespace Mingulay\Seeker;

use Exception;
use InvalidArgumentException;
use Mingulay\SeekerInterface;

class S3ObjectSeeker implements SeekerInterface {

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
   * Create a new S3ObjectSeeker object.
   *
   * @param string $path The  streamwrapper prefixed path to an S3 Object.
   *
   * @throws InvalidArgumentException if the path is not accessible.
   */
  public function __construct(string $path) {
    $this->path = $path;
    try {
      $this->size = filesize($path);
      if ($this->size === FALSE) {
        throw new InvalidArgumentException(
          "The provided S3 Object could not be opened"
        );
      }
    } catch (Exception $e) {
      throw new InvalidArgumentException(
        "The provided S3 Object could not be opened: " . $e->getMessage()
      );
    }
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
      $data = '';
      while (!feof( $this->stream)) {
        $data .= fread($this->stream, 8192);
      }
      if (!$data or strlen($data)!= $length) {
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
      $to = ($this->size - $offset - 1);
      $from = $to - $length + 1;
      $context = stream_context_create(
        [
          's3' => [
            'Range'    => "bytes=" . $from
              . "-" . $to,
            'seekable' => FALSE,
          ],
        ]
      );
      $data = '';
      $this->stream = fopen($this->path, "r", FALSE, $context);
      while (!feof( $this->stream)) {
        $data .= fread($this->stream, 8192);
      }
      // Be sure to close the stream resource when you're done with it
      if (!$data || strlen($data)!= $length) {
        return NULL;
      }
      return $data;
    } catch (Exception $e) {
      user_error("Caught exception: " . $e->getMessage(), E_USER_WARNING);
      return NULL;
    }
  }
}