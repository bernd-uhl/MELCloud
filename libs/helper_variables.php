<?php /** @noinspection NestedPositiveIfStatementsInspection */
/** @noinspection ForeachSourceInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection SpellCheckingInspection */
/** @noinspection PhpRedundantClosingTagInspection */

/** @noinspection AutoloadingIssuesInspection */


trait HelperVariables
{
	/**
     * RegisterObjectMedia (creating a boolean variable profile with given parameters)
     *
     * @param $Name
	 * @param $Ident
     * @param $Type
     * @param $Parent
     * @param $Cached
     * @param $Filename
     * @param $Position
     * @return bool
     */
	protected function RegisterObjectMedia($Name, $Ident, $Type, $Parent, $Cached, $Filename, $Position = 0)
    {
        $ObjectID = @$this->GetIDForIdent($Ident);
        if ($ObjectID === false) {
            $ObjectID = IPS_CreateMedia($Type);
            IPS_SetParent($ObjectID, $Parent);
            IPS_SetIdent($ObjectID, $Ident);
            IPS_SetName($ObjectID, $Name);
            IPS_SetPosition($ObjectID, $Position);
            IPS_SetMediaCached($ObjectID, $Cached);
            $ImageFile = IPS_GetKernelDir() . 'media' . DIRECTORY_SEPARATOR . $Filename;
            return IPS_SetMediaFile($ObjectID, $ImageFile, false);
        }

        return true;
    }


