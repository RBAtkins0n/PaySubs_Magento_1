<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class Mage_PaySubs_ProcessingController extends Mage_Core_Controller_Front_Action
{
    protected $_redirectBlockType = 'paysubs/processing';
    protected $_successBlockType  = 'paysubs/success';
    protected $_failureBlockType  = 'paysubs/failure';

    protected $_sendNewOrderEmail = true;

    protected $_order       = null;
    protected $_paymentInst = null;

    protected function _expireAjax()
    {
        if ( !$this->getCheckout()->getQuote()->hasItems() ) {
            $this->getResponse()->setHeader( 'HTTP/1.1', '403 Session Expired' );
            exit;
        }
    }

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton( 'checkout/session' );
    }

    /**
     * when customer select PaySubs payment method
     */
    public function redirectAction()
    {
        $session = $this->getCheckout();
        $session->setPaySubsQuoteId( $session->getQuoteId() );
        $session->setPaySubsRealOrderId( $session->getLastRealOrderId() );

        $order = Mage::getModel( 'sales/order' );
        $order->loadByIncrementId( $session->getLastRealOrderId() );
        $order->addStatusToHistory( Mage_Sales_Model_Order::STATE_HOLDED,
            Mage::helper( 'paysubs' )->__( 'Customer was redirected to PayGate.' ) );
        $order->save();

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock( $this->_redirectBlockType )
                ->setOrder( $order )
                ->toHtml()
        );
        $session->unsQuoteId();
    }

    /**
     * Failure
     * POST from PaySubs returns here
     */
    public function failureAction()
    {
        /** Get POST content and sanitise it */
        $post = [];
        foreach ( $_REQUEST as $k => $value ) {
            $post[$k] = filter_var( $value, FILTER_SANITIZE_STRING );
        }

        $session = $this->getCheckout();

        if ( count( $post ) === 0 ) {
            $session->addError( 'POST back failed' );
        } else {
            if ( $post['p4'] === 'Duplicate' ) {
                $session->addError( 'Duplicate transaction' );
            }
            $session->addError( Mage::helper( 'paysubs' )->__( $post['p3'] ) );
            /** p2 holds order id
             * m_3 holds orderid-quoteid
             * p3 has response - characters 7-16 == APPROVED
             */
            if ( isset( $post['m_3'] ) && !empty( $post['m_3'] ) ) {
                $rid     = explode( '-', $post['m_3'] );
                $orderId = (int) $rid[0];
                $quoteId = (int) $rid[1];
            } else {
                $orderId = $session->getLastOrderId();
                $quoteId = $session->getPaySubsQuoteId( true );
            }
            $this->failed( $orderId, $post, $quoteId );
        }
    }

    /**
     * Success
     * POST from PaySubs returns here
     */
    public function responseAction()
    {
        /** Get POST content and sanitise it */
        $post = [];
        foreach ( $_POST as $k => $value ) {
            $post[$k] = filter_var( $value, FILTER_SANITIZE_STRING );
        }

        $session = $this->getCheckout();

        /** p2 holds order id
         * m_3 holds orderid-quoteid
         * p3 has response - characters 7-16 == APPROVED
         */
        if ( isset( $post['m_3'] ) && !empty( $post['m_3'] ) ) {
            $rid     = explode( '-', $post['m_3'] );
            $orderId = (int) $rid[0];
            $quoteId = (int) $rid[1];
        } else {
            $orderId = $session->getLastOrderId();
            $quoteId = $session->getPaySubsQuoteId( true );
        }
        if ( isset( $post['p3'] ) ) {
            if ( substr( $post['p3'], 6, 8 ) === 'APPROVED' ) {
                $this->successful( $orderId, $post, $quoteId );
            } else {
                $this->failed( $orderId, $post, $quoteId );
            }
        } else {
            // There is a problem - redirect to cart
            $cartUrl = Mage::getUrl( 'checkout/cart' );
            echo <<<HTML
<html>
<body>
<script>
window.location.href='$cartUrl';
</script>
</body>
</html>
HTML;
        }
    }

    /**
     * @param $orderId
     * @param $post
     * @param false $quoteId
     */
    private function successful( $orderId, $post, $quoteId = false )
    {
        $order = Mage::getModel( 'sales/order' )->loadByIncrementId( $orderId );
        $order->setState( Mage_Sales_Model_Order::STATE_PROCESSING )->save();

        $payment = $order->getPayment();
        $payment->save();

        $invoice = $order->prepareInvoice();
        $invoice->register()->capture();
        Mage::getModel( 'core/resource_transaction' )
            ->addObject( $invoice )
            ->addObject( $invoice->getOrder() )
            ->save();
        $message = Mage::helper( 'paysubs' )->__( 'Payment successful. Authorization: ' . substr( $post, 0, 6 ) );
        if ( $this->_sendNewOrderEmail ) {
            $message .= Mage::helper( 'paysubs' )->__( 'Nptified customer about invoice# ' . $invoice->getIncrementId() );
            $order->sendNewOrderEmail()
                ->addStatusHistoryComment( $message )
                ->setIsCustomerNotified( true )
                ->save();
        } else {
            $order->addStatusHistoryComment( $message )->save();
        }
        $this->clearCart();
        $checkoutSession = Mage::getSingleton( 'checkout/type_onepage' )->getCheckout();
        $checkoutSession->setLastSuccessQuoteId( $quoteId );
        $checkoutSession->setLastQuoteId( $quoteId );
        $checkoutSession->setLastOrderId( $orderId );

        $url = Mage::getUrl( 'checkout/onepage/success' );

        echo <<<HTML
<html>
<body>
    <script>window.location='$url';</script>
</body>
</html>
HTML;
        die;
    }

    /**
     * @param $orderId
     * @param $post
     * @param false $quoteId
     */
    private function failed( $orderId, $post, $quoteId = false )
    {
        $order = Mage::getModel( 'sales/order' )->loadByIncrementId( $orderId );
        $order->cancel();
        $order->setState(
            'Mage_Sales_Model_Order::STATE_CANCELED',
            true,
            'Redirect Response: Payment using PaySubs VCS failed: ' . $post['p3']
        );
        $order->setStatus( 'canceled' );
        $order->addStatusToHistory(
            Mage_Sales_Model_Order::STATE_CANCELED,
            'Redirect Response: Payment using PaySubs VCS failed: ' . $post['p3']
        );
        $order->save();

        $session = Mage::getSingleton( 'checkout/session' );

        if ( $quoteId ) {
            /**
             * @var $quote Mage_Sales_Model_Quote
             */
            $quote = Mage::getModel( 'sales/quote' )->load( $quoteId );
            if ( $quote->getId() ) {
                $quote->setIsActive( true )->save();
                $session->setQuoteId( $quoteId );
            }
        }

        $url = Mage::getUrl( 'checkout/onepage/failure' );
        echo <<<HTML
<html>
<body>
    <script>window.location='$url';</script>
</body>
</html>
HTML;
        die;
    }

    /**
     *
     */
    private function clearCart()
    {
        Mage::getSingleton( 'checkout/session' )->clear();
        foreach ( Mage::getSingleton( 'checkout/session' )->getQuote()->getItemsCollection() as $item ) {
            Mage::getSingleton( 'checkout/cart' )->removeItem( $item->getId() )->save();
        }
    }
}
