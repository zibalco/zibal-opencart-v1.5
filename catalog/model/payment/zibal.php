<?php
class ModelPaymentZibal extends Model {

	private $table_ready = false;

  	public function getMethod($address = array(), $total = 0) {
		$this->language->load('payment/zibal');

		if ($this->config->get('zibal_status') && $this->getCurrencyUnit($this->currency->getCode())) {
      		  	$status = TRUE;
      	} else {
			$status = FALSE;
		}

		$method_data = array();

		if ($status) {
      		$method_data = array(
        		'code'         => 'zibal',
        		'title'      => $this->language->get('text_title'),
				'sort_order' => $this->config->get('zibal_sort_order')
      		);
    	}

    	return $method_data;
  	}

	/**
	 * Only Rial and Toman are payable.
	 * @return string|bool 'rial', 'toman' or FALSE for any other currency
	 */
	public function getCurrencyUnit($code) {
		$code = strtoupper(trim((string)$code));

		if (in_array($code, array('IRR', 'RLS'))) {
			return 'rial';
		}

		if (in_array($code, array('TOM', 'IRT', 'TMN'))) {
			return 'toman';
		}

		return FALSE;
	}

	/**
	 * Order total in Rial as a string of digits, calculated from the currency of the order itself.
	 * @return string|bool FALSE if the order currency is not Rial/Toman
	 */
	public function getOrderAmount($order_info) {
		$unit = $this->getCurrencyUnit(isset($order_info['currency_code']) ? $order_info['currency_code'] : '');

		if (!$unit) {
			return FALSE;
		}

		$amount = (float)$order_info['total'];

		if (isset($order_info['currency_value']) && (float)$order_info['currency_value'] > 0) {
			$amount = $amount * (float)$order_info['currency_value'];
		}

		$amount = round($amount);

		if ($unit == 'toman') {
			$amount = $amount * 10;
		}

		return number_format($amount, 0, '', '');
	}

	public function addTransaction($order_id, $track_id, $amount) {
		$this->createTable();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "zibal_transaction` SET order_id = '" . (int)$order_id . "', track_id = '" . $this->db->escape($track_id) . "', amount = '" . $this->db->escape($amount) . "', status = 'pending', date_added = NOW(), date_modified = NOW()");
	}

	public function getTransaction($track_id) {
		$this->createTable();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zibal_transaction` WHERE track_id = '" . $this->db->escape($track_id) . "'");

		return $query->row;
	}

	public function getOrderTransactions($order_ids) {
		$this->createTable();

		$ids = array();

		foreach ($order_ids as $order_id) {
			$ids[] = (int)$order_id;
		}

		if (!$ids) {
			return array();
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zibal_transaction` WHERE order_id IN (" . implode(',', $ids) . ") ORDER BY zibal_transaction_id ASC");

		return $query->rows;
	}

	/**
	 * Takes the transaction for processing. Only one concurrent callback can win this update.
	 */
	public function lockTransaction($track_id) {
		$this->createTable();

		$this->db->query("UPDATE `" . DB_PREFIX . "zibal_transaction` SET status = 'processing', date_modified = NOW() WHERE track_id = '" . $this->db->escape($track_id) . "' AND (status IN ('pending', 'failed') OR (status = 'processing' AND date_modified < DATE_SUB(NOW(), INTERVAL 5 MINUTE)))");

		return ($this->db->countAffected() == 1);
	}

	public function setTransactionStatus($track_id, $status) {
		$this->db->query("UPDATE `" . DB_PREFIX . "zibal_transaction` SET status = '" . $this->db->escape($status) . "', date_modified = NOW() WHERE track_id = '" . $this->db->escape($track_id) . "'");
	}

	/**
	 * Adds a note to the order history without touching the order status.
	 */
	public function addOrderHistory($order_id, $order_status_id, $comment, $notify = false) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$order_status_id . "', notify = '" . ($notify ? 1 : 0) . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");
	}

	private function createTable() {
		if ($this->table_ready) {
			return;
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "zibal_transaction` (
			`zibal_transaction_id` int(11) NOT NULL AUTO_INCREMENT,
			`order_id` int(11) NOT NULL,
			`track_id` varchar(32) NOT NULL,
			`amount` varchar(20) NOT NULL,
			`status` varchar(16) NOT NULL,
			`date_added` datetime NOT NULL,
			`date_modified` datetime NOT NULL,
			PRIMARY KEY (`zibal_transaction_id`),
			UNIQUE KEY `track_id` (`track_id`),
			KEY `order_id` (`order_id`)
		) DEFAULT CHARSET=utf8");

		$this->table_ready = true;
	}
}
?>
