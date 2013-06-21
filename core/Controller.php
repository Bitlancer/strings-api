<?php

namespace Application;

class Controller {

	/**
	 * Method the request is being routed to
	 */
	protected $method = null;

	/**
	 * Parameters that will be passed to the called method
	 */
	protected $methodParameters = array();

	/**
	 * POST/PUT parameters
	 */
	protected $postParameters = array();

	/**
	 * Stores view data
	 */
	private $data = array();

    /**
     * Stores list of models this controller uses
     */
    protected $uses = array();


   	public function __construct(){

		global $app;
		$this->app = $app;

        foreach($this->uses as $model){
            $this->$model = Model::instantiateModel($model);
        }
   	}

	public function set($data){
		$this->data = array_merge($this->data,$data);
	}

	public function render(){
		
		$this->app->render(
			null,
			array(
				'data' => $this->data,
			)
		);
	}

    public function temporaryFailure($message='Temporary failure'){
        throw new TemporaryFailureException($message,503);
    }

	public function getPostParameters(){

        $request = $this->app->request();
        $contentType = $request->headers('Content-Type');
        $content = $request->getBody();

        switch($contentType){
            case 'application/json':
            default:
				$result = json_decode($content,true);
				if($result)
					return $result;
				else
					return array();
        }
    }

    public function getGetParameters(){

        $decodedParams = array();
        foreach($_GET as $k => $v)
            $decodedParams[$k] = urldecode($v);

        return $decodedParams;
    }

	public function getRequestParameters(){
		return array_merge($this->getGetParameters(),$this->getPostParameters());
	}

	public static function loadController($controller){

		$controllerClass = "\\Application\\" . basename($controller) . "Controller";
		$controllerFile = CONTROLLER_DIR . DS . $controller . "Controller.php";

		if(class_exists($controllerClass))
			return true;

        if(file_exists($controllerFile)){
        	require $controllerFile;
        }
		else
            throw new ControllerNotFoundException("Controller $controller not found");
	}

	public static function loadLibrary($library){

		//If library is a single file, load it
		$libraryFile = LIB_DIR . DS . $library . ".php";
		if(file_exists($libraryFile)){
			require_once($libraryFile);
			return;
		}

		//If library is a directory, load autoload file
		$libraryDir = LIB_DIR . DS . $library;
		$libraryAutoload = $libraryDir . DS . 'Autoload.php';
		if(file_exists($libraryDir) && is_dir($libraryDir) && file_exists($libraryAutoload)){
			require_once($libraryAutoload);
			return;
		}

		throw new ServerException("Failed to load library $library");
	}

    public function setHeaderValue($header,$value){

        $response = $this->app->response();
        $response[$header] = $value;
    }
}