    /**
     * RegisterProfileBoolean (creating a boolean variable profile with given parameters)
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $StepSize
     * @return bool
     */
    protected function RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 0);
        } else {
            $ProfileInfo = IPS_GetVariableProfile($Name);
            if ($ProfileInfo['ProfileType'] !== 0) {
                $this->SendDebug(__FUNCTION__, 'Type of variable does not match the variable profile "' . $Name . '"', 0);
                return false;
            }
        }
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);

        return true;
    }


    /**
     * RegisterProfileBooleanEx (creating a boolean variable profile with given parameters and extra associations)
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $Associations
     */
    protected function RegisterProfileBooleanEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }

        $this->RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }

    }


    /**
     * RegisterProfileInteger (creating a integer variable profile with given parameters)
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $StepSize
     * @return bool
     */
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] !== 1) {
                $this->SendDebug(__FUNCTION__, 'Type of variable does not match the variable profile "' . $Name . '"', 0);
                return false;
            }
        }

        if ($StepSize > 0) {
            IPS_SetVariableProfileDigits($Name, 1);
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);

        return true;
    }


    /**
     * RegisterProfileIntegerEx (creating a integer variable profile with given parameters and extra associations)
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $Associations
     */
    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }

        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }

    }


    /**
     * RegisterProfileFloat (creating a float variable profile with given parameters)
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $StepSize
     * @return bool
     */
    protected function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 2);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] !== 2) {
                $this->SendDebug(__FUNCTION__, 'Type of variable does not match the variable profile "' . $Name . '"', 0);
                return false;
            }
        }

        if ($StepSize > 0) {
            IPS_SetVariableProfileDigits($Name, 1);
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);

        return true;
    }


    /**
     * RegisterProfileFloatEx (creating a integer variable profile with given parameters and extra associations)
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $Associations
     */
    protected function RegisterProfileFloatEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }

        $this->RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }

    }


    /**
     * RegisterProfileString (creating a string variable profile with given parameters)
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $StepSize
     * @return bool
     */
    protected function RegisterProfileString($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {

        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 3);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] !== 3) {
                $this->SendDebug(__FUNCTION__, 'Type of variable does not match the variable profile "' . $Name . '"', 0);
                return false;
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);

        return true;
    }


    /**
     * RegisterProfileStringEx (creating a string variable profile with given parameters and extra associations)
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $Associations
     */
    protected function RegisterProfileStringEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }

        $this->RegisterProfileString($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }

    }


    /**
     * SetValue (set variable to new value, no matter whether the new value is the same or different)
     *
     * @param string $Ident
     * @param $Value
     * @return bool
     */
    protected function SetValue($Ident, $Value)
    {
        $VarID = @$this->GetIDForIdent($Ident);

        if (IPS_GetKernelVersion() >= 5) {
            if ($VarID > 0) {
                switch (IPS_GetVariable($VarID)['VariableType']) {
                    case 0:
                        parent::SetValue($Ident, (bool)$Value);
                        break;

                    case 1:
                        parent::SetValue($Ident, (int)$Value);
                        break;

                    case 2:
                        parent::SetValue($Ident, (float)$Value);
                        break;

                    case 3:
                        parent::SetValue($Ident, (string)$Value);
                        break;
                }
                return true;
            }
        } else {
            if ($VarID > 0) {
                switch (IPS_GetVariable($VarID)['VariableType']) {
                    case 0:
                        SetValue($VarID, (bool)$Value);
                        break;

                    case 1:
                        SetValue($VarID, (int)$Value);
                        break;

                    case 2:
                        SetValue($VarID, (float)$Value);
                        break;

                    case 3:
                        SetValue($VarID, (string)$Value);
                        break;
                }
                return true;
            }
        }

        return false;
    }


    /**
     * SetValue_IfDifferent (set variable to new value, if the value is different)
     *
     * @param string $Ident
     * @param $Value
     * @return bool
     */
    private function SetValue_IfDifferent($Ident, $Value)
    {
        $VarID = @$this->GetIDForIdent($Ident);

        if (IPS_GetKernelVersion() >= 5) {
            if ($VarID > 0) {
                if (parent::GetValue($Ident) != $Value) {
                    switch (IPS_GetVariable($VarID)['VariableType']) {
                        case 0:
                            parent::SetValue($Ident, (bool)$Value);
                            break;

                        case 1:
                            parent::SetValue($Ident, (int)$Value);
                            break;

                        case 2:
                            parent::SetValue($Ident, (float)$Value);
                            break;

                        case 3:
                            parent::SetValue($Ident, (string)$Value);
                            break;
                    }
                    return true;
                }
            }
        } else {
            if ($VarID > 0) {
                if (GetValue($VarID) != $Value) {
                    switch (IPS_GetVariable($VarID)['VariableType']) {
                        case 0:
                            SetValue($VarID, (bool)$Value);
                            break;

                        case 1:
                            SetValue($VarID, (int)$Value);
                            break;

                        case 2:
                            SetValue($VarID, (float)$Value);
                            break;

                        case 3:
                            SetValue($VarID, (string)$Value);
                            break;
                    }
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * Variable_Register (register and create variable with some parameters)
     *
     * @param $VarIdent
     * @param $VarName
     * @param $VarProfile
     * @param $VarIcon
     * @param $VarType
     * @param $EnableAction
     * @param $PositionX
     */
    protected function Variable_Register($VarIdent, $VarName, $VarProfile, $VarIcon, $VarType, $EnableAction, $PositionX = false)
    {
        if ($PositionX === false) {
            $Position = 0;
        } else {
            $Position = $PositionX;
        }

        switch ($VarType) {
            case 0:
                $this->RegisterVariableBoolean($VarIdent, $VarName, $VarProfile, $Position);
                break;

            case 1:
                $this->RegisterVariableInteger($VarIdent, $VarName, $VarProfile, $Position);
                break;

            case 2:
                $this->RegisterVariableFloat($VarIdent, $VarName, $VarProfile, $Position);
                break;

            case 3:
                $this->RegisterVariableString($VarIdent, $VarName, $VarProfile, $Position);
                break;
        }

        if ($VarIcon !== '') {
            IPS_SetIcon($this->GetIDForIdent($VarIdent), $VarIcon);
        }

        if ($EnableAction === true) {
            $this->EnableAction($VarIdent);
        }
    }


    /**
     * Variable_Unregister (unregister and delete variable)
     *
     * @param string $Ident
     * @return bool
     */
    protected function Variable_Unregister($Ident)
    {
        $VarID = @$this->GetIDForIdent($Ident);
        if ($VarID > 0) {
            if (IPS_VariableExists($VarID) === false) {
                $this->SendDebug(__FUNCTION__, 'Variable with ID "' . $VarID . '" does not exist!', 0);
                return false;
            }
            $this->UnregisterVariable($Ident);

            return true;
        }

        return false;
    }
}

?>