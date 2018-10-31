<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class Mage_PaySubs_Model_Cc extends Mage_PaySubs_Model_Shared
{
    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     **/
    protected $_code          = 'paysubs_cc';
    protected $_formBlockType = 'paysubs/form';
    protected $_infoBlockType = 'paysubs/info';
    protected $_paymentMethod = 'cc';

    protected $_Url = 'https://www.vcs.co.za/vvonline/ccform.asp';
}
