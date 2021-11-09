<?php 
class ModelExtensionPaymentActinia extends Model {

    const URL_TEST = 'https://api.clients.beta.actinia.tech/';
//    const URL_TEST = 'https://api.clients.sandbox.actinia.tech/';
    const URL_PROD = 'https://api.clients.actinia.tech/';

    const SESSION_PUBLICKEY_NAME = "actinia_publicKey";

    protected $url = '';
    protected $clientCodeName = '';
    protected $privateKey = null;
    protected $endpoint = null;
    protected $data = [];
    protected $success = true;
    protected $resultData = [];

    protected $hostPublicKey = null;
    protected $isHostPublicKey = true;

    const ENDPOINTS = [
        'invoiceCreate' => 'v1/invoice/create',
        'invoiceGet' => 'v1/invoice/get',
        'invoiceStatus' => 'v1/invoice/status',
        'invoiceCancel' => 'v1/invoice/cancel',

        'publicKeyGet' => 'v1/host/public/get',
    ];

    public function __construct($registry)
    {
        parent::__construct($registry);

        if($this->config->get('payment_actinia_testmode'))
            $this->url = self::URL_TEST;
        else
            $this->url = self::URL_PROD;

    }

    /**
     * @return $this
     * @throws Exception
     */
    public function publicKeyGet(){
        try{
            $this->setEndpoint('publicKeyGet');
            $this->data = [];
            $this->isHostPublicKey = false;
            $this->send();
            $_data = $this->resultData;

            if($_data['hostPublicKey'] ?? false){

                $_SESSION[self::SESSION_PUBLICKEY_NAME] = $this->hostPublicKey = $_data['hostPublicKey'];
                $this->isHostPublicKey = true;
                $this->success = true;
            } else {
                $this->success = false;
            }

            return $this;

        } catch (Exception $e){
            throw $e;
        }
    }

    /**
     * @param $val
     * @return $this
     */
    public function setPrivateKey($val){
        $this->privateKey = $val;
        return $this;
    }

    /**
     * @param string $val
     * @return $this
     */
    public function setClientCodeName(string $val){
        $this->clientCodeName = $val;
        return $this;
    }

    /**
     * @param string $val
     * @return $this
     */
    public function setEndpoint(string $val){
        if(isset(self::ENDPOINTS[$val]))
            $this->endpoint = self::ENDPOINTS[$val];
        else
            $this->endpoint = $val;

        return $this;
    }


    /**
     * @param array $data
     * @return $this
     * @throws Exception
     */
    public function invoiceCreate(array $data){
        try{
            $this->setEndpoint('invoiceCreate');
            $this->data = $data;
//            die( '<pre> invoiceCreate ' . print_r($this->data, true) . '<pre>');
            $this->send();

            $this->success = !empty($this->resultData);
            return $this;

        } catch (Exception $e){
//            echo ('<pre> invoiceCreate ' . print_r([$this->resultData, $e->getMessage()], true) . '<pre>');
            throw $e;
        }
    }


    /**
     * @return $this
     * @throws Exception
     */
    public function send(){
        try{
            $_data = (array) json_decode($this->sendToApi(), true);

            if(!empty($_data['data']) && !empty($_data['token'])) {
                if($this->isHostPublicKey)
                    $this->chkHostData($_data['data'], $_data['token']);
                $this->resultData = $_data['data'];
            }
            else
                throw new Exception('Empty data');

            return $this;

        } catch (Exception $e){
//            echo ('<pre> send ' . print_r($this->resultData, true) . '<pre>');
            throw $e;
        }
    }


    /**
     * @return string
     */
    public function getErrorMsg():string{
        $_code = $this->resultData['errorData']['code'] ?? false;

//        echo ('<pre> getErrorMsg' . print_r($this->resultData, true) . '<pre>');

        $_msg = $this->resultData['error'] ?? 'undefined';

        if($_code)
            $_msg = sprintf('%s: %s', $_code, $this->getMsgErrorByCode($_code));

        return $_msg;
    }

    /**
     * @return bool
     */
    public function isSuccess():bool{
        return $this->success;
    }

    /**
     * @return $this|bool
     * @throws Exception
     */
    public function isSuccessException(){

//        echo ('<pre> isSuccessException' . print_r($this->resultData, true) . '<pre>');

        if(!$this->success)
            throw new Exception($this->getErrorMsg());

        return $this;
    }

    /**
     * @return array
     */
    public function getData():array{
        return $this->resultData;// ?? [];
    }

    /**
     * @param $_data
     * @return array
     * @throws Exception
     */
    public function isPaymentValid($_data){
        try{
            if(!empty($_data['data']) && !empty($_data['token'])) {
                if($this->isHostPublicKey)
                    $this->chkHostData((array)$_data['data'], (string)$_data['token']);
                $this->resultData = $_data['data'];
            }
            else
                throw new Exception('Empty data');

            return (array)$_data['data'];

        } catch (Exception $e){
            throw new Exception('isPaymentValid: ' . $e->getMessage());
        }

    }

