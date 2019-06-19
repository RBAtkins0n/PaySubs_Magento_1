<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class Mage_PaySubs_Block_Failure extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate( 'paysubs/failure.phtml' );
    }

    public function getErrorMessage()
    {
        $error = Mage::getSingleton( 'checkout/session' )->getErrorMessage();
        Mage::getSingleton( 'checkout/session' )->unsErrorMessage();
        return $error;
    }

    /**
     * Get continue shopping url
     */
    public function getContinueShoppingUrl()
    {
        return Mage::getUrl( 'checkout/cart' );
    }
}
