<?php

namespace APIFramework;

/**
 * Set site host, scheme and url
 */
define('SITE_HOST', $_SERVER['HTTP_HOST']);
if(array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS'] == 'on')
	define('SCHEME','https://');
else
	define('SCHEME','http://');
define('SITE_URL',SCHEME . SITE_HOST);
define('REQUEST_URL',SITE_URL . $_SERVER['REQUEST_URI']);


/**
 * Application Wide Constants
 */
define('DS',DIRECTORY_SEPARATOR);
define('ROOT_DIR', __DIR__ . DS . "..");
define('LIB_DIR', ROOT_DIR . DS . 'lib');
define('CORE_DIR', ROOT_DIR . DS . 'core');
define('CONFIG_DIR', ROOT_DIR . DS . 'config');
define('CONTROLLER_DIR', ROOT_DIR . DS . 'controllers');
define('MODEL_DIR', ROOT_DIR . DS . 'models');
define('LOG_FILE', ROOT_DIR . DS . 'logs' . DS . 'error_log');


/**
 * Load essential libraries
 */
//Load Slim
require_once(LIB_DIR . DS . "Slim" . DS . "Slim.php");
\Slim\Slim::registerAutoloader();


/**
 * Load framework core
 */
require CORE_DIR . DS . 'Exceptions.php';
require CORE_DIR . DS . 'Controller.php';
require CORE_DIR . DS . 'Model.php';
require CORE_DIR . DS . 'Router.php';
require CORE_DIR . DS . 'AuthHandler.php';
require CORE_DIR . DS . 'AuthMiddleware.php';
require CORE_DIR . DS . 'ViewHandler.php';
require CORE_DIR . DS . 'DatabaseHandler.php';


/**
 * Load configurations
 */
require CONFIG_DIR . DS . 'Exceptions.php';


/**
 * Instantiate slim config
 */
$app = new \Slim\Slim(
    array(
        'debug' => false,
        'MODE' => getenv('CODE_ENVIRONMENT'),
        'log.enabled' => true,
        'log.level' => \Slim\Log::DEBUG,
        'slim.errors' => 'php://stderr',
        'view' => new ViewHandler()
    )
);


/**
 * Load Database config and create DB conn
 */
require CONFIG_DIR . DS . 'Database.php';
$dbConfig = new DatabaseConfig();
$db = null;
try {
	$db = new DatabaseHandler(
		$dbConfig::datasource,
		$dbConfig::host,
		$dbConfig::database,
		$dbConfig::login,
		$dbConfig::password,
		$dbConfig::charset
	);
}
catch(\PDOException $e){
	die('Failed to connect to database');
}


/**
 * Load auth config and instantiate auth middleware
 */
require CONFIG_DIR . DS . 'Authentication.php';
$app->add(new AuthMiddleware(array('\APIFramework\AuthHandler','authenticate')));


/**
 * Register error handlers
 */
$app->error(function(\Exception $e) use ($app) {

    $statusCode = '500';
	if($e instanceof \Application\ClientException || $e instanceof \Application\ServerException){
		$statusCode = $e->getCode();
		$errorMessage = $e->getMessage();
	}
	else {
        $error = array(
            'exception' => get_class($e),
            'file_line' => basename($e->getFile()) . "(" . $e->getLine() . ")",
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        );

		$errorMessage = json_encode($error);
	}

	$app->render(
		null,
		array(
			'errorMessage' => $errorMessage,
			'data' => array()
		),
		$statusCode
	);
});
$app->notFound(function() use ($app) {
    //Enforce consistency - this should be caught be the error method above
    throw new \Application\NotFoundException();
});


/**
 * Load routes
 */
require CONFIG_DIR . DS . 'Routes.php';


/**
 * Load parent controller and model classes
 */
require MODEL_DIR . DS . 'ResourceModel.php';
require CONTROLLER_DIR . DS . 'ResourcesController.php';



/**
 * Run Slim
 */
$app->run();

?>
