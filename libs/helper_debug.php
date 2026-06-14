<?php

trait HelperDebug
{
    /**
     * SendDebug (extend "SendDebug" by further output options - array, bool)
     *
     * @param string $Title
     * @param mixed $Message
     * @param int $Format
     */
    protected function SendDebug($Title, $Message, $Format)
    {
        if (is_object($Message)) {
            foreach ($Message as $Key => $DebugData) {
                $this->SendDebug($Title . ':' . $Key, $DebugData, 0);
            }
        } elseif (is_array($Message)) {
            foreach ($Message as $Key => $DebugData) {
                $this->SendDebug($Title . ':' . $Key, $DebugData, 0);
            }
        } elseif (is_bool($Message)) {
            parent::SendDebug($Title, ($Message ? 'TRUE' : 'FALSE'), 0);
        } else {
            if (IPS_GetKernelRunlevel() === KR_READY) {
                parent::SendDebug($Title, (string)$Message, (int)$Format);
            } else {
                IPS_LogMessage('DEBUG:' . $Title, (string)$Message);
            }
        }
    }
}

?>