<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
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

        $hashString     = '';
        $hash           = '';
        $hashParameters = [
            'p1',
            'p2',
            'p3',
            'p4',
            'p5',
            'p6',
            'p7',
            'p8',
            'p9',
            'p10',
            'p11',
            'p12',
            'p13',
            'NextOccurDate',
            'Budget',
            'CardholderEmail',
            'm_1',
            'm_2',
            'm_3',
            'm_4',
            'm_5',
            'm_6',
            'm_7',
            'm_8',
            'm_9',
            'm_10',
            'CustomerID',
            'RecurReference',
            'MerchantToken',
        ];
        $formFields = $payment->getFormFields();
        foreach ( $hashParameters as $hashParameter ) {
            if ( isset( $formFields[$hashParameter] ) ) {
                $hashString .= $formFields[$hashParameter];
            }
        }
        if ( $formFields['pam'] !== '' ) {
            $hash = md5( $hashString . $formFields['pam'] );
        }

        foreach ( $formFields as $field => $value ) {
            if ( $field !== 'pam' ) {
                $form->addField( $field, 'hidden', array( 'name' => $field, 'value' => $value ) );
            }
        }
        if ( $hash !== '' ) {
            $form->addField( 'Hash', 'hidden', array( 'name' => 'Hash', 'value' => $hash ) );
        }

        $html = '<html><body>';
        $html .= $this->__( 'You will be redirected to PayGate in a few seconds.' );
        $html .= $form->toHtml();
        $html .= '<script type="text/javascript">document.getElementById("paysubs_checkout").submit();</script>';
        $html .= '</body></html>';

        return $html;

    }

}
