<?php

namespace APIFramework;

class AuthMiddleware extends \Slim\Middleware
{
	/**
	 * Callback function used to authenticate the user
	 */
	private $authCallback;

	public function __construct($authCallback){

		if(!is_callable($authCallback))
			throw new \InvalidArgumentException('The supplied callback is not a valid callable function');

		$this->authCallback = $authCallback;
	}
	
	/**
	 * Middle callback function executed by Slim
	 */
    public function call(){

		$req = $this->app->request();

		$authUser = $req->headers('PHP_AUTH_USER');
        $authPass = $req->headers('PHP_AUTH_PW');

		if(call_user_func($this->authCallback,$authUser,$authPass))
			$this->next->call();
		else
			$this->unauthorized("Basic realm=\"Authentication Required\"");
	}

    /**
     * 401 - Unauthorized
     *
     * 401 is suppose to set the WWW-Authenticate header to inform the client of what kind of authentication
     * is required.
     *
     * @param string $authHeader WWW-Authenticate header value
     * @param string $errorMessage Error message to display if the user cancels auth
     * @param string $optionalHeader = additional headers that will be added to the response
     */
    private function unauthorized($authHeader,$errorMessage='Please login to access this resource',$optionalHeaders=array()){

        $res = $this->app->response();
        $res->status(401);

        $res->header('WWW-Authenticate',$authHeader);

        foreach($optionalHeaders as $headerKey => $headerValue){
            $res->header($headerKey,$headerValue);
        }

        $this->app->render(null,array(
            'data' => array(),
            'errorMessage' => $errorMessage
        ));
    }
}

