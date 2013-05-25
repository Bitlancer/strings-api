<?php

namespace APIFramework;

class ViewHandler extends \Slim\View {

	public function render($template){

		global $app;

		$slimResponse = $app->response();

		$statusCode = $slimResponse->status();
		$data = $this->data['data'];
		$errorMessage = (array_key_exists('errorMessage',$this->data) ? $this->data['errorMessage'] : '');
		

		//Wrap response
        $response = array(
			'meta' => array(
				'code' => $statusCode,
				'errorMessage' => $errorMessage
			),
            'data' => $data
        );

		//Decide how to encode response based on request ACCEPT header
		$acceptHeader = $app->request()->headers('ACCEPT');

        $jsonIndex = strpos($acceptHeader,"application/json");
        $textIndex = strpos($acceptHeader,"text/html");
        $xmlIndex = strpos($acceptHeader,"application/xml");
	
		//Set content type and encode content
		if($textIndex !== false){
			$slimResponse['Content-Type'] = 'text';
			$encodedResponse = print_r($response,true);
			//$encodedResponse = json_encode($response,JSON_PRETTY_PRINT);
		}
		else {
			$slimResponse['Content-Type'] = 'application/json';
			$encodedResponse = json_encode($response);
		}

        //Ouput
		return $encodedResponse;
	}
}
