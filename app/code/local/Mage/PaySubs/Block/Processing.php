<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class Mage_PaySubs_Block_Processing extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $payment = $this->getOrder()->getPayment()->getMethodInstance();

        $form = new Varien_Data_Form();
        $form->setAction( $payment->getUrl() )
            ->setId( 'paysubs_checkout' )
            ->setName( 'paysubs_checkout' )
            ->setMethod( 'POST' )
            ->setUseContainer( true );
        foreach ( $payment->getFormFields() as $field => $value ) {
            if ( $field == 'UrlsProvide' && $value == 'y' ) {
                $field = 'URLSProvided';
                $value = 'Y';
            }
            $form->addField( $field, 'hidden', array( 'name' => $field, 'value' => $value ) );
        }

        $html = '<html><body>';
        $html .= $this->__( 'You will be redirected to PaySubs in a few seconds.' );
        $html .= $form->toHtml();
        $html .= '<script type="text/javascript">document.getElementById("paysubs_checkout").submit();</script>';
        $html .= '</body></html>';

        return $html;

    }

}
