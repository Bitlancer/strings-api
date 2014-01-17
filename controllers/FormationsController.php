<?php

namespace Application;

class FormationsController extends ResourcesController
{

    protected $uses = array('Formation','Device','Implementation','QueueJob');

    public function __construct(){

        parent::__construct();
    }

    public function create($formationId){

        $this->createOrDeleteFormation($formationId,true);
    }

    public function delete($formationId){

        $this->createOrDeleteFormation($formationId,false);
    }

    public function createOrDeleteFormation($formationId,$create){

        if(!$this->Formation->exists($formationId))
            throw new NotFoundException('Formation does not exist.');

        $getParams = $this->getGetParameters();
        if(!isset($getParams['state'])){

            $this->createOrDeleteFormationDevices($formationId,$create);

            $this->setHeaderValue('x-bitlancer-url',REQUEST_URL . '?state=waitingForDevices');
            $this->temporaryFailure('Waiting for one or more devices to complete its operation.');
        }
        else {

            $stillPending = false;
            $errorEncountered = false;

            if($create){

                //Check each devices status
                $devices = $this->Device->getByFormationId($formationId);
                foreach($devices as $device){
                    $status = $device['device.status'];
                    if($status == 'error'){
                        $errorEncountered = true;
                        break;
                    }
                    if($status == 'building'){
                        $stillPending = true;
                        break;
                    }
                }
            }
            else { //Delete

                //Check each devices status
                $devices = $this->Device->getByFormationId($formationId);
                if(count($devices)){
                    $stillPending = true;
                    foreach($devices as $device){
                        $status = $device['device.status'];
                        if($status == 'error'){
                            $errorEncountered = true;
                            break;
                        }
                    } 
                }
            }

            if($errorEncountered){
                $this->Formation->updateStatus($formationId,'error');
                throw new ServerException('One or more device operations failed.');
            }
            elseif($stillPending){
                $this->temporaryFailure("Waiting on device operations.");
            }
            else {
                if($create){
                    $this->Formation->updateStatus($formationId,'active');
                }
                else {
                    $this->Formation->delete($formationId);
                }
            }
        }
    }

    /**
     * Create or delete all of a formations devices
     * 
     * @param $formationId int The id of the formation
     * @param $create boolean Whether this is a create operation
     */
    protected function createOrDeleteFormationDevices($formationId,$create){

       //Create Q jobs for each device
        $devices = $this->Device->getByFormationId($formationId);

        foreach($devices as $device){

            $deviceId = $device['device.id'];
            $organizationId =$device['device.organization_id'];
            $deviceType = $device['device_type.name'];

            $jobUrl = SITE_URL;
            if($deviceType == 'instance'){
                if($create)
                    $jobUrl .= "/Instances/create/$deviceId";
                else
                    $jobUrl .= "/Instances/delete/$deviceId";
            }
            elseif($deviceType == 'load-balancer'){
                if($create)
                    $jobUrl .= "/LoadBalancers/create/$deviceId";
                else
                    $jobUrl .= "/LoadBalancers/delete/$deviceId";
            }
            else {
                $this->Formation->updateStatus($formationId,'error');
                throw new ServerException("Encountered an unknown device type $deviceType.");
            }

            $this->QueueJob->add($organizationId,$jobUrl,'','post',90,40,30);
        }
    }
}
