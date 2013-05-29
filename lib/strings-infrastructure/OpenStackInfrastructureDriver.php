<?php

namespace StringsInfrastructure;

require_once('InfrastructureDriver.php');
require(__DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'php-opencloud' . DIRECTORY_SEPARATOR . 'Autoload.php');

class OpenStackInfrastructureDriver extends InfrastructureDriver
{
	protected function parseConnectionParameters($connParams){
		
		$validConnectionDataStruct = array(
			'region' => '',
            'identityApiEndpoint' => '',
            'credentials' => array(
            	'username' => '',
               	'secret' => ''
            )
		);
		
		if(!self::validDataStructure($validConnectionDataStruct,$connParams))
			throw new \InvalidArgumentException('One or more required provider connection parameters is missing or is invalid.');
		
		$this->region = $connParams['region'];
		$this->identityAPIEndpoint = $connParams['identityApiEndpoint'];
		$this->connectionCredentials = array(
			'username' => $connParams['credentials']['username'],
			'password' => $connParams['credentials']['secret']
		);
	}
	
	protected function getProviderConnection(){
	
		$connection = new \OpenCloud\OpenStack($this->identityAPIEndpoint,$this->connectionCredentials);
		return $connection->Compute('cloudServersOpenStack');
	}

    protected function toGenericServerStatus($status){
        return strtolower($status);
    }
	
	public function createServer($name,$flavor,$image,$wait=false,$waitTimeout=600){

		$server = $this->connection->Server();
		$server->Create(array(
			'name' => $name,
			'image' => $this->connection->Image($image),
			'flavor' => $this->connection->Flavor($flavor)
		));

		if($wait){
			$server->WaitFor('ACTIVE',$waitTimeout);
            if($server->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }

		$server = array(
            'id' => $server->id,
            'rootPassword' => $server->adminPass
        );

		return $server;
	}
	
	public function resizeServer($serverID,$flavor,$wait=false,$waitTimeout=600){

		$server = $this->connection->Server($serverID);
		$flavor = $this->connection->Flavor($flavor);
		$server->Resize($flavor);

        if($wait){
            $server->WaitFor('ACTIVE',$waitTimeout);
            if($server->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }

	}

    public function confirmResizeServer($serverID,$wait=false,$waitTimeout=600){

        $server = $this->connection->Server($serverID);
        $server->ResizeConfirm();

        if($wait){
            $server->WaitFor('ACTIVE',$waitTimeout);
            if($server->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }
    }

    public function revertResizeServer($serverID,$wait=false,$waitTimeout=600){

        $server = $this->connection->Server($serverID);
        $server->ResizeRevert();

        if($wait){
            $server->WaitFor('ACTIVE',$waitTimeout);
            if($server->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }
    }

	public function rebuildServer($serverID,$flavorId=false,$imageId=false,$wait=false,$waitTimeout=600){

		$server = $this->connection->Server($serverID);

        if($flavorId === false)
            $flavorId = $server->flavor->id;
        if($imageId === false)
            $imageId = $server->image->id;

		$server->Rebuild(array(
            'name' => $server->name,
            'flavor' => $this->connection->Flavor($flavorId),
            'image' => $this->connection->Image($imageId)
        ));

        if($wait){
            $server->WaitFor('ACTIVE',$waitTimeout);
            if($server->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }
	}
	
	public function deleteServer($serverID,$wait=false,$waitTimeout=600){

		$server = $this->connection->Server($serverID);
		$server->Delete();

        if($wait){
            $server->WaitFor('DELETED',$waitTimeout);
            if($server->Status() != 'DELETED')
                throw new OperationTimeoutException();
        }
	}
	
	public function rebootServer($serverID,$wait=false,$waitTimeout=300){

		$server = $this->connection->Server($serverID);
		$server->Reboot();

        if($wait){
            $server->WaitFor('ACTIVE',$waitTimeout);
            if($server->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }
	}

	public function getServerStatus($serverID){

		$server = $this->connection->Server($serverID);
		return $this->toGenericServerStatus($server->status);
	}

    public function getServerPublicIPv4Address($serverID){
        return $this->getServerIP($serverID);
    }

    public function getServerPrivateIPv4Address($serverID){
        return $this->getServerIP($serverID,'private');
    }

    public function getServerPublicIPv6Address($serverID){
        return $this->getServerIP($serverID,'public',6);
    }

    public function getServerPrivateIPv6Address($serverID){
        throw new \Exception('Not supported');
    }

    private function getServerIP($serverID,$interfaceType='public',$addrType=4){

        $server = $this->connection->Server($serverID);
        $ips = $server->ips();
        $ips = $ips->$interfaceType;
        foreach($ips as $ip){
            if($ip->version == $addrType)
                return $ip->addr;
        }
        return null;
    }

    public function getServerIPs($serverID){

        $server = $this->connection->Server($serverID);

        $ips = array();

        $ipCollection = $server->ips();
        foreach($ipCollection as $network => $networkIPs){
            foreach($networkIPs as $ip){

                if(!isset($ips[$network]))
                    $ips[$network] = array();

                $ips[$network][$ip->version] = $ip->addr;
            }
        }

        return $ips;
    }

    public function getServers($filter=array()){

        $servers = array();

        $serverCollection = $this->connection->serverList(true,$filter);
        while($server = $serverCollection->Next()){
            $servers[] = array(
                'id' => $server->id,
                'name' => $server->name,
                'status' => $this->toGenericServerStatus($server->status)
            );
        }

        return $servers;
    }

	public function getImages(){

		$images = array();
	
		$imageCollection = $this->connection->imageList();
		while($image = $imageCollection->Next()){
			$images[] = array(
				'id' => $image->id,
				'name' => $image->name
			);
		}

		return $images;
	}

	public function getFlavors(){

		$flavors = array();
		
		$flavorCollection = $this->connection->flavorList();
		while($flavor = $flavorCollection->Next()){
			$flavors[] = array(
				'id' => $flavor->id,
				'name' => $flavor->name
			);
		}

		return $flavors;
	}

    public function getServerFlavor($serverID){

        $server = $this->connection->Server($serverID);
        $flavor = $server->flavor;
        return $flavor->id;
    }

}
