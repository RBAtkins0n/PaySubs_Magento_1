<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class Mage_Adminhtml_Model_System_Config_Source_Enabledisableyn
{
    public function toOptionArray()
    {
        return array(
            array( 'value' => 'y', 'label' => Mage::helper( 'adminhtml' )->__( 'Enable' ) ),
            array( 'value' => 'n', 'label' => Mage::helper( 'adminhtml' )->__( 'Disable' ) ),
        );
    }
}
