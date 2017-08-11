<?php
/** 
 * PAYONE OXID Connector is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PAYONE OXID Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with PAYONE OXID Connector.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      http://www.payone.de
 * @copyright (C) Payone GmbH
 * @version   OXID eShop CE
 */
 
class fcPayOneUser extends fcPayOneUser_parent {

    /**
     * Helper object for dealing with different shop versions
     * @var object
     */
    protected $_oFcpoHelper = null;

    /**
     * init object construction
     * 
     * @return null
     */
    public function __construct() {
        parent::__construct();
        $this->_oFcpoHelper = oxNew('fcpohelper');
    }

    /**
     * Method manages adding/merging userdata
     *
     * @param array $aResponse
     * @return void
     */
    public function fcpoSetAmazonOrderReferenceDetailsResponse($aResponse) {
        $sAmazonEmailAddress = $this->_fcpoAmazonEmailEncode($aResponse['add_paydata[email]']);
        $aResponse['add_paydata[email]'] = $sAmazonEmailAddress;
        $this->_fcpoAddOrUpdateAmazonUser($aResponse);
    }

    /**
     * Makes this Email unique to be able to handle amazon users different from standard users
     * Currently the email address simply gets a prefix
     *
     * @param $sEmail
     * @return string
     */
    protected function _fcpoAmazonEmailEncode($sEmail) {
        $oViewConf = $this->_oFcpoHelper->getFactoryObject('oxViewConfig');

        return $oViewConf->fcpoAmazonEmailEncode($sEmail);
    }

    /**
     * Returns the origin email of an amazon encoded email
     *
     * @param $sEmail
     * @return string
     */
    protected function _fcpoAmazonEmailDecode($sEmail) {
        $oViewConf = $this->_oFcpoHelper->getFactoryObject('oxViewConfig');

        return $oViewConf->fcpoAmazonEmailDecode($sEmail);
    }

    /**
     * Checks if a user should be added or updated, redirects to matching method
     * and logs user in
     *
     * @param $aResponse
     * @return void
     */
    protected function _fcpoAddOrUpdateAmazonUser($aResponse) {
        $sAmazonEmailAddress = $aResponse['add_paydata[email]'];
        $blUserExists = $this->_fcpoUserExists($sAmazonEmailAddress);
        if ($blUserExists) {
            $sUserId = $this->_fcpoUpdateAmazonUser($aResponse);
        } else {
            $sUserId = $this->_fcpoAddAmazonUser($aResponse);
        }
        // logoff and on again
        $this->_oFcpoHelper->fcpoSetSessionVariable('usr', $sUserId);
    }

    /**
     * Method adds a new amazon user into OXIDs user system. User won't get a password
     *
     * @param $aResponse
     * @return string
     */
    protected function _fcpoAddAmazonUser($aResponse) {
        $aStreetParts = $this->_fcpoSplitStreetAndStreetNr($aResponse['add_paydata[shipping_street]']);
        $sCountryId = $this->_fcpoGetCountryIdByIso2($aResponse['add_paydata[shipping_country]']);

        $oUser = $this->_oFcpoHelper->getFactoryObject('oxUser');
        $sUserOxid = $oUser->getId();
        $oUser->oxuser__oxusername = new oxField($aResponse['add_paydata[email]']);
        $oUser->oxuser__oxstreet = new oxField($aStreetParts['street']);
        $oUser->oxuser__oxstreetnr = new oxField($aStreetParts['streetnr']);
        $oUser->oxuser__oxzip = new oxField($aResponse['add_paydata[shipping_zip]']);
        $oUser->oxuser__oxfon = new oxField($aResponse['add_paydata[shipping_telephonenumber]']);
        $oUser->oxuser__oxfname = new oxField($aResponse['add_paydata[shipping_firstname]']);
        $oUser->oxuser__oxlname = new oxField($aResponse['add_paydata[shipping_lastname]']);
        $oUser->oxuser__oxcity = new oxField($aResponse['add_paydata[shipping_city]']);
        $oUser->oxuser__oxcountryid = new oxField($sCountryId);
        $oUser->addToGroup('oxidnotyetordered');

        $oUser->save();

        return $sUserOxid;
    }

