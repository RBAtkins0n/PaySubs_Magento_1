<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class Mage_PaySubs_Model_Shared extends Mage_Payment_Model_Method_Abstract
{

    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     **/
    protected $_code = 'paysubs_shared';

    protected $_isGateway              = false;
    protected $_canAuthorize           = true;
    protected $_canCapture             = true;
    protected $_canCapturePartial      = false;
    protected $_canRefund              = false;
    protected $_canVoid                = false;
    protected $_canUseInternal         = false;
    protected $_canUseCheckout         = true;
    protected $_canUseForMultishipping = true;

    protected $_paymentMethod = 'shared';
    protected $_defaultLocale = 'en';

    protected $_Url;

    protected $_order;

    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if ( !$this->_order ) {
            $paymentInfo  = $this->getInfoInstance();
            $this->_order = Mage::getModel( 'sales/order' )
                ->loadByIncrementId( $paymentInfo->getOrder()->getRealOrderId() );
        }
        return $this->_order;
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton( 'checkout/session' );
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl( 'paysubs/processing/redirect' );
    }

    public function capture( Varien_Object $payment, $amount )
    {
        $payment->setStatus( self::STATUS_APPROVED )
            ->setLastTransId( $this->getTransactionId() );

        return $this;
    }

    public function cancel( Varien_Object $payment )
    {
        $payment->setStatus( self::STATUS_DECLINED )
            ->setLastTransId( $this->getTransactionId() );

        return $this;
    }

    /**
     * Return redirect block type
     *
     * @return string
     */
    public function getRedirectBlockType()
    {
        return $this->_redirectBlockType;
    }

    /**
     * Return payment method type string
     *
     * @return string
     */
    public function getPaymentMethodType()
    {
        return $this->_paymentMethod;
    }

    public function getUrl()
    {
        return $this->_Url;
    }

    /**
     * prepare params array to send it to gateway page via POST
     *
     * @return array
     */
    public function getFormFields()
    {
        $orderId  = $this->getOrder()->getRealOrderId();
        $amount   = number_format( $this->getOrder()->getBaseGrandTotal(), 2, '.', '' );
        $currency = $this->getOrder()->getBaseCurrencyCode();
        $email    = $this->getOrder()->getCustomerEmail();

        $order          = Mage::getModel( 'sales/order' )->loadByIncrementId( $orderId );
        $billingAddress = $order->getBillingAddress();
        if ( $billingAddress ) {
            $phone = $billingAddress->getData( 'telephone' );
        }
        $terminal_id   = $this->getConfigData( 'terminal_id' );
        $description   = $this->getConfigData( 'description' );
        $currency      = $this->getConfigData( 'currency' );
        $settlement    = $this->getConfigData( 'delayed_settlement' );
        $budget        = $this->getConfigData( 'budget' );
        $pam           = $this->getConfigData( 'pam' );
        $send_email    = $this->getConfigData( 'holder_email' );
        $send_msg      = $this->getConfigData( 'sms_message' );
        $recurring     = $this->getConfigData( 'recurring' );
        $occur_email   = $this->getConfigData( 'occurance_email' );
        $return_url    = $this->getConfigData( 'paysubs_return_url' );
        $cancelled_url = $this->getConfigData( 'paysubs_cancelled_url' );

        $locale = explode( '_', Mage::app()->getLocale()->getLocaleCode() );
        if ( is_array( $locale ) && !empty( $locale ) ) {
            $locale = $locale[0];
        } else {
            $locale = $this->getDefaultLocale();
        }

        if ( $recurring ) {
            $occur_freq   = $this->getConfigData( 'occur_frequency' );
            $occur_count  = $this->getConfigData( 'occur_count' );
            $occur_amount = $this->getConfigData( 'occur_amount' );
            $occur_date   = $this->getConfigData( 'occur_date' );
        } else {
            $occur_freq   = '';
            $occur_count  = '';
            $occur_amount = '';
            $occur_date   = '';
        }
        if ( $send_msg ) {
            $message = $this->getConfigData( 'message' );
        } else {
            $message = '';
        }
        if ( $send_email ) {
            $email = $this->getOrder()->getCustomerEmail();
        } else {
            $email = '';
        }
        if ( $return_url == 'y' ) {
            $approved_url = $this->getConfigData( 'paysubs_approved_url' );
            $declined_url = $this->getConfigData( 'paysubs_declined_url' );
        } else {
            $approved_url = '';
            $declined_url = '';
        }

        $hash   = $terminal_id . $orderId . $description . $amount . $currency . $occur_freq . $occur_count . $phone . $message . $cancelled_url . $occur_email . $settlement . $occur_amount . $occur_date . $email . md5( $this->getConfigData( 'pam' ) . '::' . $orderId ); //Hash value calculation
        $params = array(
            'p1'                  => $terminal_id,
            'p2'                  => $orderId,
            'p3'                  => $description,
            'p4'                  => $amount,

            'p5'                  => $currency,
            'p6'                  => $occur_freq,
            'p7'                  => $occur_count,
            'p8'                  => $phone,
            'p9'                  => $message,
            'p10'                 => $cancelled_url,
            'p11'                 => $occur_email,
            'p12'                 => $settlement,
            'p13'                 => $occur_amount,
            'CardholderEmail'     => $email,
            'Next Occurance Date' => $occur_date,
            'Budget'              => $budget,
            'UrlsProvide'         => $return_url,
            'ApprovedUrl'         => $approved_url,
            'DeclinedUrl'         => $declined_url,
            'Pam'                 => $hash,
        );
        if ( $this->getConfigData( 'pam' ) != '' ) {
            $params['m_1'] = md5( $this->getConfigData( 'pam' ) . '::' . $params['p2'] );
        }
        return $params;

    }
}
