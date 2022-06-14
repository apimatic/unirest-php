<?php

namespace Unirest;

class RequestParent extends Request
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