    /**
     * Updating user. Checking current address, if different add new address as additional address to user
     * iff current address is not known until now
     *
     * @param $aResponse
     * @return string
     */
    protected function _fcpoUpdateAmazonUser($aResponse) {
        $sAmazonEmailAddress = $aResponse['add_paydata[email]'];
        $sUserOxid = $this->_fcpoGetUserOxidByEmail($sAmazonEmailAddress);

        $oUser = $this->_oFcpoHelper->getFactoryObject('oxUser');
        $oUser->load($sUserOxid);

        $aStreetParts = $this->_fcpoSplitStreetAndStreetNr($aResponse['add_paydata[shipping_street]']);
        $sCountryId = $this->_fcpoGetCountryIdByIso2($aResponse['add_paydata[shipping_country]']);

        $oUser->oxuser__oxusername = new oxField($aResponse['add_paydata[email]']);
        $oUser->oxuser__oxstreet = new oxField($aStreetParts['street']);
        $oUser->oxuser__oxstreetnr = new oxField($aStreetParts['streetnr']);
        $oUser->oxuser__oxzip = new oxField($aResponse['add_paydata[shipping_zip]']);
        $oUser->oxuser__oxfon = new oxField($aResponse['add_paydata[shipping_telephonenumber]']);
        $oUser->oxuser__oxfname = new oxField(trim($aResponse['add_paydata[shipping_firstname]']));
        $oUser->oxuser__oxlname = new oxField(trim($aResponse['add_paydata[shipping_lastname]']));
        $oUser->oxuser__oxcity = new oxField($aResponse['add_paydata[shipping_city]']);
        $oUser->oxuser__oxcountryid = new oxField($sCountryId);
        $oUser->addToGroup('oxidnotyetordered');

        $oUser->save();

        return $sUserOxid;
    }

    /**
     * Method splits street and streetnr from string
     *
     * @param string $sStreetAndStreetNr
     * @return array
     */
    protected function _fcpoSplitStreetAndStreetNr($sStreetAndStreetNr) {
        /**
         * @todo currently very basic by simply splitting ot space
         */
        $aReturn = array();
        $aParts = explode(' ', $sStreetAndStreetNr);
        foreach ($aParts as $iIndex=>$sPart) {
            if ($iIndex==(count($aParts)-1)) {
                $aReturn['streetnr'] = $sPart;
            } else {
                $aStreetNames[] = $sPart;
            }
        }
        $aReturn['street'] = implode(' ', $aStreetNames);

        return $aReturn;
    }

    /**
     * Returns id of a countrycode
     *
     * @param $sIso2Country
     * @return string
     */
    protected function _fcpoGetCountryIdByIso2($sIso2Country) {
        $oCountry = $this->_oFcpoHelper->getFactoryObject('oxCountry');
        $sOxid = $oCountry->getIdByCode($sIso2Country);

        return $sOxid;
    }

    /**
     * Method checks if a user WITH password exists using the given email-address
     *
     * @param string $sAmazonEmailAddress
     * @return bool
     */
    protected function _fcpoUserExists($sAmazonEmailAddress, $blWithPasswd=false) {
        $blReturn = false;
        $sUserOxid = $this->_fcpoGetUserOxidByEmail($sAmazonEmailAddress);
        if ($sUserOxid && !$blWithPasswd) {
            $blReturn = true;
        } elseif ($sUserOxid && $blWithPasswd) {
            $this->load($sUserOxid);
            $blReturn = ($this->oxuser__oxpassword->value) ? true : false;
        }

        return $blReturn;
    }

    /**
     * Method delivers OXID of a user by offfering an email address or false if email does not exist
     *
     * @param string $sAmazonEmailAddress
     * @return mixed
     */
    protected function _fcpoGetUserOxidByEmail($sAmazonEmailAddress) {
        $oDb = $this->_oFcpoHelper->fcpoGetDb();
        $sQuery = "SELECT OXID FROM oxuser WHERE OXUSERNAME=".$oDb->quote($sAmazonEmailAddress);
        $mReturn = $oDb->GetOne($sQuery);

        return $mReturn;
    }

    /**
     * Sets the credit-worthiness of the user
     *
     * @param array $aResponse response of a API request
     *
     * @return null
     */
    protected function fcpoSetBoni($aResponse) {
        $boni = 100;
        if ($aResponse['scorevalue']) {
            $boni = $this->_fcpoCalculateBoniFromScoreValue($aResponse['scorevalue']);
        } else {
            $aResponse = $this->_fcpoCheckUseFallbackBoniversum($aResponse);
            $aMap = array('G' => 500, 'Y' => 300, 'R' => 100);
            if (isset($aMap[$aResponse['score']])) {
                $boni = $aMap[$aResponse['score']];
            }
        }

        $this->oxuser__oxboni->value = $boni;

        $blValidResponse = ($aResponse && is_array($aResponse) && array_key_exists('fcWrongCountry', $aResponse) === false);

        if ($blValidResponse) {
            $this->oxuser__fcpobonicheckdate = new oxField(date('Y-m-d H:i:s'));
        }

        $this->save();
    }

