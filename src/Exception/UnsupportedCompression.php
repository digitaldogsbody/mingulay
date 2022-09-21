<?php

namespace Mingulay\Exception;

/**
 * This Exception gets thrown if the file is compressed with something other than DEFLATE.
 */
class UnsupportedCompression extends \Exception
{
}