    /**
     * @param array $data
     * @param string $token
     * @return bool
     * @throws Exception
     */
    protected function chkHostData(array $data, string $token){
        try {
            $tokenDecode = (array)json_decode(json_encode($this->JWTdecode($token)), true);
            $res = array_diff($data, $tokenDecode);

            if (!empty($res))
                throw new Exception('Invalid JWT');

            return true;
        } catch (Exception $e){
            throw new Exception('chkHostData: ' . $e->getMessage());
        }
    }

    /**
     * @param string $jwt
     * @return array
     * @throws Exception
     */
    protected function JWTdecode(string $jwt):array{

        try{
            return (array) Firebase\JWT\JWT::decode($jwt, $this->hostPublicKey, ['RS256']);

        } catch (Exception $e){
            throw new Exception('JWTdecode: ' . $e->getMessage());
        }
    }

    /**
     * @param string $code
     * @return string
     */
    protected function getMsgErrorByCode(string $code):string{
        $errors = [
            'CS001' => 'Ошибка проверки входных параметров',
            'CS002' => 'Ошибка базы данных',
            'CS003' => 'Клиент не найден',

            'CS006' => 'Невозможно сохранить ключ',
            'CS007' => 'Неверный формат публичного ключа',

            'CAPI002' => 'Your have not loaded your public key. Please load it first.',
            "CAPI003" => "Token payload and request data do not much! Why?",
            'CAPI004' => 'Token validation error',
            'CAPI010' => 'Ошибка проверки входных параметров',
            'CAPI006' => 'Ошибка базы данных',
            'CAPI008' => 'Адрес уже добавлен в список разрешенных',
            'CAPI007' => 'Неизвестная ошибка системы',

            'I001' => 'Ошибка проверки входных параметров',
            'I002' => 'Ошибка базы данных',
            'I003' => 'Указанная валюта не поддерживается',
            'I004' => 'Ошибка получения информации о валюте счета',
            'I005' => 'Ошибка точности указания суммы счета',
            'I006' => 'Мерчант не найден',
            'I007' => 'Ошибка расчета тарифа для счета',
            'I008' => 'Ошибка создания номера счета',
            'I009' => 'Счет не найден',
            'I013' => 'Счета не может быть отменен, так как он был оплачен',
            'I014' => 'Счет не может быть отменен, так как сейчас совершается оплата',
            'I016' => 'Указанная валюта не поддерживается для этого мерчанта',
            'I020' => 'Указанная валюта не совпадает с указанным счетом',
            'I021' => 'Указанный счет клиента не найден',
            'I036' => 'Нет корневого счета для указанной валюты конвертации',
            'I037' => 'Конвертация на указанную валюту невозможна в настоящий момент',
            'I000' => 'Неизвестная ошибка системы',
        ];

        return $errors[$code] ?? 'undefined';
    }

    /**
     * @throws Exception
     */
    protected function sendToApi(){
        try{
            $fields = $this->prepareData();

            $ch = curl_init($this->url . $this->endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt( $ch, CURLOPT_HTTPHEADER, [
                'Content-Type:application/json'
            ]);

            $response=curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_errno= curl_errno($ch);
            curl_close($ch);

            if($http_status !== 200)
                throw new Exception('Server Error. Code: ' . $http_status . ' | ' . $curl_errno);

            return $response;

        } catch (Exception $e){
            $this->resultData = json_decode($response, true);
            throw new Exception($this->getErrorMsg());
        }
    }

    /**
     * @return false|string
     * @throws Exception
     */
    protected function prepareData(): string{
        try {
            $_obj = (object) $this->data;

            return json_encode([
                "auth" => [
                    "clientCodeName" => $this->clientCodeName,
                    "token" => $this->JWTencode($_obj),
                ],
                "data" => $_obj,
            ]);

        } catch (Exception $e){
            throw $e;
        }
    }

    /**
     * @param $data
     * @return string
     * @throws Exception
     */
    protected function JWTencode($data){
        try{
            require_once 'system/library/jwt/src/JWT.php';
            return Firebase\JWT\JWT::encode($data, $this->privateKey, 'RS256');

        } catch (Exception $e){
            throw $e;
        }
    }


    /**
     * @return $this
     * @throws Exception
     */
    public function chkPublicKey(){
        try{
            if(!empty($_SESSION[self::SESSION_PUBLICKEY_NAME]))
                $this->hostPublicKey = $_SESSION[self::SESSION_PUBLICKEY_NAME];
            else
                $this->publicKeyGet();

            return $this;

        } catch (Exception $e){
            throw $e;
        }
    }





// ---------------------------------------------------------------------------------------------------------------------
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/actinia');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_actinia_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if (!$this->config->get('payment_actinia_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}
		$method_data = [];

		if ($status) {
			$method_data = [
				'code' => 'actinia',
				'terms' => '',
				'title' => $this->language->get('text_title'),
				'sort_order' => $this->config->get('actinia_sort_order')
			];
		}
		return $method_data;
	}

}
?>