    /**
     * Calculates scorevalue to make it usable in OXID
     *
     * @param $sScoreValue
     * @return string
     * @see https://integrator.payone.de/jira/browse/OXID-136
     */
    protected function _fcpoCalculateBoniFromScoreValue($sScoreValue) {
        $dScoreValue = (double)$sScoreValue;
        $oConfig = $this->getConfig();
        $sFCPOBonicheck = $oConfig->getConfigParam('sFCPOBonicheck');

        if ($sFCPOBonicheck == 'CE') {
            $sScoreValue = (string) round(1000-($dScoreValue/6),0);
        }

        return $sScoreValue;
    }

    /**
     * Parses response and set fallback if conditions match
     *
     * @param $aResponse
     * @return array
     */
    protected function _fcpoCheckUseFallbackBoniversum($aResponse) {
        $oConfig = $this->getConfig();
        $sScore = $aResponse['score'];
        $sAddresscheckType = $this->_fcpoGetAddressCheckType();

        $blUseFallBack = (
            $sScore == 'U' &&
            in_array($sAddresscheckType, array('BB', 'PB'))
        );

        if ($blUseFallBack) {
            $sFCPOBoniversumFallback = $oConfig->getConfigParam('sFCPOBoniversumFallback');
            $aResponse['score'] = $sFCPOBoniversumFallback;
        }

        return $aResponse;
    }

    /**
     * Check, correct and return addresschecktype
     *
     * @param void
     * @return string
     */
    protected function _fcpoGetAddressCheckType() {
        $oConfig = $this->getConfig();
        $sBoniCheckType = $oConfig->getConfigParam('sFCPOBonicheck');
        $sAddressCheckType = $oConfig->getConfigParam('sFCPOAddresscheck');

        if ($sBoniCheckType == 'CE') {
            $sAddressCheckType = 'PB';
        }

        return $sAddressCheckType;
    }



    /**
     * Check if the credit-worthiness of the user has to be checked again
     *
     * @return bool
     */
    protected function isNewBonicheckNeeded() {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sTimeLastCheck = strtotime($this->oxuser__fcpobonicheckdate->value);
        $iEnduranceBoniCheck = (int) $oConfig->getConfigParam('sFCPODurabilityBonicheck');
        $sTimeout = (time() - (60 * 60 * 24 * $iEnduranceBoniCheck));

        $blReturn = ($sTimeout > $sTimeLastCheck) ? true : false;

        return $blReturn;
    }

    /**
     * Check if the current basket sum exceeds the minimum sum for the credit-worthiness check
     *
     * @return bool
     */
    protected function isBonicheckNeededForBasket() {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $iStartlimitBonicheck = $oConfig->getConfigParam('sFCPOStartlimitBonicheck');

        $blReturn = true;
        if ($iStartlimitBonicheck && is_numeric($iStartlimitBonicheck)) {
            $oSession = $this->_oFcpoHelper->fcpoGetSession();
            $oBasket = $oSession->getBasket();
            $oPrice = $oBasket->getPrice();

            if ($oPrice->getBruttoPrice() < $iStartlimitBonicheck) {
                $blReturn = false;
            }
        }

        return $blReturn;
    }

    /**
     * Check if the credit-worthiness has to be checked
     *
     * @return bool
     */
    protected function isBonicheckNeeded() {
        $blBoniCheckNeeded = (
            (
                $this->oxuser__oxboni->value == $this->getBoni() ||
                $this->isNewBonicheckNeeded()
            ) &&
            $this->isBonicheckNeededForBasket()
        );

        return $blBoniCheckNeeded;
    }

    /**
     * Check the credit-worthiness of the user with the consumerscore or addresscheck request to the PAYONE API
     *
     * @return bool
     */
    public function checkAddressAndScore($blCheckAddress = true, $blCheckBoni = true) {
        // in general we assume that everything is fine with score and address
        $blBoniChecked = $blAddressValid = true;

        // let's see what should be checked
        if ($blCheckBoni) {
            $blBoniChecked = $this->_fcpoPerformBoniCheck();
        }
        if ($blCheckAddress) {
            $blAddressValid = $this->_fcpoPerformAddressCheck();
        }

        // merge results
        $blChecksValid = ($blBoniChecked && $blAddressValid);

        return $blChecksValid;
    }

