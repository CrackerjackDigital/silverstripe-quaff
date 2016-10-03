<?php
namespace Quaff\Exceptions;
/**
 * Throw this in a transport if e.g. underlying library is not available in construtor or if another transport may succeed where this one failed.
 *
 * @package Quaff\Exceptions
 */
class BadTransport extends Exception {

}