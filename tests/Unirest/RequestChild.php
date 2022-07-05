<?php

namespace Unirest;

class RequestChild extends Request
{
    public function getTotalNumberOfConnections()
    {
        return $this->totalNumberOfConnections;
    }

    public function resetHandle()
    {
        $this->initializeHandle();
    }
}