    /**
     * Performing address check
     *
     * @param void
     * @return bool
     */
    protected function _fcpoPerformAddressCheck() {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOAddresscheck = $this->_fcpoGetAddresscheckSetting();
        // early return a success if addresscheck is inactive
        if (!$sFCPOAddresscheck) return true;

        // get more addresscheck related settings
        $blFCPOCorrectAddress = (bool) $oConfig->getConfigParam('blFCPOCorrectAddress');
        $blFCPOCheckDelAddress = (bool) $oConfig->getConfigParam('blFCPOCheckDelAddress');

        // perform validations
        $blIsValidAddress = $this->_fcpoValidateAddress($blFCPOCorrectAddress);
        $blIsValidAddress = $this->_fcpoValidateDelAddress($blIsValidAddress, $blFCPOCheckDelAddress);

        return $blIsValidAddress;
    }

    /**
     * Returns addresscheck setting or false if inactive
     *
     * @param void
     * @return mixed bool/string
     */
    protected function _fcpoGetAddresscheckSetting() {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOAddresscheck = $oConfig->getConfigParam('sFCPOAddresscheck');
        $mFCPOAddresscheck = ($sFCPOAddresscheck == 'NO') ? false : $sFCPOAddresscheck;

        return $mFCPOAddresscheck;
    }

    /**
     * Performing boni check on user
     *
     * @param void
     * @return void
     */
    protected function _fcpoPerformBoniCheck() {
        $sFCPOBonicheck = $this->_fcpoGetBoniSetting();
        $blBoniCheckNeeded = $this->isBonicheckNeeded();

        // early return as success if bonicheck is inactive or not needed
        if (!$sFCPOBonicheck || !$blBoniCheckNeeded) return true;

        return $this->_fcpoValidateBoni();
    }

    /**
     * Returns boni setting or false if inactive
     *
     * @param void
     * @return mixed bool/string
     */
    protected function _fcpoGetBoniSetting() {
        // get raw configured setting
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOBonicheck = $oConfig->getConfigParam('sFCPOBonicheck');

        // multiple inactivity checks due to php is a non type checking language
        $blBoniInactive = ($sFCPOBonicheck == -1 || $sFCPOBonicheck == '-1' || !$sFCPOBonicheck);

        // sum it up
        $mFCPOBonicheck = ($blBoniInactive) ? false : $sFCPOBonicheck;

        return $mFCPOBonicheck;
    }

    /**
     * Validating delivery address
     * 
     * @param bool $blIsValidAddress
     * @param bool $blFCPOCheckDelAddress
     * @return boolean
     */
    protected function _fcpoValidateDelAddress($blIsValidAddress, $blFCPOCheckDelAddress) {
        if ($blIsValidAddress && $blFCPOCheckDelAddress === true) {
            //check delivery address
            $oPORequest = $this->_oFcpoHelper->getFactoryObject('fcporequest');
            $aResponse = $oPORequest->sendRequestAddresscheck($this, true);

            if ($aResponse === false || $aResponse === true) {
                // false = No deliveryaddress given
                // true = Address-check has been skipped because the address has been checked before
                return true;
            }

            $blIsValidAddress = $this->fcpoIsValidAddress($aResponse, false);
        }

        return $blIsValidAddress;
    }

    /**
     * Validates address by requesting payone
     * 
     * @param string $sFCPOBonicheck
     * @param bool $blCheckedBoni
     * @param bool $blFCPOCorrectAddress
     * @return bool
     */
    protected function _fcpoValidateAddress($blFCPOCorrectAddress) {
        //check billing address
        $oPORequest = $this->_oFcpoHelper->getFactoryObject('fcporequest');
        $aResponse = $oPORequest->sendRequestAddresscheck($this);

        if ($aResponse === true) {
            // check has been performed recently
            $blIsValidAddress = true;
        } else {
            // address check has been triggered - validate the response
            $blIsValidAddress = $this->fcpoIsValidAddress($aResponse, $blFCPOCorrectAddress);
        }

        return $blIsValidAddress;
    }

    /**
     * Requesting for boni of user if conditions are alright
     * 
     * @param void
     * @return void
     */
    protected function _fcpoValidateBoni() {
        // Consumerscore
        $oPORequest = $this->_oFcpoHelper->getFactoryObject('fcporequest');
        $aResponse = $oPORequest->sendRequestConsumerscore($this);
        $this->fcpoSetBoni($aResponse);

        return true;
    }

