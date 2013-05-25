<?php

namespace Application;

class InstancesController extends ResourcesController
{

    protected $uses = array('Device','Implementation');

	public function __construct(){

		parent::__construct();
		self::loadLibrary('strings-infrastructure');
	}

	public function create($deviceId){

		if(empty($deviceId))
			throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId); 
        $deviceAttrs = $device['device_attribute'];

        if(isset($deviceAttrs['implementation.status']) && $deviceAttrs['implementation.status'] == 'active')
            throw new ClientException('This device is already running');

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_name']);

        //Check the device's status to determine what we should do
        if(!isset($deviceAttrs['implementation.id'])){

            $providerDevice = $providerDriver->createServer(
                $device['device.name'],
                $deviceAttrs['implementation.flavor_id'],
                $deviceAttrs['implementation.image_id']);

            $providerDeviceId = $providerDevice['id'];

            $this->Device->saveAttribute($device,'implementation.id',$providerDeviceId);

            $newDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
            $this->Device->updateImplementationStatus($device,$newDeviceStatus);

            $this->temporaryFailure("Waiting for device build to complete");
        }
        else {

            $providerDeviceId = $deviceAttrs['implementation.id'];

            $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);

            if($liveDeviceStatus == 'build'){
                $this->temporaryFailure("Waiting for device to spin up");
            }
            elseif($liveDeviceStatus == 'active'){

                $publicIP = $providerDriver->getServerPublicIPv4Address($providerDeviceId);
                $this->Device->saveAttribute($device,'implementation.public_ipv4',$publicIP);

                $privateIP = $providerDriver->getServerPrivateIPv4Address($providerDeviceId);
                $this->Device->saveAttribute($device,'implementation.private_ipv4',$privateIP);

                $this->addDeviceARecord($device['device.id']);

                $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
            }
            else {
                $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
                throw new UnexpectedProviderStatusException($liveDeviceStatus);
            }
        }
	}

	public function resize($deviceId,$flavorId){

       if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];
        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_name']);

        $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);

        if($liveDeviceStatus == 'active'){
            $providerDriver->resizeServer($providerDeviceId,$flavorId);

            $this->Device->saveAttribute($device,'implementation.flavor_id',$flavorId);

            $newDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
            $this->Device->updateImplementationStatus($device,$newDeviceStatus); 
        }
        else {
            $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
            throw new UnexpectedProviderStatusException($liveDeviceStatus);
        }
	}

    public function confirmResize($deviceId,$confirm){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $confirm = ($confirm == 'false' || $confirm == '0' ? false : true);

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];
        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_name']);

        $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);

        if($liveDeviceStatus == 'verify_resize'){
            if($confirm)
                $providerDriver->confirmResizeServer($providerDeviceId,true);
            else {
                $providerDriver->revertResizeServer($providerDeviceId);
                $flavorId = $providerDriver->getServerFlavor($providerDeviceId);
                $this->Device->saveAttribute($device,'implementation.flavor_id',$flavorId);
            }

            $newDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
            $this->Device->updateImplementationStatus($device,$newDeviceStatus);
        }
        else {
            $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
            throw new UnexpectedProviderStatusException($liveDeviceStatus);
        }
    }

	public function rebuild($deviceId){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];
        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_name']);

        $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);

        if($liveDeviceStatus == 'active'){

            $providerDriver->rebuildServer($providerDeviceId,false,false);

            $newDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
            $this->Device->updateImplementationStatus($device,$newDeviceStatus);
        }
        else {
            $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
            throw new UnexpectedProviderStatusException($liveDeviceStatus);
        }
	}

	public function delete($deviceId){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
           throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];
        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_name']);

        $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);

        $this->removeDeviceARecord($deviceId);
        $providerDriver->deleteServer($deviceAttrs['implementation.id']);
        $this->Device->delete($deviceId);
	}

	public function reboot($deviceId){

         if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];
        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_name']);

        $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);

        if($liveDeviceStatus == 'active'){

            $providerDriver->rebootServer($providerDeviceId);

            $newDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
            $this->Device->updateImplementationStatus($device,$newDeviceStatus);
        }
        else {
            $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
            throw new UnexpectedProviderStatusException($liveDeviceStatus);
        }
	}

	public function getStatus($deviceId){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');
    
        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];

        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_name']);

        $providerStatus = $providerDriver->getServerStatus($providerDeviceId);

        $this->Device->updateImplementationStatus($device,$providerStatus);

       $this->set(array(
            'status' => $providerStatus
        )); 
	}

    public function updateServerStatuses($implementationId,$region){
        
        if(empty($implementationId))
            throw new InvalidArgumentException('Implementation id is required');

        if(empty($region))
            throw new InvalidArgumentException('Region is required');

        $providerDriver = $this->getServers

    }

    private function addDeviceARecord($deviceId){

        $this->loadController('Dns');
        $dnsController = new DnsController();
        $dnsController->addDeviceARecord($deviceId);
    }

    private function removeDeviceARecord($deviceId){

        $this->loadController('Dns');
        $dnsController = new DnsController();
        $dnsController->removeDeviceARecord($deviceId);
    }

    protected function getProviderDriver($implementationId,$region){

        $implementation = $this->Implementation->get($implementationId);
        $implementationAttrs = $implementation['implementation_attribute'];

        $infrastructureProvider = strtolower($implementation['provider.name']);

        $providerDriver = null;
        switch($infrastructureProvider){
            case 'rackspace':
                $connParameters = array(
                    'credentials' => array(
                        'username' => $implementationAttrs['username'],
                        'secret' => $implementationAttrs['api_key']
                    ),
                    'region' => $region,
                    'identityApiEndpoint' => $implementationAttrs['identity_api_endpoint']
                );
                $providerDriver = new \StringsInfrastructure\RackspaceInfrastructureDriver($connParameters);
                break;
            case 'openstack':
                throw new ServerException('Not implemented');
                $connParameters = array();
                $providerDriver = new \StringsInfrastructure\OpenStackInfrastructureDriver($connParameters);
                break;
            default:
                throw new InvalidArgumentException('Unrecognized provider');
        }

        return $providerDriver;
    }
}
