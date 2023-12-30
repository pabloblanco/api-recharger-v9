<?php
namespace App\Helpers;

use App\Models\Ninety_nine_logs;
use App\Models\Ninety_nine_tokenLife;
use App\Models\Request99;

class Curl
{
  public function __construct()
  {
    date_default_timezone_set('America/Mexico_City');
  }
  /**
   * [executeCurl description]
   * @param  boolean $url     [url o request a ejecutar]
   * @param  boolean $type    [tipo  de peticion: Get o Post]
   * @param  array   $header  [cabecera de la peticion]
   * @param  array   $data    [data enviada a 99min]
   * @param  [type]  $id_card [id del carrito asociado a la peticion]
   * @return [Array]           [resultado del request]
   */
  public static function executeCurl($url = false, $type = false, $header = [], $data = [], $id_card = null, $request)
  {
    if ($url && $type) {
      $startTime   = microtime(true);
      $SendRequest = true;
      $timeToke    = '';
      $curl        = curl_init();

      if (!count($header)) {

        if (strcmp($url, 'oauth/token') === 0) {
          $header = [
            "accept: */*",
            "Content-Type: application/json",
            "cache-control: no-cache",
            "accept-language: en-US,en;q=0.8",
          ];
        } else {
          $timeToke = Ninety_nine_tokenLife::getToken($request);
          if (!empty($timeToke)) {
            $header = [
              "Content-Type: application/json",
              "Authorization: " . $timeToke->tokenType . " " . $timeToke->token,
            ];
          } else {
            $SendRequest = false;
            $DataReturn  = [
              'success' => false,
              'data'    => "No se pudo obtener Token de 99min",
              'code'    => 400,
            ];
          }
        }
      }

      if ($SendRequest) {
        if (env('APP_ENV', 'local') == 'local') {
          //Deshabilito el ssl
          curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
          curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        }

        $options = [
          CURLOPT_URL            => env('URL_API99V3') . $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING       => "",
          CURLOPT_MAXREDIRS      => 10,
          CURLOPT_TIMEOUT        => 60,
          CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST  => $type,
          CURLOPT_HTTPHEADER     => $header,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_SSL_VERIFYHOST => false,
        ];

        if (is_array($data) && count($data)) {
          $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);

        curl_close($curl);
        $endTime = round((microtime(true) - $startTime), 2);

        $DataReturn = array();
        if ($err) {
          $DataReturn = [
            'success' => false,
            'data'    => $err,
            'code'    => !empty($httpcode) ? $httpcode : 0,
          ];
        } else {
          $dataJson = json_decode($response);

          if (!empty($dataJson)) {
            $DataReturn = [
              'success'  => true,
              'data'     => $dataJson,
              'original' => $response,
              'code'     => !empty($httpcode) ? $httpcode : 0,
            ];
          } else {
            $DataReturn = [
              'success'  => false,
              'data'     => 'No se pudo obtener json.',
              'original' => $response,
              'code'     => !empty($httpcode) ? $httpcode : 0,
            ];
          }
        }
      } else {
        //Fue un fallo del obtencion de token de 99min
        curl_close($curl);
        $endTime = round((microtime(true) - $startTime), 2);
      }
      $typeE = 'OK';
      $error = null;
      if (!$DataReturn['success']) {
        $typeE = 'ERROR';
        $error = $DataReturn['data'];
      }

      $requestTo99 = new Request99;
      $requestTo99->setIp($request->ip());
      $requestTo99->setBearerToken($request->bearerToken());
      $requestTo99->setMethod($type);
      $requestTo99->setUrl(env('URL_API99V3') . $url);
      $requestTo99->setMethodIntermedia($request->method());
      $requestTo99->setUrlIntermedia($request->url());

      if (strcmp($url, 'oauth/token') !== 0) {
        $requestTo99->setHeader($header);
        $requestTo99->setPath(env('APP_URL'));

      } else {
        $requestTo99->setHeader($request->header());
        $requestTo99->setPath(env('URL_API99V3'));
      }
      Ninety_nine_logs::saveLogBD($requestTo99, $data, $dataJson, $endTime, $typeE, $error, $id_card);

      return $DataReturn;
    }
    return ['success' => false, 'data' => 'Faltan datos.'];
  }

  //Ejecuta curl por post
  function executePost($data = false, $url = false, $header = []){
    if($data && $url && is_array($data)){
      $curl = curl_init();

      if(!count($header)){
        $header = [
          "Content-Type: application/json",
          "cache-control: no-cache"
        ];
      }

      curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $header,
      ));

      $response = curl_exec($curl);
      $err = curl_error($curl);
      $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

      curl_close($curl);

      if ($err) {
        return ['success' => false, 'data' => $err, 'code' => $httpcode];
      } else {
        return ['success' => true, 'data' => json_decode($response), 'code' => $httpcode];
      }
    }

    return ['success' => false, 'data' => 'Faltan datos'];
  }

}
