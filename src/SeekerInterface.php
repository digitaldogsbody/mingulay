<?php

namespace Mingulay;

/**
 * Defines an interface for retrieving arbitrary data from within an object.
 * Catching exceptions is the responsibility of the implementation, and in such cases, a NULL should be returned.
 */
interface SeekerInterface
{
    /**
     * Retrieve data from the start of the object, with an optional offset.
     * If no offset is supplied, then the first `$length` bytes should be returned (or up to $length if the object is smaller).
     * If an offset is supplied, then the first `$offset` bytes should be discarded, and _then_ `$length` bytes returned.
     *
     * @param int $length The number of bytes to be returned.
     * @param int $offset The offset from the start of the object.
     * @return string The bytestring of the data.
     */
    public function retrieveStart(int $length, int $offset = 0): ?string;

    /**
     * Retrieve data from the end of the object, with an optional offset.
     * If no offset is supplied, then the last `$length` bytes should be returned (or up to $length if the object is smaller).
     * If an offset is supplied, then `$length` bytes should be returned, starting at `$offset` bytes from the end of the object (or the start of the object if the object is smaller).
     *
     * @param int $length The number of bytes to be returned.
     * @param int $offset The offset counting backwards from the end of the object.
     * @return string The bytestring of the data.
     */
    public function retrieveEnd(int $length, int $offset = 0): ?string;
}