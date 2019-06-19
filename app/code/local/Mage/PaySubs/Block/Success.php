<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class Mage_PaySubs_Block_Success extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate( 'paysubs/success.phtml' );
    }

    /**
     * Get continue shopping url
     */
    public function getContinueShoppingUrl()
    {
        return Mage::getUrl( 'checkout/cart' );
    }

    /**
     * Retrieve identifier of created order
     *
     * @return string
     */
    public function getOrderId()
    {
        return Mage::getSingleton( 'checkout/session' )->getLastRealOrderId();
    }

    /**
     * Check order print availability
     *
     * @return bool
     */
    public function canPrint()
    {
        return Mage::getSingleton( 'customer/session' )->isLoggedIn();
    }

    /**
     * Get url for order detale print
     *
     * @return string
     */
    public function getPrintUrl()
    {
        return $this->getUrl( 'sales/order/print', array( 'order_id' => Mage::getSingleton( 'checkout/session' )->getLastOrderId() ) );
    }
}
