<?php


namespace Application;

class RemoteExecutionController extends ResourcesController
{

    protected $uses = array('Script','Device','JumpServer');

    public function __construct(){

        parent::__construct();
    }

    public function run($scriptId){

        //Get the script
        $script = $this->Script->get($scriptId);
        if(empty($script))
            throw new NotFoundException('Script does not exist.');
        $scriptType = $script['script.type'];
        $scriptUrl = $script['script.url'];
        $scriptPath = $script['script.path'];

        //Get the collection of devices associated with this script
        $devices = $this->getAssociatedDevices($script);
        if(empty($devices))
            return;

        //Turn the devices into a delimited string that will
        //be passed to the script
        //$devicesParameter = $this->devicesParameterString($devices);

        //Select an appropriate jump server to use for this collection of
        //devices.
        $jumpServerInfo = $this->getJumpServerInfo($devices);
        $jumpServerFQDN = $jumpServerInfo['fqdn'];
        $jumpServerPrivateKey = $jumpServerInfo['privateKey'];

        //Write the the private key to a temporary file
        $privateKeyFile = tempnam(REMOTE_EXECUTION_PRIVATE_KEY_DIR,'key_');
        file_put_contents($privateKeyFile,$jumpServerPrivateKey);

        try {

            $defaultSshOptions = "-o StrictHostKeyChecking=no -q";

            $sshCmd = "ssh remoteexec@$jumpServerFQDN";
            $sshCmd .= " -i $privateKeyFile";
            $sshCmd .= " -o StrictHostKeyChecking=no";
            $sshCmd .= " -q 2>&1";

            //Call the pre-execution script
            $remoteCmd = "~/deploy/pre-exec.sh";
            $remoteCmd .= " -t '$scriptType'";
            $remoteCmd .= " -u '$scriptUrl'";
            $remoteCmd .= " -p '$scriptPath'";
            list($status,$output) = $this->execRemoteCmdViaSsh(
                $jumpServerFQDN,
                REMOTE_EXECUTION_USER,
                $privateKeyFile,
                $defaultSshOptions,
                $remoteCmd);

            //Call the deploy script
            //ToDo

            //Call the post-execution script
            $remoteCmd = "~/deploy/post-exec.sh";
            list($status,$output) = $this->execRemoteCmdViaSsh(
                $jumpServerFQDN,
                REMOTE_EXECUTION_USER,
                $privateKeyFile,
                $defaultSshOptions,
                $remoteCmd);
  
        }
        catch(Exception $e) {
            unlink($privateKeyFile);
            throw $e;
        }
        @unlink($privateKeyFile);
    }

    private function execRemoteCmdViaSsh($host,$user,$privateKeyFile,$options,$remoteCmd){

        $remoteCmd = escapeshellarg($remoteCmd);
        $sshCmd = "ssh $user@$host -i $privateKeyFile $options $remoteCmd 2>&1";
        exec($sshCmd,$output,$status);

        return array($status,$output);
    }

    /**
     * Get the list of the devices this script is associated with
     */
    private function getAssociatedDevices($script){

        $scriptModel = $script['script.model'];
        $modelId = $script['script.foreign_key_id'];

        $devices = array();
        
        switch($scriptModel){
            case 'Application':
                $devices = $this->Device->getByApplicationIdWithProfiles($modelId);
                break;
            case 'Formation':
                $devices = $this->Device->getByFormationIdWithProfiles($modelId);
                break;
            default:
                throw new ServerException("Unexpected model association $scriptModel.");
        }

        return $devices;
    }

    /**
     * Return the appropriate jump server to use for a collection of devices
     *
     * For now, it selects the jump server associated with the region for the
     * first device.
     */
    private function getJumpServerInfo($devices){

        $device = array_pop($devices);
        $implementationId = $device['device.implementation_id'];
        $regionId = $this->Device->getAttribute(
            $device['device.id'],
            'implementation.region_id');

        $jumpServer = $this->JumpServer->getByImplementationAndRegion(
            $implementationId,
            $regionId);

        if(empty($jumpServer))
            throw new ServerException("Could not determine what jump server " .
                                      "to use for this execution."); 

        $jumpServerDeviceId = $jumpServer['jump_server.device_id'];

        //Get jump server internal FQDN
        $jumpServerFQDN = $this->Device->getAttribute(
            $jumpServerDeviceId,
            'dns.internal.fqdn');
            
        if(empty($jumpServerFQDN))
            throw new ServerException("Attribute dns.internal.fqdn has not " .
                                      "been defined for device $jumpServerDeviceId");

        return array(
            'fqdn' => $jumpServerFQDN,
            'privateKey' => $jumpServer['jump_server.private_key']
        );
    }
}
