<?php

namespace Application;

class QueueJob extends ResourceModel
{
    public function add($organizationId,$url,$body="",$httpMethod='post',$timeoutSecs=60,$retries=10,$retryDelaySecs=30){

        $query = "
            INSERT INTO queued_job
            (organization_id,http_method,url,body,timeout_secs,remaining_retries,retry_delay_secs)
            VALUES
                (:organizationId,:httpMethod,:url,:body,:timeoutSecs,:remainingRetries,:retryDelaySecs)
        ";

        $queryParameters = array(
            ':organizationId' => $organizationId,
            ':httpMethod' => $httpMethod,
            ':url' => $url,
            ':body' => $body,
            ':timeoutSecs' => $timeoutSecs,
            ':remainingRetries' => $retries,
            ':retryDelaySecs' => $retryDelaySecs
        );

        $this->query($query,$queryParameters);
    }
}
