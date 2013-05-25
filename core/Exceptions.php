<?php

namespace Application;

class UnexpectedProviderStatusException extends ServerException
{
    public function __construct($status){
        $message = "Unexpected provider status \"$status\" encountered";
        parent::__construct($message);
    }
}

class ControllerNotFoundException extends ServerException {}

class ModelNotFoundException extends ServerException {}

class InvalidArgumentException extends ClientException {}

class TemporaryFailureException extends ServerException
{
    public function __construct($message = 'Temporary failure'){
        parent::__construct($message, 503, null);
    }
}

class NotFoundException extends ClientException
{
	public function __construct($message = 'Resource not found') {
        parent::__construct($message, 404, null);
    }
}

class ClientException extends \RuntimeException
{
	public function __construct($message, $code = 400, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}

class ServerException extends \RuntimeException
{
	public function __construct($message, $code = 500, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}