    /**
     * Overrides oxid standard method getBoni()
     * Sets it to value defined in the admin area of PAYONE if it was configured
     *
     * @return int
     * @extend getBoni()
     */
    public function getBoni() {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $iDefaultBoni = $oConfig->getConfigParam('sFCPODefaultBoni');
        if ($iDefaultBoni !== null && is_numeric($iDefaultBoni) === true) {
            return $iDefaultBoni;
        }
        return parent::getBoni();
    }

    /**
     * Checks if the address given by the user matches the address returned by the PAYONE addresscheck API request
     *
     * @return bool
     */
    protected function fcpoIsValidAddress($aResponse, $blCorrectUserAddress) {
        $blEarlyValidation = (
            $aResponse &&
            is_array($aResponse) &&
            array_key_exists('fcWrongCountry', $aResponse) &&
            $aResponse['fcWrongCountry'] === true
        );

        // early return on quick check
        if ($blEarlyValidation) return true;

        // dig deeper, do corrections if configured
        $blReturn = $this->_fcpoValidateResponse($aResponse, $blCorrectUserAddress);

        return $blReturn;
    }

    /**
     * Validating response of address check
     * 
     * @param array $aResponse
     * @param bool $blCorrectUserAddress
     * @return boolean
     */
    protected function _fcpoValidateResponse($aResponse, $blCorrectUserAddress) {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $oUtilsView = $this->_oFcpoHelper->fcpoGetUtilsView();

        if ($aResponse['status'] == 'VALID') {
            $blReturn = $this->_fcpoValidateUserDataByResponse($aResponse, $blCorrectUserAddress);
            return $blReturn;
        } elseif ($aResponse['status'] == 'INVALID') {
            $sErrorMsg = $oLang->translateString('FCPO_ADDRESSCHECK_FAILED1') . $aResponse['customermessage'] . $oLang->translateString('FCPO_ADDRESSCHECK_FAILED2');
            $oUtilsView->addErrorToDisplay($sErrorMsg, false, true);
            return false;
        } elseif ($aResponse['status'] == 'ERROR') {
            $sErrorMsg = $oLang->translateString('FCPO_ADDRESSCHECK_FAILED1') . $aResponse['customermessage'] . $oLang->translateString('FCPO_ADDRESSCHECK_FAILED2');
            $oUtilsView->addErrorToDisplay($sErrorMsg, false, true);
            return false;
        }
    }

    /**
     * Validate user data against request response and correct address if configured
     * 
     * @param array $aResponse
     * @param bool $blCorrectUserAddress
     * @return boolean
     */
    protected function _fcpoValidateUserDataByResponse($aResponse, $blCorrectUserAddress) {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $oUtilsView = $this->_oFcpoHelper->fcpoGetUtilsView();
        $mPersonstatus = $oConfig->getConfigParam('blFCPOAddCheck' . $aResponse['personstatus']);

        if ($mPersonstatus) {
            $sErrorMsg = $oLang->translateString('FCPO_ADDRESSCHECK_FAILED1') . $oLang->translateString('FCPO_ADDRESSCHECK_' . $aResponse['personstatus']) . $oLang->translateString('FCPO_ADDRESSCHECK_FAILED2');
            $oUtilsView->addErrorToDisplay($sErrorMsg, false, true);
            return false;
        } else {
            if ($blCorrectUserAddress) {
                if ($aResponse['firstname']) {
                    $this->oxuser__oxfname = new oxField($aResponse['firstname']);
                }
                if ($aResponse['lastname']) {
                    $this->oxuser__oxlname = new oxField($aResponse['lastname']);
                }
                if ($aResponse['streetname']) {
                    $this->oxuser__oxstreet = new oxField($aResponse['streetname']);
                }
                if ($aResponse['streetnumber']) {
                    $this->oxuser__oxstreetnr = new oxField($aResponse['streetnumber']);
                }
                if ($aResponse['zip']) {
                    $this->oxuser__oxzip = new oxField($aResponse['zip']);
                }
                if ($aResponse['city']) {
                    $this->oxuser__oxcity = new oxField($aResponse['city']);
                }
                $this->save();
            }
            #Country auch noch ?!? ( umwandlung iso nach id )
            #$this->oxuser__oxfname->value = $aResponse['country'];
            return true;
        }
    }

    /**
     * Unsetting groups
     * 
     * @param void
     * @return void
     */
    public function fcpoUnsetGroups() {
        $this->_oGroups = null;
    }

}
