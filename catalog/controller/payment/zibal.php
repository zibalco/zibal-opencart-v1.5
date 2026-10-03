<?php
class ControllerPaymentZibal extends Controller {
    protected function index() {
        $this->language->load('payment/zibal');
        $this->data['button_confirm'] = $this->language->get('button_confirm');

        $this->data['text_wait'] = $this->language->get('text_wait');
        $this->data['text_ersal'] = $this->language->get('text_ersal');
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/zibal.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/payment/zibal.tpl';
        } else {
            $this->template = 'default/template/payment/zibal.tpl';
        }

        $this->render();
    }

    public function confirm() {
        $this->language->load('payment/zibal');
        $this->load->model('checkout/order');
        $this->load->model('payment/zibal');

        $json = array();

        $order_id = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;
        $order_info = $order_id ? $this->model_checkout_order->getOrder($order_id) : array();

        if (!$order_info) {
            $json['error'] = $this->language->get('error_order');
        } elseif (!empty($order_info['order_status_id'])) {
            $json['error'] = $this->language->get('error_paid');
        } elseif (!empty($order_info['payment_code']) && $order_info['payment_code'] != 'zibal') {
            $json['error'] = $this->language->get('error_method');
        } else {
            // amount and unit come from the currency of the order, not from the currency of the session
            $amount = $this->model_payment_zibal->getOrderAmount($order_info);

            if ($amount === false) {
                $json['error'] = $this->language->get('error_currency');
            } else {
                $parameters = array(
                    'merchant'    => $this->config->get('zibal_PIN'),
                    'amount'      => $this->toNumber($amount),
                    'orderId'     => $order_id,
                    'callbackUrl' => $this->url->link('payment/zibal/callback', '', 'SSL')
                );

                $response = $this->postToZibal('request', $parameters);
                $result = $response['data'];
                $track_id = ($result && isset($result->trackId)) ? $this->toDigits($result->trackId) : '';

                if ($result && isset($result->result) && (int)$result->result == 100 && preg_match('/^[0-9]{1,32}$/', $track_id)) {
                    // trackId is stored before the customer leaves, callback only accepts stored trackIds
                    $this->model_payment_zibal->addTransaction($order_id, $track_id, $amount);

                    // orders tried in this session, so a retry on a new order can point to the earlier attempts
                    $orders = (isset($this->session->data['zibal_orders']) && is_array($this->session->data['zibal_orders'])) ? $this->session->data['zibal_orders'] : array();

                    if (!in_array($order_id, $orders)) {
                        $orders[] = $order_id;
                    }

                    $this->session->data['zibal_orders'] = array_slice($orders, -10);

                    $lines = array($this->language->get('text_h_request'));
                    $lines[] = $this->language->get('text_h_track') . ': ' . $track_id;
                    $lines[] = $this->language->get('text_h_amount') . ': ' . $amount;
                    $lines[] = sprintf($this->language->get('text_h_attempt'), count($this->model_payment_zibal->getOrderTransactions(array($order_id))));
                    $lines = array_merge($lines, $this->previousAttempts($order_id, $track_id));

                    $this->model_payment_zibal->addOrderHistory($order_id, $this->getHistoryStatusId($order_info), implode("\n", $lines));

                    $json['success'] = 'https://gateway.zibal.ir/start/' . $track_id . ($this->config->get('zibal_direct') == '1' ? '/direct' : '');
                } else {
                    $lines = array($this->language->get('text_h_request_failed'));
                    $lines[] = $this->language->get('text_h_amount') . ': ' . $amount;
                    $lines = array_merge($lines, $this->describeResponse($response));

                    $this->model_payment_zibal->addOrderHistory($order_id, $this->getHistoryStatusId($order_info), implode("\n", $lines));

                    if ($result && isset($result->message) && is_scalar($result->message)) {
                        $json['error'] = sprintf($this->language->get('error_request'), (string)$result->message);
                    } else {
                        $json['error'] = $this->language->get('error_connect');
                    }
                }
            }
        }

        $this->response->setOutput(json_encode($json));
    }

    public function callback() {
        $this->language->load('payment/zibal');
        $this->load->model('checkout/order');
        $this->load->model('payment/zibal');

        $track_id = isset($this->request->get['trackId']) ? trim((string)$this->request->get['trackId']) : '';

        if (!preg_match('/^[0-9]{1,32}$/', $track_id)) {
            return $this->showResult(false, $this->language->get('text_pay_invalid'));
        }

        // the order is found by the stored trackId, orderId of the query string is not trusted
        $transaction = $this->model_payment_zibal->getTransaction($track_id);

        if (!$transaction) {
            return $this->showResult(false, $this->language->get('text_pay_invalid'));
        }

        $order_id = (int)$transaction['order_id'];

        // already finished: calling callback again changes nothing
        if ($transaction['status'] == 'paid') {
            return $this->showResult(true);
        }

        if ($transaction['status'] == 'review') {
            return $this->showResult(false, $this->language->get('text_pay_review'));
        }

        if (!$this->model_payment_zibal->lockTransaction($track_id)) {
            $transaction = $this->model_payment_zibal->getTransaction($track_id);

            if ($transaction && $transaction['status'] == 'paid') {
                return $this->showResult(true);
            }

            return $this->showResult(false, $this->language->get('text_pay_processing'));
        }

        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $this->model_payment_zibal->setTransactionStatus($track_id, 'failed');

            return $this->showResult(false, $this->language->get('text_pay_invalid'));
        }

        $response = $this->postToZibal('verify', array(
            'merchant' => $this->config->get('zibal_PIN'),
            'trackId'  => $track_id
        ));

        $result = $response['data'];
        $code = ($result && isset($result->result)) ? (int)$result->result : null;

        $callback_line = $this->language->get('text_h_callback') . ': success=' . (isset($this->request->get['success']) ? (int)$this->request->get['success'] : '-') . ', status=' . (isset($this->request->get['status']) ? $this->statusText($this->request->get['status']) : '-');

        $lines = array();
        $lines[] = $this->language->get('text_h_track') . ': ' . $track_id;

        if ($code === 100) {
            $expected = $transaction['amount'];
            $paid = isset($result->amount) ? $this->toDigits($result->amount) : '';

            $lines[] = $this->language->get('text_h_card') . ': ' . ((isset($result->cardNumber) && is_scalar($result->cardNumber)) ? $this->clean($result->cardNumber) : '-');
            $lines[] = $this->language->get('text_h_date') . ': ' . ((isset($result->paidAt) && is_scalar($result->paidAt)) ? $this->clean($result->paidAt) : '-');

            if (isset($result->refNumber) && is_scalar($result->refNumber)) {
                $lines[] = $this->language->get('text_h_ref') . ': ' . $this->clean($this->toDigits($result->refNumber));
            }

            $lines[] = $this->language->get('text_h_amount') . ': ' . $this->clean($paid);
            $previous = $this->previousAttempts($order_id, $track_id);

            if ($paid !== $expected || $this->model_payment_zibal->getOrderAmount($order_info) !== $expected || (isset($result->orderId) && trim((string)$result->orderId) !== (string)$order_id)) {
                // money is paid but it does not match this order, never confirm automatically
                array_unshift($lines, $this->language->get('text_h_mismatch'));
                $lines[] = $this->language->get('text_h_expected') . ': ' . $expected;
                $lines[] = $this->language->get('text_h_raw') . ':';
                $lines[] = $this->clean(substr($response['raw'], 0, 10000));

                $this->model_payment_zibal->addOrderHistory($order_id, $this->getHistoryStatusId($order_info), implode("\n", $lines));
                $this->model_payment_zibal->setTransactionStatus($track_id, 'review');

                return $this->showResult(false, $this->language->get('text_pay_review'));
            }

            if (!empty($order_info['order_status_id'])) {
                // order is already confirmed (paid by another transaction), status must not change
                array_unshift($lines, $this->language->get('text_h_duplicate'));
                $lines = array_merge($lines, $previous);

                $this->model_payment_zibal->addOrderHistory($order_id, $order_info['order_status_id'], implode("\n", $lines));
                $this->model_payment_zibal->setTransactionStatus($track_id, 'paid');

                return $this->showResult(true);
            }

            $order_status_id = (int)$this->config->get('zibal_order_status_id');

            if (!$order_status_id) {
                $order_status_id = (int)$this->config->get('config_order_status_id');
            }

            array_unshift($lines, $this->language->get('text_h_success'));
            $lines = array_merge($lines, $previous);

            // the note is written directly because confirm() drops the comment in some 1.5 versions
            $this->model_payment_zibal->addOrderHistory($order_id, $order_status_id, implode("\n", $lines), true);
            $this->model_checkout_order->confirm($order_id, $order_status_id);
            $this->model_payment_zibal->setTransactionStatus($track_id, 'paid');

            unset($this->session->data['zibal_orders']);

            return $this->showResult(true);
        }

        $lines = array_merge($lines, $this->previousAttempts($order_id, $track_id));
        $lines[] = $callback_line;
        $lines = array_merge($lines, $this->describeResponse($response));

        if ($code === null) {
            // no valid answer from Zibal: the payment is not failed for sure, callback can be retried
            array_unshift($lines, $this->language->get('text_h_unknown'));

            $this->model_payment_zibal->addOrderHistory($order_id, $this->getHistoryStatusId($order_info), implode("\n", $lines));
            $this->model_payment_zibal->setTransactionStatus($track_id, 'pending');

            return $this->showResult(false, $this->language->get('text_pay_unknown'));
        }

        if ($code === 201) {
            // verified before but this order was never completed by this transaction
            array_unshift($lines, $this->language->get('text_h_review'));

            $this->model_payment_zibal->addOrderHistory($order_id, $this->getHistoryStatusId($order_info), implode("\n", $lines));
            $this->model_payment_zibal->setTransactionStatus($track_id, 'review');

            return $this->showResult(false, $this->language->get('text_pay_review'));
        }

        // why it failed: status of the verify answer, otherwise the status Zibal sent to callback
        if ($result && isset($result->status) && is_scalar($result->status)) {
            array_unshift($lines, $this->language->get('text_h_reason') . ': ' . $this->statusText($result->status));
        } elseif (isset($this->request->get['status'])) {
            array_unshift($lines, $this->language->get('text_h_reason') . ': ' . $this->statusText($this->request->get['status']));
        }

        array_unshift($lines, $this->language->get('text_h_failed'));

        $this->model_payment_zibal->addOrderHistory($order_id, $this->getHistoryStatusId($order_info), implode("\n", $lines));
        $this->model_payment_zibal->setTransactionStatus($track_id, 'failed');

        return $this->showResult(false, $this->language->get('text_pay_failed'));
    }

    /**
     * status label of a history note, the order status itself is not changed by a note
     */
    private function getHistoryStatusId($order_info) {
        if (!empty($order_info['order_status_id'])) {
            return (int)$order_info['order_status_id'];
        }

        return (int)$this->config->get('config_order_status_id');
    }

    /**
     * everything Zibal answered, with the exact received body
     */
    private function describeResponse($response) {
        $lines = array();
        $result = $response['data'];

        if ($result && isset($result->result) && is_scalar($result->result)) {
            $lines[] = $this->language->get('text_h_result') . ': ' . $this->clean($result->result);
        }

        if ($result && isset($result->message) && is_scalar($result->message)) {
            $lines[] = $this->language->get('text_h_message') . ': ' . $this->clean($result->message);
        }

        if ($result && isset($result->status) && is_scalar($result->status)) {
            $lines[] = $this->language->get('text_h_status') . ': ' . $this->statusText($result->status);
        }

        if ($response['error'] !== '') {
            $lines[] = $this->language->get('text_h_curl_error') . ': ' . $this->clean($response['error']);
        }

        $lines[] = $this->language->get('text_h_http') . ': ' . (int)$response['http'];
        $lines[] = $this->language->get('text_h_raw') . ':';
        $lines[] = ($response['raw'] !== '') ? $this->clean(substr($response['raw'], 0, 10000)) : '-';

        return $lines;
    }

    private function clean($value) {
        $flags = ENT_QUOTES;

        if (defined('ENT_SUBSTITUTE')) {
            $flags = $flags | ENT_SUBSTITUTE;
        } elseif (defined('ENT_IGNORE')) {
            $flags = $flags | ENT_IGNORE;
        }

        return htmlspecialchars((string)$value, $flags, 'UTF-8');
    }

    private function toDigits($value) {
        if (is_float($value)) {
            return number_format($value, 0, '', '');
        }

        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function toNumber($digits) {
        if (PHP_INT_SIZE >= 8 || strlen($digits) < 10) {
            return (int)$digits;
        }

        return (float)$digits;
    }

    /**
     * Zibal status code with its meaning, e.g. "3 - cancelled by user"
     */
    private function statusText($status) {
        $status = (int)$status;
        $key = 'text_status_' . (($status < 0) ? 'm' . abs($status) : $status);
        $text = $this->language->get($key);

        return ($text != $key) ? $status . ' - ' . $text : (string)$status;
    }

    /**
     * other Zibal transactions of this order and of the orders tried before it in the same session
     */
    private function previousAttempts($order_id, $track_id) {
        $order_ids = array((int)$order_id);

        if (isset($this->session->data['zibal_orders']) && is_array($this->session->data['zibal_orders'])) {
            foreach ($this->session->data['zibal_orders'] as $value) {
                $order_ids[] = (int)$value;
            }
        }

        $lines = array();

        foreach ($this->model_payment_zibal->getOrderTransactions(array_unique($order_ids)) as $transaction) {
            if ($transaction['track_id'] == $track_id) {
                continue;
            }

            $lines[] = '- ' . $this->language->get('text_h_order') . ' #' . (int)$transaction['order_id'] . ' | trackId ' . $this->clean($transaction['track_id']) . ' | ' . $this->language->get('text_tx_' . $transaction['status']) . ' | ' . $this->clean($transaction['date_added']);
        }

        if ($lines) {
            array_unshift($lines, $this->language->get('text_h_previous') . ':');
        }

        return $lines;
    }

    /**
     * success goes straight to the success page of the store, anything else is shown inside the store theme
     */
    private function showResult($success, $message = '') {
        if ($success) {
            $this->redirect($this->url->link('checkout/success', '', 'SSL'));
        }

        $this->document->setTitle($this->language->get('text_pay_title'));

        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_home'),
            'href'      => $this->url->link('common/home'),
            'separator' => false
        );

        $this->data['heading_title'] = $this->language->get('text_pay_title');
        $this->data['text_error'] = $message;
        $this->data['button_continue'] = $this->language->get('button_continue');
        $this->data['continue'] = $this->url->link('checkout/cart');

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/error/not_found.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/error/not_found.tpl';
        } else {
            $this->template = 'default/template/error/not_found.tpl';
        }

        $this->children = array(
            'common/column_left',
            'common/column_right',
            'common/content_top',
            'common/content_bottom',
            'common/footer',
            'common/header'
        );

        $this->response->setOutput($this->render());
    }

    /**
     * connects to zibal's rest api
     * @param $path
     * @param $parameters
     * @return array raw: received body, error: cURL error, http: status code, data: decoded json (stdClass) or null
     */
    protected function postToZibal($path, $parameters)
    {
        $result = array(
            'raw'   => '',
            'error' => '',
            'http'  => 0,
            'data'  => null
        );

        if (!function_exists('curl_init')) {
            $result['error'] = 'PHP cURL extension is not installed';

            return $result;
        }

        $url = 'https://gateway.zibal.ir/v1/'.$path;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response  = curl_exec($ch);

        if ($response === false) {
            $result['error'] = curl_error($ch) . ' (' . curl_errno($ch) . ')';
        } else {
            $result['raw'] = (string)$response;
            $result['http'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $data = json_decode($response);

            if (is_object($data)) {
                $result['data'] = $data;
            }
        }

        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            curl_close($ch);
        }

        return $result;
    }


}
?>
