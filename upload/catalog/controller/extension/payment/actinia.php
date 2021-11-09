<?php

class ControllerExtensionPaymentActinia extends Controller
{

    const ORDER_SEPARATOR = '_';
    const ORDER_APPROVED = 'PAID';
    const ORDER_PROCESSING = 'PENDING';

    protected $RESPONCE_SUCCESS = 'success';

    protected $RESPONCE_FAIL = 'failure';
    protected $SIGNATURE_SEPARATOR = '|';
    protected $ORDER_DECLINED = 'declined';
    protected $ORDER_EXPIRED = 'expired';

    /**
     * @return mixed
     */
    public function index()
    {
        try {

            $this->language->load('extension/payment/actinia');
            $this->load->model('extension/payment/actinia');

            $order_id = $this->session->data['order_id'];
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            $callback = $this->url->link('extension/payment/actinia/callback', '', true);
            $desc = $this->language->get('order_desq') . $order_id;

            if ($this->config->get('payment_actinia_currency') and $this->config->get('payment_actinia_currency') != ' ') {
                $payment_actinia_currency = $this->config->get('payment_actinia_currency');
            } else {
                $payment_actinia_currency = $this->session->data['currency'];
            }

            if ($this->config->get('payment_actinia_language') == '') {
                $lang = 'ru';
            } else {
                $lang = $this->config->get('payment_actinia_language');
            }

            $paymentData = [
                'merchantId' => $this->config->get('payment_actinia_merchant'),
                'clientName' => sprintf('%s %s', $order_info['firstname'], $order_info['lastname']),
                'clientEmail' => $order_info['email'],
                'clientPhone' => $this->preparePhone($order_info['telephone']),
                'description' => $desc,
                'amount' => str_replace(',', '.', (string)round($order_info['total'] * $order_info['currency_value'], 2)),
                'currency' => $payment_actinia_currency,
//                'clientAccountId'   => $this->config->get('payment_actinia_clientaccountid'),
                'returnUrl' => $this->url->link('extension/payment/actinia/response', '', true),
                'externalId' => $order_id . self::ORDER_SEPARATOR . time(),
                'locale' => strtoupper($lang),
                'expiresInMinutes' => "45",
//                'expireType'        => "minutes",
//                'time'              => "45",
                'feeCalculationType' => "INNER",
                'withQR' => "NO",
                'cb' => [
                    'serviceName' => 'InvoiceService',
                    'serviceAction' => 'invoiceGet',
                    'serviceParams' => [
                        'callbackUrl' => $callback,
                    ]
                ]
            ];

            $resData = $this->model_extension_payment_actinia
                ->setClientCodeName($this->config->get('payment_actinia_clientcodename'))
                ->setPrivateKey($this->config->get('payment_actinia_prkey'))
                ->chkPublicKey()
                ->invoiceCreate($paymentData)
                ->isSuccessException()
                ->getData();

            $data['url'] = $resData['link'];
            $data['actinia_data'] = $paymentData;


            $data['button_confirm'] = $this->language->get('button_confirm');
            $data['confirm_pretext'] = $this->language->get('order_confirm_pretext');
//            $this->load->model('checkout/order');
//            $this->model_checkout_order
//                ->addOrderHistory($order_id, $this->config->get('payment_actinia_order_status_id'), 'actinia pending', $notify = true, $override = false);


        } catch (Exception $e) {
            $data['actinia_data']['message'] = $e->getMessage();

        }


        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/actinia')) {
            return $this->load->view($this->config->get('config_template') . '/template/extension/payment/actinia', $data);

        } else {

            return $this->load->view('/extension/payment/actinia', $data);
        }
    }

    /**
     *
     */
    public function response()
    {
        $this->language->load('payment/actinia');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/actinia');

        $this->cart->clear();

        $backref = $this->url->link('checkout/success', '', true);
        $this->response->redirect($backref);
    }

    /**
     *
     */
    public function callback()
    {
        $callback = [];
        try {
            $callback = (array)json_decode(file_get_contents("php://input", true));
            if (empty($callback)) {
                throw new Exception('callback empty');
            }

            $this->load->model('extension/payment/actinia');
            $callback = $this->model_extension_payment_actinia->decodeJsonObjToArr($callback, true);
            $payment = $this->model_extension_payment_actinia
                ->setClientCodeName($this->config->get('payment_actinia_clientcodename'))
                ->setPrivateKey($this->config->get('payment_actinia_prkey'))
                ->chkPublicKey()
                ->isPaymentValid($callback);

            if ($payment['merchantId'] !== $this->config->get('payment_actinia_merchant')) {
                throw new Exception('not valid merchantId (|' . $payment['merchantId'] . ' | ' . $this->config->get('payment_actinia_merchant') . '|)');
            }

            list($order_id,) = explode(self::ORDER_SEPARATOR, $payment['externalId']);
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($order_id);

            if (!$order_info)
                throw new Exception('Order not found: ' . $order_id);

            $total = round($order_info['total'] * $order_info['currency_value'], 2);


            if ($payment['status'] == self::ORDER_APPROVED and $total == $payment['amount']) {
                $comment = "Actinia payment id : " . $payment['paymentId'];
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_actinia_order_status_id'), $comment, $notify = true, $override = false);

            } else if ($payment['status'] == self::ORDER_PROCESSING) {
                $comment = "Actinia payment id : " . $payment['paymentId'] . ' pending';
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_actinia_order_process_status_id'), $comment, $notify = false, $override = false);

            } else {
                $comment = "Payment cancelled";
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_actinia_order_cancelled_status_id'), $comment, $notify = false, $override = false);
            }

            $res = ['status' => 'ok'];

        } catch (Exception $e) {
            $res = [
                'status' => 'error',
                'msg' => $e->getMessage()
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($res));

    }


    /**
     * @param $telephone
     * @return string
     */
    protected function preparePhone($telephone)
    {
        $_originalLength = 12;
        $_templ = '380';
        $phone = preg_replace('/[^\d]/', '', $telephone);
        $_l = $_originalLength - strlen($phone);
        $phone = substr($_templ, 0, $_l) . $phone;
        return $phone;
    }

}

?>