<?php

namespace APIFramework;

class Router {

	public static function registerRoute($httpMethods,$uri,$controller,$action,$conditions=array()){
		global $app;

        if(!is_array($httpMethods))
            $httpMethods = array($httpMethods);

        foreach($httpMethods as $httpMethod){
            $app->map($uri, function() use ($controller,$action){
			    Router::callController($controller,$action,func_get_args());
            })->via($httpMethod)->conditions($conditions);
        }
	}

    public static function callController($controller,$method,$methodParameters=array()){

        //Load controller
        \Application\Controller::loadController($controller);

        //Instantiate controller
        $controllerClass = "\\Application\\" . basename($controller) . 'Controller';
        $controllerInstance = new $controllerClass;

        //Check if method exists
        if(!method_exists($controllerInstance,$method)){
            throw new \Application\NotFoundException('This controller method has not been defined');
        }
        else
            call_user_func_array(array($controllerInstance,$method),$methodParameters);

        $controllerInstance->render();
    }
}
