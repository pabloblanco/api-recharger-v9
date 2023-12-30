<?php
namespace App\Helpers;

class Common
{
	function __construct($bd = false) {
    	if($bd)
    		$this->bd = $bd;
   	}

	//retorna datos de autenticacion basica
	public function getAuthBasic($req = false){
		//print_r($req->getHeaders());
		if($req){
			$authUser = $req->getHeader('PHP_AUTH_USER');
	        $authPass = $req->getHeader('PHP_AUTH_PW');
	        $type = 'basic';
	        $rha = $req->getHeader('REDIRECT_HTTP_AUTHORIZATION');
	        $rha = (!empty($rha) && is_array($rha)) ? $rha[0] : $rha;
	        $ha = $req->getHeader('HTTP_AUTHORIZATION');
	        $ha = (!empty($ha) && is_array($ha)) ? $ha[0] : $ha;

			if(!empty($rha)){
	        	if (strpos(strtolower($rha),'basic') === 0){
	        		$al = explode(':',base64_decode(substr($rha, 6)));
	        		if(count($al) > 1){
	                	list($authUser,$authPass) = explode(':',base64_decode(substr($rha, 6)));
	        		}else{
	                	$authUser = base64_decode(substr($rha, 6));
	                	$authPass = false;
	                }
	        	}
	            if(strpos(strtolower($rha),'bearer') === 0){
	            	$authUser = substr($rha, 7);
	            	$authPass = false;
	            	$type = 'bearer';
	            }
	        }elseif(!empty($ha)){
	        	if (strpos(strtolower($ha),'basic') === 0){
	        		$al = explode(':',base64_decode(substr($ha, 6)));
	        		if(count($al) > 1){
	                	list($authUser,$authPass) = explode(':',base64_decode(substr($ha, 6)));
	        		}else{
	                	$authUser = base64_decode(substr($ha, 6));
	                	$authPass = false;
	                }
	        	}
	            if(strpos(strtolower($ha),'bearer') === 0){
	            	$authUser = substr($ha, 7);
	            	$authPass = false;
	            	$type = 'bearer';
	            }
	        }

	        if(!empty($authUser)){
		        $response = new \stdClass;
		        $response->authUser = is_array($authUser) ? $authUser[0] : $authUser;
		        $response->authPass = is_array($authPass) ? $authPass[0] : $authPass;
		        $response->authtype = $type;
		        return $response;
		    }
		}
        return false;
    }

