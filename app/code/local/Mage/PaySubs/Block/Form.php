<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class Mage_PaySubs_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate( 'paysubs/form.phtml' );
    }

    protected function _getConfig()
    {
        return Mage::getSingleton( 'paysubs/config' );
    }
}
