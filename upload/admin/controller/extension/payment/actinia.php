<?php

class ControllerExtensionPaymentActinia extends Controller
{
    protected $error = [];

    public function index()
    {
        $this->load->language('extension/payment/actinia');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('localisation/language');
//        $this->load->model('extension/payment/actinia');
        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            if (isset($this->error['bank' . $language['language_id']])) {
                $data['error_bank' . $language['language_id']] = $this->error['bank' . $language['language_id']];
            } else {
                $data['error_bank' . $language['language_id']] = '';
            }
        }

        //-- POST
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

//            move_uploaded_file($this->request->files['payment_actinia_prkey']['tmp_name'], DIR_DOWNLOAD . 'ActiniaPrivate.key');

            $this->model_setting_setting->editSetting('payment_actinia', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $arr = [
            "heading_title", "text_payment", "text_success", "text_pay", "text_card", 'entry_geo_zone', 'text_all_zones', 'test_mode',
            "entry_merchant", "entry_styles", "entry_clientcodename", "entry_order_status",
            "entry_currency", "entry_backref", "entry_server_back", "entry_language", "entry_status", "entry_order_status_cancelled",
            "entry_sort_order", "error_permission", "error_merchant", "error_clientcodename", 'text_edit', "entry_help_lang"
        ]; // , "entry_clientaccountid", "error_clientaccountid"

        foreach ($arr as $v)
            $data[$v] = $this->language->get($v);

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['entry_order_process_status'] = $this->language->get('entry_order_process_status');

        //-- ERROR
        $arr = ["warning", "merchant", "clientcodename", "type"]; //, "clientaccountid"
        foreach ($arr as $v)
            $data['error_' . $v] = (isset($this->error[$v])) ? $this->error[$v] : "";

        //-- BREADCRUMBS
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
            'separator' => false
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_payment'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true),
            'separator' => ' :: '
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/actinia', 'user_token=' . $this->session->data['user_token'], true),
            'separator' => ' :: '
        ];

        $data['action'] = $this->url->link('extension/payment/actinia', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        //--
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['payment_actinia_currencyc'] = [' ', 'EUR', 'USD', 'GBP', 'RUB', 'UAH'];
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $array_data = [
            "payment_actinia_prkey",
            "payment_actinia_merchant",
            "payment_actinia_clientcodename",
            "payment_actinia_backref",
            "payment_actinia_server_back",
            "payment_actinia_order_cancelled_status_id",
            'payment_actinia_geo_zone_id',
            "payment_actinia_language",
            "payment_actinia_status",
            "payment_actinia_sort_order",
            "payment_actinia_order_status_id",
            "payment_actinia_order_process_status_id",
            "payment_actinia_currency",
            "payment_actinia_testmode",
        ]; // "payment_actinia_clientaccountid",

        foreach ($array_data as $v) {
            $data[$v] = (isset($this->request->post[$v])) ? $this->request->post[$v] : $this->config->get($v);
        }

        //--
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/actinia', $data));
    }

    /**
     * @return bool
     */
    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/actinia')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_actinia_prkey']) {
            $this->error['prkey'] = $this->language->get('error_prkey');
        }
        if (!$this->request->post['payment_actinia_merchant']) {
            $this->error['merchant'] = $this->language->get('error_merchant');
        }
        if (!$this->request->post['payment_actinia_clientcodename']) {
            $this->error['clientcodename'] = $this->language->get('error_clientcodename');
        }
//        if (!$this->request->post['payment_actinia_clientaccountid']) {
//            $this->error['clientaccountid'] = $this->language->get('error_clientaccountid');
//        }
        return (!$this->error) ? true : false;
    }
}
