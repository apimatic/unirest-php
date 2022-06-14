<?php

namespace Unirest;

class RequestChild extends Request
{
    public static function getPrevCallsSuccessfulConnects()
    {
        return parent::$prevCallsSuccessfulConnects;
    }

    public static function resetHandleAndPrevConnects()
    {
        parent::forceReInitializeHandle();
        parent::$prevCallsSuccessfulConnects = 0;
    }
}
