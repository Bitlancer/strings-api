<?php

namespace SimpleRackspaceLib;

/**
 * A minimal library for interacting with Rackspace's API.
 *
 * A good majority of the code for this library was borrowed
 * from https://github.com/JCotton1123/simple-rackspace-cli.
 *
 */

if(!defined('DEBUG'))
    define('DEBUG', false);

class SimpleRackspaceClient {

    /**
     * Identity URL
     */
    private $identityUrl;

    /**
     * Username
     */
    private $username;

    /**
     * API Key
     */
    private $apiKey;

    /**
     * Service
     */
    private $service;

    /**
     * Region
     */
    private $region;

    /**
     * Service URL
     */
     private $serviceUrl;

    /**
     * Auth token
     */
    private $token = false;


    public function __construct($username, $apiKey, $identityUrl, $service, $region=DFW) {

        $this->username = $username;
        $this->apiKey = $apiKey;

        $this->identityUrl = $identityUrl;
        $this->service = $service;
        $this->region = $region;
    }

    public function request($endpoint, $method='GET', $data=false){

        if(empty($token)){
            $this->refreshTokenAndServiceCatelog();    
        }

        $url = $this->serviceUrl . $endpoint;
        $data = $data !== false ? json_encode($data) : false;
        $headers = $this->getHeaders();

        list($response,
            $httpStatus) = $this->curlRequest($url, $method, $data, $headers);

        $parsedResponse = json_decode($response, true);
        
        return array(
            $parsedResponse,
            $httpStatus
        );
    }

    private function refreshTokenAndServiceCatelog() {

        $authInfo = $this->getAuthInfo($this->identityUrl,
                                        $this->username,
                                        $this->apiKey);

        $this->token = $this->extractToken($authInfo);

        $this->serviceUrl = $this->extractServiceUrl($authInfo,
                                                    $this->service,
                                                    $this->region);
    }

    private function getHeaders(){

        $defaultHeaders = array(
            "Accept: application/json",
            "Content-Type: application/json",
            "Accept-Language: en-US,en"
        );

        $headers = $defaultHeaders;
        
        if(!empty($this->token))
            $headers[] = "X-Auth-Token: $this->token";

        return $headers;
    }

    private function extractServiceUrl($auth_info, $service, $region){
        $region = strtoupper($region);
        $valid_service_names = array();
        $service_catelog = $auth_info['access']['serviceCatalog'];
        foreach($service_catelog as $service_details){
            $service_name = $service_details['name'];
            $valid_service_names[] = $service_name;
            if($service == $service_name){
                foreach($service_details['endpoints'] as $endpoint){
                    if($endpoint['region'] == $region)
                        return $endpoint['publicURL'];
                }
            }
        }
        throw new \InvalidArgumentException("Invalid service or service region supplied.");
    }

    private function extractToken($auth_info){
        return $auth_info['access']['token']['id'];
    }

    private function getAuthInfo($identity_uri, $username, $apiKey){

        $token_uri = $identity_uri . "tokens";

        $auth_data = array(
            "auth" => array(
                "RAX-KSKEY:apiKeyCredentials" => array(
                    "username" => $username,
                    "apiKey" => $apiKey 
                )
            )
        );

        list($auth_info,$code) = $this->curlRequest(
            $token_uri,
            "POST",
            json_encode($auth_data),
            array(
                'Content-Type: application/json'
            )
        );
        
        if($code != 200)
            throw new \RuntimeException("Failed to authenticate, $auth_info");

        $auth_info = json_decode($auth_info, true);
        return $auth_info;
    }

    function curlRequest($url, $method='GET', $data=false, $headers=array()){

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if($data !== false)
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if(DEBUG)
            curl_setopt($ch, CURLOPT_VERBOSE, true);

        $output = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return array($output, $code);
    }

}