    //Envia notificación al slack
    public function sendSlackNotification2($message = '', $type = 'alert', $data = [], $request = false, $webHook = false){

    	$send = [
    		'text' => 'Mensaje de notificación',
    		'attachments' => [[
    			'footer' => 'Fecha de la notificación',
    			'ts' => time(),
    			'color' => $type == 'alert' ? 'danger' : 'good',
    			'pretext' => $message,
    			'fields' => [
    				[
	    				'title' => 'Host',
	    				'value' => !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'],
	    				'short' => false
    				]
    			]
    		]]
    	];

    	if($request){
    		if(!empty($request->getServerParam('REMOTE_ADDR'))){
	    		$send['attachments'][0]['fields'] []= [
	    			'title' => 'IP',
					'value' => $request->getServerParam('REMOTE_ADDR'),
					'short' => false
	    		];
	    	}

	    	if(!empty($request->getMethod())){
	    		$send['attachments'][0]['fields'] []= [
	    			'title' => 'Method',
					'value' => $request->getMethod(),
					'short' => false
	    		];
	    	}

    		if(!empty($request->getUri())){
    			$uri = $request->getUri();

	    		$send['attachments'][0]['fields'] []= [
	    			'title' => 'URL',
					'value' => $uri->getBasePath().'/'.$uri->getPath(),
					'short' => false
	    		];
	    	}

	    	if(!empty($request->getHeaders())){
	    		$send['attachments'][0]['fields'] []= [
	    			'title' => 'Headers',
					'value' => (string)json_encode($request->getHeaders()),
					'short' => false
	    		];
	    	}

	    	if(!empty($request->getParsedBody())){
	    		$send['attachments'][0]['fields'] []= [
	    			'title' => 'Receive data',
					'value' => (string)json_encode($request->getParsedBody()),
					'short' => false
	    		];
	    	}
    	}

    	if(count($data)){
    		foreach ($data as $key => $value) {
    			$send['attachments'][0]['fields'] []= [
	    			'title' => $key,
					'value' => $value,
					'short' => false
	    		];
    		}
    	}

    	$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $webHook ? $webHook : slack,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode($send),
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
				'Cache-Control: no-cache',
				'Content-Type: application/json'
			]
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			return 'NOT_OK';
		} else {
			return 'OK';
		}
    }

    /*DEPRECATED*/
    public function sendSlackNotification($message = '', $type = 'alert', $data = false, $request = false){

    	$send = [
    		'text' => 'Mensaje de notificación',
    		'attachments' => [[
    			'footer' => 'Fecha de la notificación',
    			'ts' => time(),
    			'color' => $type == 'alert' ? 'danger' : 'good',
    			'pretext' => $message,
    			'fields' => [
    				[
	    				'title' => 'Host',
	    				'value' => !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'],
	    				'short' => false
    				]
    			]
    		]]
    	];

    	if($request){
    		if(!empty($request->getServerParam('REMOTE_ADDR'))){
	    		$send['attachments'][0]['fields'] []= [
	    			'title' => 'IP',
					'value' => $request->getServerParam('REMOTE_ADDR'),
					'short' => false
	    		];
	    	}

	    	if(!empty($request->getMethod())){
	    		$send['attachments'][0]['fields'] []= [
	    			'title' => 'Method',
					'value' => $request->getMethod(),
					'short' => false
	    		];
	    	}

    		if(!empty($request->getUri())){
    			$uri = $request->getUri();

	    		$send['attachments'][0]['fields'] []= [
	    			'title' => 'URL',
					'value' => $uri->getBasePath().'/'.$uri->getPath(),
					'short' => false
	    		];
	    	}

	    	if(!empty($request->getHeaders())){
	    		$send['attachments'][0]['fields'] []= [
	    			'title' => 'Headers',
					'value' => (string)json_encode($request->getHeaders()),
					'short' => false
	    		];
	    	}

	    	if(!empty($request->getParsedBody())){
	    		$send['attachments'][0]['fields'] []= [
	    			'title' => 'Receive data',
					'value' => (string)json_encode($request->getParsedBody()),
					'short' => false
	    		];
	    	}
    	}

    	if($data){
    		$send['attachments'][0]['fields'] []= [
    			'title' => 'Send data',
				'value' => $data,
				'short' => false
    		];
    	}

    	$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => slack,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode($send),
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
				'Cache-Control: no-cache',
				'Content-Type: application/json'
			]
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			return 'NOT_OK';
		} else {
			return 'OK';
		}

    }

    //funcion para registrar logs
    public function logV2($idLog, $data = NULL, $request = false, $msisdn = NULL, $time = 0, $message = NULL, $notify = 'NN', $error = NULL){
    	$env = isServerTest ? 'D' : 'P';

    	if($idLog !== false){
    		if($time > 0){
    			$totaltime = round((microtime(true) - $time), 2);
    		}else{
    			$totaltime = 0;
    		}

    		$sql = "UPDATE islim_log_recharge
    				SET time = :time, notify = :noti, data_out = :data, error = :error, message = :msg
    				WHERE id = :id";

    		$excSql = $this->bd->prepare($sql);
    		$excSql->bindParam(':id', $idLog);
			$excSql->bindParam(':time', $totaltime);
			$excSql->bindParam(':noti', $notify);
			$excSql->bindParam(':data', $data);
			$excSql->bindParam(':error', $error);
			$excSql->bindParam(':msg', $message);

			return $excSql->execute();
    	}else{
    		$auth = 'S/N';
    		$date = date("Y-m-d H:i:s");
    		$ip = NULL;
    		$req = NULL;
    		$head = NULL;

    		if($request){
    			$ip = $request->getServerParam('REMOTE_ADDR');
    			$req = $request->getUri();
    			$req = $req->getPath();
    			$head = (string)json_encode($request->getHeaders());
    			$auth = $this->getAuthBasic($request);
    			if(!empty($auth)){
    				$auth = $auth->authtype.' '.$auth->authUser;
    			}else{
    				$auth = 'S/N';
    			}
    		}

			$sql = "INSERT INTO islim_log_recharge
					(ip, auth, request, msisdn, headers, data_in, error, message, date_reg, notify, env)
					VALUES (:ip, :auth, :req, :dn, :head, :data, :err, :msg, :date, :noti, :env)";

			$excSql = $this->bd->prepare($sql);
			$excSql->bindParam(':ip', $ip);
			$excSql->bindParam(':auth', $auth);
			$excSql->bindParam(':req', $req);
			$excSql->bindParam(':dn', $msisdn);
			$excSql->bindParam(':head', $head);
			$excSql->bindParam(':data', $data);
			$excSql->bindParam(':err', $error);
			$excSql->bindParam(':msg', $message);
			$excSql->bindParam(':date', $date);
			$excSql->bindParam(':noti', $notify);
			$excSql->bindParam(':env', $env);

			$excSql->execute();

			return $this->bd->lastInsertId();
    	}
    }

    //funcion para registrar logs DEPRECATED
    public function log($data = false, $request = false, $action = 'in', $type = 'dev'){
    	if($data && $request){
    		$ip = $request->getServerParam('REMOTE_ADDR');
    		$date = date("Y-m-d H:i:s");
    		$uri = $request->getUri();
    		$head = (string)json_encode($request->getHeaders());
    		$auth = $this->getAuthBasic($request);//$uri->getUserInfo();
    		$user = '';
    		if(!empty($auth))
    			$user = $auth->authtype.' '.$auth->authUser;
    		$path = $uri->getPath();

    		//guardando log.
    		$sqlLog = "INSERT INTO islim_logs (ip, user, action, type_log, request, header, data, date_reg) VALUES (:ip, :user, :action, :type, :request, :header, :data, :date_reg)";
    		$excSqlLog = $this->bd->prepare($sqlLog);
    		$excSqlLog->bindParam(':ip', $ip);
    		$excSqlLog->bindParam(':user', $user);
    		$excSqlLog->bindParam(':action', $action);
    		$excSqlLog->bindParam(':type', $type);
    		$excSqlLog->bindParam(':request', $path);
    		$excSqlLog->bindParam(':header', $head);
    		$excSqlLog->bindParam(':data', $data);
    		$excSqlLog->bindParam(':date_reg', $date);

    		return $excSqlLog->execute();
    	}else{
    		if(!empty($data)){
    			$date = date("Y-m-d H:i:s");
    			$sqlLog = "INSERT INTO islim_logs (ip, user, action, type_log, request, header, data, date_reg) VALUES (:ip, :user, :action, :type, :request, :header, :data, :date_reg)";
	    		$excSqlLog = $this->bd->prepare($sqlLog);
	    		$excSqlLog->bindParam(':user', $user);
	    		$excSqlLog->bindParam(':action', $action);
	    		$excSqlLog->bindParam(':type', $type);
	    		$excSqlLog->bindParam(':request', 'desde un metodo');
	    		$excSqlLog->bindParam(':data', $data);
	    		$excSqlLog->bindParam(':date_reg', $date);

    		}
    	}
    }

    //Devuelve diferencia de tiempo en segundos dada una fecha ('Y-m-d H:i:s')
    public function getDiffTime($date = ''){
    	if(!empty($date)){
    		$now = time();

			$date = new DateTime($date);
			$dateSeconds = $date->getTimestamp();

			$dif = $now - $dateSeconds;

			return $dif > 0 ? $dif : 0;
    	}
    }

    //Crea una nueva session
    function createdNewSession($data = false, $isExpired = false){
		if(is_object($data)){
			//inhabilitando todas las sessiones activas
			if($isExpired){
				$sqlNewSession = "UPDATE islim_sessions SET status = 'I' WHERE concentrators_id = :cid AND id = :id";
				$excSqlNewSession = $this->bd->prepare($sqlNewSession);
				$excSqlNewSession->bindParam(':cid', $data->concentrators_id);
				$excSqlNewSession->bindParam(':id', $isExpired);
				$excSqlNewSession->execute();
			}

			//Creando una nueva session
			$tokenNew = strtoupper($data->key.str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789").uniqid());
			$date = date("Y-m-d H:i:s");
			$ttl = ttl;

			$sqlNewSession = "INSERT INTO islim_sessions (concentrators_id, api_key, token, ttl, date_reg, status, ip) VALUES (:cid, :key, :token, :ttl, :dateR, 'A', :ip)";
			$excSqlSession = $this->bd->prepare($sqlNewSession);
			$excSqlSession->bindParam(':cid', $data->concentrators_id);
			$excSqlSession->bindParam(':key', $data->key);
			$excSqlSession->bindParam(':token', $tokenNew);
			$excSqlSession->bindParam(':ttl', $ttl);
			$excSqlSession->bindParam(':dateR', $date);
			$excSqlSession->bindParam(':ip', $data->ip);
			$excSqlSession->execute();

			$response = new \stdClass;
			$response->token = $tokenNew;
			$response->ttl = $ttl;
			$response->createdAt = $date;

			return $response;
		}
		return false;
	}

	//Retorna token y key de un token de session compuesto.
	function getKeyFromTokenSession($token = false){
		if($token){
			$tokenSession = substr($token,strlen($token)-49,strlen($token));
			$key = substr($token,0,strlen($token)-49);

			$response = new \stdClass;
			$response->key = $key;
			$response->token = $tokenSession;

			return $response;
		}
		return false;
	}

	//Revierte el proceso de una compra luego de pasar por el segundo paso. Necesita le numero de transaccion y el id del concentrador
	function revertPayment($transaction = false, $idConc = false){
		if($transaction && $idConc){
			$sqlSale = "SELECT id, amount FROM islim_sales WHERE unique_transaction = :transaction AND concentrators_id = :id";
			$excSqlSale = $this->bd->prepare($sqlSale);
			$excSqlSale->bindParam(':transaction', $transaction);
			$excSqlSale->bindParam(':id', $idConc);
			$excSqlSale->execute();
			$dataSale = $excSqlSale->fetch();

			if(!empty($dataSale)){
				$sqlSale = "UPDATE islim_sales SET status = 'Trash' WHERE id = :id";
				$excSqlSale = $this->bd->prepare($sqlSale);
				$excSqlSale->bindParam(':id', $dataSale['id']);
				$excSqlSale->execute();

				$sqlConc = "SELECT balance FROM islim_concentrators WHERE id = :id";
				$excSqlConc = $this->bd->prepare($sqlConc);
				$excSqlConc->bindParam(':id', $idConc);
				$excSqlConc->execute();
				$dataConc = $excSqlConc->fetch();

				if(!empty($dataConc)){
					$newBalance = $dataConc['balance'] + $dataSale['amount'];
					$sqlConc = "UPDATE islim_concentrators SET balance = :newBalance WHERE id = :id";
					$excSqlConc = $this->bd->prepare($sqlConc);
					$excSqlConc->bindParam(':newBalance', $newBalance);
					$excSqlConc->bindParam(':id', $idConc);
					$excSqlConc->execute();
				}
			}
		}
		return false;
	}

	function getAltanResponseTest($test = false){
		if($test)
			return uniqid();
		return false;
	}

	//Retorna true si el ancho de banda que se quiere activar es permitido para el usuario
	Public static function compareWide($newWide = false, $serviceWide = false, $equal = false){
		if($newWide && $serviceWide){
			$newWide = $this->getWide($newWide);
			$serviceWide = $this->getWide($serviceWide);
			if($equal)
				return ($newWide != 0 && $serviceWide != 0 && $newWide == $serviceWide);
			return ($newWide != 0 && $serviceWide != 0 && $newWide > $serviceWide);
		}
		return false;
	}

	//retornar un entero verificando los ultimos tres o dos caracteres de una cadena
	function getWide($wide){
		$wide = substr($wide,strlen($wide)-3,strlen($wide));
		if(is_numeric($wide)) return (int)$wide;
		else{
			$wide = substr($wide,strlen($wide)-2,strlen($wide));
			if(is_numeric($wide)) return (int)$wide;
			else{
				$wide = substr($wide,strlen($wide)-1,strlen($wide));
				if(is_numeric($wide)) return (int)$wide;
			}
		}
		return 0;
	}

	//Funcion para envio de sms
	//$msisdn -> numero al que se le realizo la recarga
	//$concentratos_id -> id del concentrador
	//$service -> titulo del servicio que se activo
	function smsPush($msisdn, $concentratos_id = 1, $service){
		$curl = curl_init();
		$data = [
					"msisdn" => $msisdn,
					"service" => $service,
					"concentrator" => $concentratos_id,
					"type_sms" => "R"
				];

		$json = json_encode($data);
		$header = array(
				"Accept: application/json",
				"Cache-Control: no-cache",
				"Content-Type: application/json"
			);
		curl_setopt_array($curl, array(
			CURLOPT_URL => URL_SMS,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => $json,
			CURLOPT_HTTPHEADER => $header
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		return true;
	}

	//Funcion para envio de sms deprecate
	function __smsPush($msisdn, $concentratos_id = 1, $service){
		$curl = curl_init();
		$method = "POST";
		$sqlConc = "SELECT api_key FROM islim_api_keys WHERE concentrators_id = :id AND type ='prod'";
		$excSqlConc = $this->bd->prepare($sqlConc);
		$excSqlConc->bindParam(':id', $concentratos_id);
		$excSqlConc->execute();
		$dataConc = $excSqlConc->fetch();
		$json = array('apiKey' => $dataConc['api_key']);
		$json = json_encode($json);
		$header = array(
				"Accept: application/json",
				"Cache-Control: no-cache",
				"Content-Type: application/json"
			);
		curl_setopt_array($curl, array(
			CURLOPT_URL => URLAltan."profile/".$msisdn,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_POSTFIELDS => $json,//"{\n\t\"msisdn\": \"123456789\",\n\t\"seller\": \"123\",\n\t\"service\": 3\n}",
			CURLOPT_HTTPHEADER => $header
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		$responseAltan = json_decode($response);
		if($responseAltan->error || $responseAltan->data->status == 'error'){
			return false;
		}else{
			$supOffer = $this->lastSupOffers($responseAltan->msisdn->supplementaryOffers);
			$dExpire = new DateTime($supOffer->expireDate);
            $dateExpire = $date->format('Y-m-d');
			$gbBuy = number_format(($responseAltan->msisdn->{'remaining-mb'}/1024),2,'.','');
			$sqlClient = "SELECT name, phone_home FROM islim_client_views WHERE phone_netwey = :msisdn";
			$excSqlClient = $this->bd->prepare($sqlClient);
			$excSqlClient->bindParam(':msisdn', $msisdn);
			$excSqlClient->execute();
			$dataClient = $excSqlClient->fetch();
			$name = $dataClient['name'];
			$phoneHome = ($dataClient['phone_home'])? $dataClient['phone_home'] : flase;
			$txtsms = $this->i18n("MX","ES", "SMSRECHARGER");
			$smsview = str_replace("[NAME]", $name, $txtsms);
            $smsview = str_replace("[GB]", $gbBuy, $smsview);
            $smsview = str_replace("[DATE]", $dateExpire, $smsview);
            $smsview = str_replace("[SERVICE]", $service, $smsview);
			if($phoneHome){
				$curlSMS = curl_init();
				curl_setopt_array($curlSMS, array(
					CURLOPT_URL => URLSMS."&contacts=52".$msisdn."&msg=".$smsview,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 30,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "GET"
				));
				$response = curl_exec($curlSMS);
				$err = curl_error($curlSMS);
				curl_close($curlSMS);
				return true;
			}else
				return false;
		}
	}

	function lastSupOffers($offers = false){
        if($offers){
            $lastDate = 0;
            $element = false;
            $c = 0;
            foreach ($offers as $offer) {
                $date = new DateTime($offer->expireDate);
                $date = $date->getTimestamp();
                if($c == 0) $unusedAmt = 12345;
                else $unusedAmt = $offer->unusedAmt;
                if($lastDate < $date && $unusedAmt > 0){
                    $lastDate = $date;
                    $element = $offer;
                }
            }
            if($element) return $element;
        }
        return false;
    }


    function i18n($country = country_code, $lang = 'ES', $attrib){ # Internacionalización
	    try{
	        $i18nDB = $this->bd->prepare("SELECT * FROM islim_i18n WHERE country_code = :country AND language = :lang AND attribute = :att");
	        $i18nDB->bindParam(':country',$country);
	        $i18nDB->bindParam(':lang',$lang);
	        $i18nDB->bindParam(':att',$attrib);
	        $i18nDB->execute();
	        $message = $i18nDB->fetch();
	        if(is_array($message))
	            return $message['message'];
	        else{
	            return "Not Found...";
	        }

	    }catch(PDOException $e){
	        return "waiting please... $country $lang $attrib ".$e->getMessage();
	    }
	}

	//Ejecuta un curl de la API ALTAM
	function executeCurlAltan($url = false, $method = "POST", $json = false){
		$curl = curl_init();

		if($method == "POST"){
			$header = array(
				"Accept: application/json",
				"Cache-Control: no-cache",
				"Content-Type: application/json"
			);
		}elseif($method == "GET"){
			$header = array(
				"Cache-Control: no-cache"
			);
		}

		curl_setopt_array($curl, array(
			CURLOPT_URL => URLAltan.$url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_POSTFIELDS => $json,
			CURLOPT_HTTPHEADER => $header
			/*CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false*/
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		$res = new \stdClass;

		if ($err) {
			$res->error = true;
			$res->data = $err;
		} else {
			$resDe = json_decode($response);

			if(!empty($resDe)){
				$res->error = false;
				$res->data = $resDe;
				$res->original = $response;
			}else{
				$res->error = true;
				$res->data = 'No se pudo decodificar el JSON';
				$res->original = $response;
			}
		}
		return $res;
	}

	function getStatusDn($dn = false, $key = false){
		if($dn && $key){
			if(profile == 1){
				$url = "quickProfile/".$dn;
			}else{
				$url = "profile/".$dn;
			}

			$altan = $this->executeCurlAltan($url, "POST", json_encode(['apiKey' => $key]));

			if(!$altan->error && !empty($altan->data) && $altan->data->status == 'success' && !empty($altan->data->msisdn) && !empty($altan->data->msisdn->status)){
				return ['success' => true, 'status' => strtolower($altan->data->msisdn->status)];
			}
		}

		return ['success' => false, 'response' => !empty($altan->original) ? $altan->original : $altan->data];

	}

	//Limpiando textpo
	function cleanTxt($txt = ''){
		return !empty(trim($txt))? preg_replace('/\&(.)[^;]*;/', '\\1', htmlentities(trim($txt))) : null;
	}

  function getsupOffertFromPrimary($primary = false)
  {
    if ($primary) {
      $rel = [
        '1100501004' => '1200501004',
        '1100501007' => '1200501007',
        '1100501009' => '1200501009',
        '1100501010' => '1200501010',
        '1100501011' => '1200501011',
        '1100501012' => '1200501012',
        '1100501015' => '1200501025',
        '1100501016' => '1200501026',
        '1101001003' => '1201001003',
        '1100501018' => '1200501041'
      ];

      if (!empty($rel[$primary])) {
        return $rel[$primary];
      }
    }

    return null;
  }
}
