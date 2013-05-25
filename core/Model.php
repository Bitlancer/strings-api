<?php
namespace Application;

class Model {

	public $db;

    public function __construct(){

        global $app,$db;

        $this->app = $app;
        $this->db = $db;
    }

	public static function loadModel($model){

        if(class_exists("\\Application\\" . $model))
            return true;

        $modelFile = MODEL_DIR . DS . basename($model) . ".php";
        if(file_exists($modelFile)){
            require($modelFile);
        }
        else
            throw new ModelNotFoundException("Model $model not found"); 
    }

    public function instantiateModel($model){

        Model::loadModel($model);
        $modelClass = '\\Application\\' . $model;
        return new $modelClass();
    }
}
