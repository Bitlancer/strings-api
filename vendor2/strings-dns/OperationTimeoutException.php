<?php

namespace StringsLoadBalancer;

class OperationTimeoutException extends RuntimeException
{
    public function __construct($message = 'Operation timed out'){
        parent::__construct($message);            
    }
}
