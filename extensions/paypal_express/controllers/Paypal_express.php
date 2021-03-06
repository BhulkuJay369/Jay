<?php if ( ! defined('BASEPATH')) exit('No direct access allowed');

class Paypal_express extends Main_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('customer');
        $this->load->model('Orders_model');
        $this->load->model('Paypal_model');
    }

    public function index() {
        if ( ! file_exists(EXTPATH .'paypal_express/views/paypal_express.php')) { 								//check if file exists in views folder
            show_404(); 																		// Whoops, show 404 error page!
        }

        $payment = $this->extension->getPayment('paypal_express');

        // START of retrieving lines from language file to pass to view.
        $data['code'] 			= $payment['name'];
        $data['title'] 			= !empty($payment['title']) ? $payment['title'] : $this->lang->line('text_title');
        // END of retrieving lines from language file to send to view.

        $order_data = $this->session->userdata('order_data');                           // retrieve order details from session userdata
        $data['payment'] = !empty($order_data['payment']) ? $order_data['payment'] : '';

        // pass array $data and load view files
        return $this->load->view('paypal_express/paypal_express', $data, TRUE);
    }

    public function confirm() {
        $order_data = $this->session->userdata('order_data'); 						// retrieve order details from session userdata
        $cart_contents = $this->session->userdata('cart_contents'); 												// retrieve cart contents

        if (empty($order_data) OR empty($cart_contents)) {
            return FALSE;
        }

        if (!empty($order_data['payment']) AND $order_data['payment'] == 'paypal_express') { 	// check if payment method is equal to paypal
            $this->load->model('Paypal_model');
            $response = $this->Paypal_model->setExpressCheckout($order_data, $this->cart->contents());

            if (strtoupper($response['ACK']) === 'SUCCESS' OR strtoupper($response['ACK']) === 'SUCCESSWITHWARNING') {
                $payment = isset($order_data['ext_payment']) ? $order_data['ext_payment'] : array();
                if (isset($payment['ext_data']['api_mode']) AND $payment['ext_data']['api_mode'] === 'sandbox') {
                    $api_mode = '.sandbox';
                } else {
                    $api_mode = '';
                }

                redirect('https://www' . $api_mode . '.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $response['TOKEN'] . '');
            } else {
                log_message('error', $response['L_ERRORCODE0'] . ' --> ' . $response['L_LONGMESSAGE0'] . ' --> ' . $response['CORRELATIONID']);
            }
        }
    }

    public function authorize() {
        $order_data = $this->session->userdata('order_data'); 							// retrieve order details from session userdata
        $cart_contents = $this->session->userdata('cart_contents'); 												// retrieve cart contents

        if (!empty($order_data) AND $this->input->get('token') AND $this->input->get('PayerID')) { 						// check if token and PayerID is in $_GET data

            $token = $this->input->get('token'); 												// retrieve token from $_GET data
            $payer_id = $this->input->get('PayerID'); 											// retrieve PayerID from $_GET data

            $customer_id = (!$this->customer->islogged()) ? '' : $this->customer->getId();
            $order_id = (is_numeric($order_data['order_id'])) ? $order_data['order_id'] : FALSE;
            $order_info = $this->Orders_model->getOrder($order_id, $order_data['customer_id']);	// retrieve order details array from getMainOrder method in Orders model

            $transaction_id = $this->Paypal_model->doExpressCheckout($token, $payer_id);

            if ($transaction_id AND $order_info) {
                $transaction_details = $this->Paypal_model->getTransactionDetails($transaction_id);
                $this->Paypal_model->addPaypalOrder($transaction_id, $order_id, $customer_id, $transaction_details);
                $this->Orders_model->completeOrder($order_id, $order_data, $cart_contents);

                redirect('checkout/success');
            }
        }

        $this->alert->set('alert', $this->lang->line('alert_error_server'));
        redirect('checkout');
    }

    public function cancel() {
        $order_data = $this->session->userdata('order_data'); 							// retrieve order details from session userdata

        if (!empty($order_data) AND $this->input->get('token')) { 						// check if token and PayerID is in $_GET data

            $token = $this->input->get('token'); 												// retrieve token from $_GET data


//        $this->alert->set('alert', $this->lang->line('alert_error_server'));
            redirect('checkout');
        }
    }
}

/* End of file paypal_express.php */
/* Location: ./extensions/paypal_express/controllers/paypal_express.php */