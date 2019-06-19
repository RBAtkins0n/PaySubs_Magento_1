<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
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
            Mage::helper( 'paysubs' )->__( 'Customer was redirected to PaySubs.' ) );
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
     * PaySubs returns POST variables to this action
     */
    public function responseAction()
    {
        $session = $this->getCheckout();
        $order   = Mage::getModel( 'sales/order' );
        $order->loadByIncrementId( $session->getLastRealOrderId() );
        $order->save();
        $order->sendNewOrderEmail();
        try {

            $request = $this->_checkReturnedPost();
            if ( $this->_order->canInvoice() ) {
                $invoice = $this->_order->prepareInvoice();
                $invoice->register()->capture();
                Mage::getModel( 'core/resource_transaction' )
                    ->addObject( $invoice )
                    ->addObject( $invoice->getOrder() )
                    ->save();
            }

            $this->_order->addStatusToHistory(
                $this->_order->getStatus(),
                Mage::helper( 'paysubs' )->__( 'Customer returned successfully.' ) . '<br/>' .
                Mage::helper( 'paysubs' )->__( 'Authorization:' ) . substr( $_REQUEST['p3'], 0, 6 ) );
            $this->_order->save();
            if ( $this->_order->getId() && $this->_sendNewOrderEmail ) {
                $this->_order->sendNewOrderEmail();
            }

            $this->loadLayout();
            $this->renderLayout();

            $this->_redirect( 'checkout/onepage/success' );

        } catch ( Exception $e ) {
            $this->loadLayout();
            $this->_initLayoutMessages( 'checkout/session' );
            $this->renderLayout();
            $this->_redirect( 'checkout/onepage/success' );
        }
    }

    /**
     * PaySubs return action
     */
    protected function successAction()
    {
        $session = $this->getCheckout();

        $session->unsPaySubsRealOrderId();
        $session->setQuoteId( $session->getPaySubsQuoteId( true ) );
        $session->getQuote()->setIsActive( false )->save();

        $order = Mage::getModel( 'sales/order' );
        $order->load( $this->getCheckout()->getLastOrderId() );
        if ( $order->getId() && $this->_sendNewOrderEmail ) {
            $order->sendNewOrderEmail();
        }

    }

    /**
     * PaySubs return action
     */
    protected function failureAction()
    {

        $session = $this->getCheckout();
        $session->getMessages( true );
        if ( !$this->getRequest()->isPost() ) {
            $session->addError( 'Wrong request type.' );
        } else {
            $request = $this->getRequest()->getPost();
            if ( $request['p4'] == 'Duplicate' ) {
                $session->addError( 'Duplicate transaction' );
            }
            $session->addError( Mage::helper( 'paysubs' )->__( $request['p3'] ) );

            $this->_order       = Mage::getModel( 'sales/order' )->loadByIncrementId( $request['p2'] );
            $this->_paymentInst = $this->_order->getPayment()->getMethodInstance();
            $this->_paymentInst->setTransactionId( $request['p2'] );
        }

        $messages = $session->getMessages();

        $errors = false;
        if ( $messages ) {
            foreach ( $messages->getErrors() as $msg ) {
                $errors[] = $msg->toString();
            }
        }

        if ( isset( $request ) ) {
            $this->_order->addStatusToHistory(
                Mage_Sales_Model_Order::STATE_CANCELED,
                "Failure from PaySubs<br/>" .
                ( is_array( $errors ) ? implode( "<br/>", $errors ) : 'No extra information' ) );
            $this->_order->cancel();
            $this->_order->save();
        } else {
            $order = Mage::getModel( 'sales/order' );
            $order->loadByIncrementId( $session->getLastRealOrderId() );
            $order->addStatusToHistory( Mage_Sales_Model_Order::STATE_CANCELED,
                Mage::helper( 'paysubs' )->__( 'Payment Canceled by Customer' ) );
            $order->save();
        }
        $this->loadLayout();
        $this->_initLayoutMessages( 'checkout/session' );
        $this->renderLayout();

    }

    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedPost()
    {
        // check request type
        if ( !$this->getRequest()->isPost() ) {
            throw new Exception( 'Wrong request type.', 10 );
        }

        // get request variables
        $request = $this->getRequest()->getPost();
        if ( empty( $request ) ) {
            throw new Exception( 'Request doesn\'t contain POST elements.', 20 );
        }

        // check order id
        if ( empty( $request['p2'] ) || strlen( $request['p2'] ) > 50 ) {
            throw new Exception( 'Missing or invalid order ID', 40 );
        }

        // load order for further validation
        $this->_order       = Mage::getModel( 'sales/order' )->loadByIncrementId( $request['p2'] );
        $this->_paymentInst = $this->_order->getPayment()->getMethodInstance();

        // check transaction password
        if ( $this->_paymentInst->getConfigData( 'pam' ) ) {
            if ( $this->_paymentInst->getConfigData( 'pam' ) != $request['pam'] ) {
                throw new Exception( 'Transaction password wrong' );
            }

            if ( $request['m_1'] != md5( $request['pam'] . '::' . $request['p2'] ) ) {
                throw new Exception( 'Checksum mismatch' );
            }

        }

        // check transaction status
        if ( !empty( $request['p3'] ) && substr( $request['p3'], 6, 8 ) != 'APPROVED' ) {
            throw new Exception( 'Transaction was not successfull.' );
        }

        // check transaction amount
        if ( number_format( $this->_order->getBaseGrandTotal(), 2, '.', '' ) != $request['p6'] ) {
            throw new Exception( 'Transaction amount doesn\'t match.' );
        }

        return $request;
    }
}
