<?php

class mercadoPago{
	function __construct($key = false, $publicKey = false){
    	if($key)
    		$this->key = $key;

    	if($publicKey)
    		$this->publicKey = $publicKey;
   	}

	public function getPayment($payment_id = false){
		if($payment_id){
			\MercadoPago\SDK::setAccessToken($this->key);

			$payment = \MercadoPago\Payment::find_by_id($payment_id);

			if($payment){
				return $payment;
			}
		}

		return false;
	}

	public function getPaymentByExtRef($ref = false){
		if($ref){
			\MercadoPago\SDK::setAccessToken($this->key);
			$data ['url_query']=[
				"external_reference" => $ref
			];

	        $payment = \MercadoPago\SDK::get("/v1/payments/search", $data);
	        
	        if($payment){
				return $payment;
			}
		}

		return false;
	}
}