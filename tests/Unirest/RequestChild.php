<?php

namespace Unirest;

class RequestChild extends Request
{
    public static function getTotalNumberOfConnections()
    {
        return parent::$totalNumberOfConnections;
    }

    public static function resetHandle()
    {
        parent::initializeHandle();
    }
}
