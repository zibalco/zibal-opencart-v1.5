<?php

// Text
$_['text_title'] = 'Zibal online payment';
$_['text_wait'] = 'Please wait!';
$_['text_ersal'] = 'Connecting to Zibal gateway...';
$_['text_continue'] = 'Continue';
$_['text_back'] = 'Back to store';

// Payment result (customer)
$_['text_pay_title'] = 'Payment result';
$_['text_pay_failed'] = 'The payment was not successful. You can try again if you wish.';
$_['text_pay_unknown'] = 'The payment result could not be received from the gateway. If the amount was deducted from your account, open this page again in a few minutes or contact the store support.';
$_['text_pay_review'] = 'This payment needs to be reviewed by the store. Please contact the store support.';
$_['text_pay_processing'] = 'This payment is being processed, please open this page again in a moment.';
$_['text_pay_invalid'] = 'The transaction is not valid.';

// Order history
$_['text_h_success'] = 'Zibal: payment successful';
$_['text_h_failed'] = 'Zibal: payment failed';
$_['text_h_request'] = 'Zibal: transaction created and customer was sent to the gateway (not paid yet)';
$_['text_h_request_failed'] = 'Zibal: creating the transaction failed';
$_['text_h_unknown'] = 'Zibal: verification result is unknown (no valid response received); order status was not changed';
$_['text_h_review'] = 'Zibal: transaction was already verified but this order was not completed by it; manual review needed';
$_['text_h_mismatch'] = 'Zibal: transaction is paid but does not match this order; the order was not confirmed and needs manual review';
$_['text_h_duplicate'] = 'Zibal: another successful payment was received for an already confirmed order; order status was not changed (duplicate payment, review needed)';
$_['text_h_track'] = 'Tracking code (trackId)';
$_['text_h_card'] = 'Card number';
$_['text_h_date'] = 'Transaction date';
$_['text_h_ref'] = 'Reference number';
$_['text_h_amount'] = 'Amount (Rial)';
$_['text_h_expected'] = 'Expected amount (Rial)';
$_['text_h_callback'] = 'Callback parameters';
$_['text_h_result'] = 'Result code';
$_['text_h_message'] = 'Zibal message';
$_['text_h_status'] = 'Transaction status';
$_['text_h_curl_error'] = 'cURL error';
$_['text_h_http'] = 'HTTP code';
$_['text_h_raw'] = 'Received response';

$_['text_h_attempt'] = 'Attempt number %s for this order';
$_['text_h_reason'] = 'Reason';
$_['text_h_previous'] = 'Other Zibal transactions of this customer in this purchase';
$_['text_h_order'] = 'Order';

// Transaction state
$_['text_tx_pending'] = 'no result (customer did not return from the gateway or the result is unknown)';
$_['text_tx_processing'] = 'processing';
$_['text_tx_failed'] = 'failed';
$_['text_tx_paid'] = 'paid';
$_['text_tx_review'] = 'needs manual review';

// Zibal status codes
$_['text_status_m1'] = 'waiting for payment';
$_['text_status_m2'] = 'internal error';
$_['text_status_1'] = 'paid - verified';
$_['text_status_2'] = 'paid - not verified';
$_['text_status_3'] = 'cancelled by user';
$_['text_status_4'] = 'card number is not valid';
$_['text_status_5'] = 'insufficient balance';
$_['text_status_6'] = 'wrong card password';
$_['text_status_7'] = 'too many requests';
$_['text_status_8'] = 'daily online payment count limit exceeded';
$_['text_status_9'] = 'daily online payment amount limit exceeded';
$_['text_status_10'] = 'card issuer is not valid';
$_['text_status_11'] = 'switch error';
$_['text_status_12'] = 'card is not accessible';

// Error
$_['error_order'] = 'Order not found. Please go through the checkout again.';
$_['error_paid'] = 'This order is already placed and confirmed.';
$_['error_method'] = 'The payment method of this order is not Zibal.';
$_['error_currency'] = 'Payment with Zibal is only possible in Rial or Toman.';
$_['error_request'] = 'Error connecting to Zibal gateway: %s';
$_['error_connect'] = 'Could not connect to Zibal gateway. Please try again.';
?>
