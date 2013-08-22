<?php

namespace Application;

/**
 * Define custom application exceptions
 *
 * All custom exceptions should be children of either ClientException (4xx),
 * ServerException (5xx), or one of their descedants.
 */

class UnexpectedProviderStatusException extends ServerException
{
    public function __construct($status){
        $message = "Unexpected provider status \"$status\" encountered";
        parent::__construct($message);
    }
}
