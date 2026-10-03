<?php 
class ControllerPaymentZibal extends Controller {

	private $error = array(); 

	public function index() {
		$this->language->load('payment/zibal');


		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');
			
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
			$this->load->model('setting/setting');
			
			$this->model_setting_setting->editSetting('zibal', $this->request->post);
			
			$this->session->data['success'] = $this->language->get('text_success');

			//$this->redirect(HTTPS_SERVER . 'index.php?route=extension/payment&token=' . $this->session->data['token']);
			$this->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
		}

		$this->data['heading_title'] = $this->language->get('heading_title');

		$this->data['text_enabled'] = $this->language->get('text_enabled');
		$this->data['text_disabled'] = $this->language->get('text_disabled');
		$this->data['text_yes'] = $this->language->get('text_yes');
		$this->data['text_no'] = $this->language->get('text_no');
		
		$this->data['entry_PIN'] = $this->language->get('entry_PIN');
		$this->data['entry_order_status'] = $this->language->get('entry_order_status');		
		$this->data['entry_status'] = $this->language->get('entry_status');
		$this->data['entry_sort_order'] = $this->language->get('entry_sort_order');
        $this->data['entry_zibal_direct'] = $this->language->get('entry_zibal_direct');

        $this->data['help_encryption'] = $this->language->get('help_encryption');
		
		$this->data['button_save'] = $this->language->get('button_save');
		$this->data['button_cancel'] = $this->language->get('button_cancel');

		$this->data['tab_general'] = $this->language->get('tab_general');
		
		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}
		
		if (isset($this->error['PIN'])) {
			$this->data['error_PIN'] = $this->error['PIN'];
		} else {
			$this->data['error_PIN'] = '';
		}

		//$this->document->breadcrumbs = array();
        $this->data['breadcrumbs'] = array();
   	$this->data['breadcrumbs'][] = array(
       		//'href'      => $this->url->https('common/home'),
			'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
       		'text'      => $this->language->get('text_home'),
      		'separator' => FALSE
   		);

   		$this->data['breadcrumbs'][] = array(
       		//'href'      => $this->url->https('extension/payment'),
			'href'      => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'),
       		'text'      => $this->language->get('text_payment'),
      		'separator' => ' :: '
   		);

   		$this->data['breadcrumbs'][] = array(
       		//'href'      => $this->url->https('payment/sb24'),
			'href'      => $this->url->link('payment/zibal', 'token=' . $this->session->data['token'], 'SSL'),
       		'text'      => $this->language->get('heading_title'),
      		'separator' => ' :: '
   		);
			
				
			$this->data['action'] = $this->url->link('payment/zibal', 'token=' . $this->session->data['token'], 'SSL');
		
		  $this->data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');

		if (isset($this->request->post['zibal_PIN'])) {
			$this->data['zibal_PIN'] = $this->request->post['zibal_PIN'];
		} else {
			$this->data['zibal_PIN'] = $this->config->get('zibal_PIN');
		}
		
		
		if (isset($this->request->post['zibal_order_status_id'])) {
			$this->data['zibal_order_status_id'] = $this->request->post['zibal_order_status_id'];
		} else {
			$this->data['zibal_order_status_id'] = $this->config->get('zibal_order_status_id');
		}


        if (isset($this->request->post['zibal_direct'])) {
            $this->data['zibal_direct'] = $this->request->post['zibal_direct'];
        } else {
            $this->data['zibal_direct'] = $this->config->get('zibal_direct');
        }


        $this->load->model('localisation/order_status');
		
		$this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		
		if (isset($this->request->post['zibal_status'])) {
			$this->data['zibal_status'] = $this->request->post['zibal_status'];
		} else {
			$this->data['zibal_status'] = $this->config->get('zibal_status');
		}
		
		if (isset($this->request->post['zibal_sort_order'])) {
			$this->data['zibal_sort_order'] = $this->request->post['zibal_sort_order'];
		} else {
			$this->data['zibal_sort_order'] = $this->config->get('zibal_sort_order');
		}
		
		$this->template = 'payment/zibal.tpl';
		$this->children = array(
			'common/header',
			'common/footer'
		);

		//$this->response->setOutput($this->render(TRUE), $this->config->get('config_compression'));
		$this->response->setOutput($this->render());
	}

	private function validate() {

		if (!$this->user->hasPermission('modify', 'payment/zibal')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		
		if (empty($this->request->post['zibal_PIN'])) {
			$this->error['PIN'] = $this->language->get('error_PIN');
		}

		
		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}	
	}
}
?>
