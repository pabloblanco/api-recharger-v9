<?php
/****************************************************************************************************************************
*   2021-2022 GDALab
*****************************************************************************************************************************
* 
*   NOTICE OF LICENSE
*
*
*   DISCLAIMER
*
*
*****************************************************************************************************************************
*
*   @author     GDALab <contact@gdalab.com>
*   @copyright  
*   @license    
*   @web        https://www.gdalab.com/
* 
*****************************************************************************************************************************
* Enum fields list
*****************************************************************************************************************************
*
*   islim_client_netweys.dn_type (Tipo de servicio que tiene ese dn) => Default = H
*
*     H   ->  hogar, 
*     T   ->  telefonia, 
*     M   ->  mifi, 
*     MH  ->  MIFI huella altan, 
*     F   ->  Fibra 815
*
*   islim_open_pay.status (Estado de la recarga) => Default = C
*
*     C   ->  creado, 
*     T   ->  trash, 
*     A   ->  aprobado, 
*     R   ->  cancelado,
*     P   ->  procesado, 
*     E   ->  error
*
*   islim_client_netweys.type_buy (Tipo de compra) => Default = null
*
*     CR   ->  Credito
*     CO   ->  Contado
* 
*****************************************************************************************************************************
* Method list
*****************************************************************************************************************************
* 
*   public function echo                    =>  Request para verificar estatus del servidor
*   public function getPayment              =>  Consulta pago dado un id de mercado pago
*   public function auth                    =>  Request para optener token de autenticación
*   public function statusRecharge          =>  Verifica estado de una recarga
*   public function step1                   =>  Primer paso para una recarga "verificacion de los datos."
*   public function verificationPayStep2    =>  Segundo paso para una recarga con comprobación de pago
*   public function step2                   =>  Segundo paso para una recarga "recarga o activacion del plan."
*   public function step2Seller             =>  Segundo paso para una recarga "recarga o activacion del plan."
*   public function balance                 =>  Obtener el saldo de un concentrador
*   public function doRecharge              =>  
*   public function resetRechargeProcess    =>  Verifica si el proceso de recarga tiene mas de un tiempo X ejcutandose y lo reinicia
*   public function activeRechargeProm      =>  Servicio que se ejecuta por cron 1 vez al día y activa recargas de promoción
*   public function extraRecharge           =>  Proceso que se ejecuta por cron, activa servicios "extras" (nav. nocturna) para las recargas
*   public function extraRegister           =>  Proceso que se ejecuta por cron, activa servicios "extras" (nav. nocturna) para las altas
*   public function sendAlertLogs           =>  Request que se debe ejecutar desde un cron cada minuto y envia las notificaciones al slack registradas en la tabla de logs
*   public function removeLogs              =>  Request que se debe ejecutar desde un cron una vez al dia preferiblemente a las 23:59
*   public function fileBluelabel           =>  Request para ser ejecutado desde un cron, genera archivo de conciliación para bluelabel
*   public function massiveRetention(email) =>  Carga masiva de servicios de rentención
*   public function processRetention        =>  Request para ejecutar desde cron, activa las solicitudes de servicio de rentención
*
*****************************************************************************************************************************/

namespace App\Http\Controllers;

use App\Models\APIKey;
use App\Models\ClientNetwey;
use App\Models\Concentrator;
use App\Models\Mobility;
use App\Models\OpenPay;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Suspend;
use Illuminate\Http\Request;
use Log;

/*
  * Class RechargerController.
  *
  * @OA\Info(
  *      version="9.0.0",
  *      title="API Recharger",
  *      description="API para gestionar las recargas",
  *      @OA\Contact(
  *          email="contact@gdalab.com"
  *      ),
  *     @OA\License(
  *         name="GDA Lab",
  *         url="https://www.gdalab.com/LICENSE-9.0.0.html"
  *     )
  * )
*/
class RechargerController extends Controller
{
/*
	* Request para verificar estatus del servidor
	*
	* @param Request $request
	*
	* @return mixed
	*
	* @throws
  * 
  * @OA\Get(
  *     path="/echo",
  *     tags={"Recargas"},
  *     operationId="echo", 
  *     summary="Test server status",   
  *     description="Request para verificar estatus del servidor",
  *     security={
  *         {"Bearer Token": {"Token:token"}}
  *     },
  *     @OA\Parameter(
  *         name="status",
  *         in="query",
  *         description="Status values that needed to be considered for filter",
  *         required=true,
  *         explode=true,
  *         @OA\Schema(
  *             default="available",
  *             type="string",
  *             enum={"available", "pending", "sold"},
  *         )
  *     ),
  *     @OA\RequestBody(
  *         description="Input data format",
  *         @OA\MediaType(
  *             mediaType="application/json",
  *             @OA\Schema(
  *                 type="object",
  *                 @OA\Property(
  *                     property="msisdn",
  *                     description="Updated name of the service",
  *                     type="string",
  *                 ),
  *                 @OA\Property(
  *                     property="seller",
  *                     description="Updated status of the service",
  *                     type="string"
  *                 )
  *             )
  *         )
  *     ),
  *     @OA\Response(
  *         response=400,
  *         description="Invalid ID supplied"
  *     ),
  *     @OA\Response(
  *         response=404,
  *         description="Page not found"
  *     ),
  *     @OA\Response(
  *         response=405,
  *         description="Validation exception"
  *     ),
  * )    
*/
	public function echo(Request $request)
    {
      $timeStart = microtime(true);
      $idLog     = $this->common->logV2(
        false,
        null,
        $request
      );
      if(env('SAVE_LOG'))
        Recharger_logs::saveLogBD('/inventary-99/{pc}', $request, false, false, 'INFO', false, false);

      //colocar aqui validacion con altam (ping)
      $token = $this->common->getAuthBasic($request);
      $url   = "serviceability/";
      $json  = array(
        'apiKey' => $token->authUser,
        'lng'    => '-99.1774201',
        'lat'    => '19.3952801');
      $json          = json_encode($json);
      $responseAltan = $this->model->executeCurlAltan($url, "POST", $json);

      if (!$responseAltan->error && $responseAltan->data->status == 'success') {
        $status = array('status' => 'OK', 'response' => 'We are alive');
      } else {
        $status = array('status' => 'FAIL', 'cod' => 'ERROR_COMMUNICATION', 'response' => 'Error de comunicación, intente más tarde.');
        $error  = !empty($responseAltan->original) ? $responseAltan->original : $responseAltan->data;
      }

      $this->common->logV2(
        $idLog,
        (String) json_encode($status),
        null,
        null,
        $timeStart,
        null,
        'NN',
        !empty($error) ? 'Servicialidad: ' . $error : null
      );

      return $response->withJson($status);
    }

/*
  * Consulta pago dado un id de mercado pago.
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Post(
  *     path="/get-payment",
  *     tags={"Recargas"},
  *     operationId="get-payment", 
  *     summary="Test server status",      
  *     description="Consulta pago dado un id de mercado pago.",
  *     security={
  *         {"Bearer Token": {"Token:token"}}
  *     },
  *     @OA\Parameter(
  *         name="status",
  *         in="query",
  *         description="Status values that needed to be considered for filter",
  *         required=true,
  *         explode=true,
  *         @OA\Schema(
  *             default="available",
  *             type="string",
  *             enum={"available", "pending", "sold"},
  *         )
  *     ),
  *     @OA\RequestBody(
  *         description="Input data format",
  *         @OA\MediaType(
  *             mediaType="application/json",
  *             @OA\Schema(
  *                 type="object",
  *                 @OA\Property(
  *                     property="msisdn",
  *                     description="Updated name of the service",
  *                     type="string",
  *                 ),
  *                 @OA\Property(
  *                     property="seller",
  *                     description="Updated status of the service",
  *                     type="string"
  *                 )
  *             )
  *         )
  *     ),
  *     @OA\Response(
  *         response=400,
  *         description="Invalid ID supplied"
  *     ),
  *     @OA\Response(
  *         response=404,
  *         description="Page not found"
  *     ),
  *     @OA\Response(
  *         response=405,
  *         description="Validation exception"
  *     ),
  * )    
*/
  public function getPayment(Request $request)
  {
    $timeStart = microtime(true);

    $data = $request->getParsedBody();

    $idLog = $this->common->logV2(
      false,
      (String) json_encode($data),
      $request
    );

    $res = ['status' => 'FAIL'];

    $payment_id = !empty($data['payment_id']) ? $data['payment_id'] : null;

    $payment_ref = !empty($data['reference']) ? $data['reference'] : null;

    if (!empty($payment_id) || !empty($payment_ref)) {
      if (!empty($payment_id)) {
        $paymp = $this->mp->getPayment($payment_id);

        if ($paymp) {
          $res['status']   = 'OK';
          $res['response'] = [
            'payment_data'     => [
              'status'             => $paymp->status,
              'status_detail'      => $paymp->status_detail,
              'external_reference' => $paymp->external_reference,
              'date_approved'      => $paymp->date_approved,
              'transaction_amount' => $paymp->transaction_amount,
              'card'               => $paymp->card ? $paymp->card->last_four_digits : 'S/I',
              'payment_method'     => $paymp->payment_method_id],
            'payment_all_data' => $paymp->toArray()];
        }
      }

      if (!empty($payment_ref)) {
        $paymp = $this->mp->getPaymentByExtRef($payment_ref);

        if ($paymp) {
          $res['status']   = 'OK';
          $res['response'] = ['payment_all_data' => $paymp];
        }
      }
    }

    if ($res['status'] != 'OK') {
      $res['cod'] = 'ERR_PAY';
      $res['msg'] = 'No se pudo consultar el pago';
    }

    $this->common->logV2(
      $idLog,
      (String) json_encode($res),
      null,
      null,
      $timeStart,
      null,
      'NN',
      $res['status'] != 'OK' ? $res['msg'] : null
    );

    return $response->withJson($res);
  }

/*
  * Request para optener token de autenticación
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Post(
  *     path="/auth",
  *     tags={"Recargas"},
  *     operationId="auth", 
  *     summary="Test server status",      
  *     description="Request para optener token de autenticación",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function auth(Request $request)
  {
    $timeStart = microtime(true);

    $idLog = $this->common->logV2(
      false,
      null,
      $request
    );

    $resA = ['status' => 'FAIL'];

    $user = $this->common->getAuthBasic($request);
    if ($user) {
      try {
        if (environment == 'pro') {
          $typeSession = 'prod';
        } else {
          $typeSession = 'test';
        }

        //Parche para poder hacer recargas tipo "produccion" en test
        if (!empty(isServerTest) && isServerTest) {
          $typeSession = 'test';
        }

        $IP = $request->getServerParam('REMOTE_ADDR');

        //Validando key del concentrador
        $sqlkey    = "SELECT concentrators_id FROM islim_api_keys WHERE api_key = :key AND status = 'A' AND type = :type";
        $excSqlKey = $this->db->prepare($sqlkey);
        $excSqlKey->bindParam(':key', $user->authUser);
        $excSqlKey->bindParam(':type', $typeSession);
        $excSqlKey->execute();
        $dataSqlKey = $excSqlKey->fetch();

        if (!empty($dataSqlKey)) {
          $sqlSession    = "SELECT id, ttl, token, date_reg FROM islim_sessions WHERE concentrators_id = :cid AND api_key = :key AND ip = :ip AND status = 'A'";
          $excSqlSession = $this->db->prepare($sqlSession);
          $excSqlSession->bindParam(':cid', $dataSqlKey['concentrators_id']);
          $excSqlSession->bindParam(':key', $user->authUser);
          $excSqlSession->bindParam(':ip', $IP);
          $excSqlSession->execute();
          $dataSQLSession = $excSqlSession->fetch();
          $createSession  = false;
          $isExpired      = false;
          if (!empty($dataSQLSession)) {
            $timeTtl = $this->common->getDiffTime($dataSQLSession['date_reg']);
            $ttl     = $dataSQLSession['ttl'];
            if ($ttl > $timeTtl) {
              $dataResponse = array('token' => $dataSQLSession['token'], 'ttl' => $ttl, 'createdAt' => $dataSQLSession['date_reg']);
            } else {
              $isExpired     = $dataSQLSession['id'];
              $createSession = true;
            }
          } else {
            $createSession = true;
          }

          if ($createSession) {
            //creando y retornando nueva session
            $dataNewSession                   = new \stdClass;
            $dataNewSession->concentrators_id = $dataSqlKey['concentrators_id'];
            $dataNewSession->key              = $user->authUser;
            $dataNewSession->ip               = $IP;
            $dataResponse                     = $this->common->createdNewSession($dataNewSession, $isExpired);
          }

          $resA = ['status' => 'OK', 'response' => $dataResponse];
        } else {
          //$errorBan = true;
          $resA = [
            'status' => 'FAIL',
            'cod'    => 'LOGIN_KEY_NOT_VALID',
            'msg'    => 'Datos no válidos para login.'];
        }
      } catch (PDOException $e) {
        $this->common->sendSlackNotification2('Error(BD): ' . $e->getMessage(), 'alert', [], $request);

        $resA = [
          'status' => 'FAIL',
          'cod'    => 'SYSTEM_FAILURE',
          'msg'    => 'Falla en el sistema.'];
      }
    } else {
      $resA = [
        'status' => 'FAIL',
        'cod'    => 'MISSING_DATA',
        'msg'    => 'Faltan datos.'];
    }

    $this->common->logV2(
      $idLog,
      (String) json_encode($resA),
      null,
      null,
      $timeStart,
      null,
      ($resA['status'] != 'OK' && !isServerTest) ? 'P' : 'NN',
      ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] : null
    );

    return $response->withJson($resA);
  }

/*
  * Verifica estado de una recarga
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Post(
  *     path="/status-recharge",
  *     tags={"Recargas"},
  *     operationId="status-recharge", 
  *     summary="Test server status",      
  *     description="Verifica estado de una recarga",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function statusRecharge(Request $request)
  {
    $timeStart = microtime(true);

    $data = $request->getParsedBody();

    $resA = ['status' => 'FAIL'];

    $number      = !empty($data['msisdn']) ? $data['msisdn'] : null;
    $transaction = !empty($data['transaction']) ? $data['transaction'] : null;

    $idLog = $this->common->logV2(
      false,
      (String) json_encode($data),
      $request,
      $number
    );

    if (!empty($number) && !empty($transaction)) {
      try {
        $token          = $this->common->getAuthBasic($request);
        $tokenBearer    = $this->common->getKeyFromTokenSession($token->authUser);
        $idConcentrator = $this->model->getIdConcentratorByKey($tokenBearer->key);

        $recharge = $this->model->getSale($transaction, $idConcentrator, $number);

        if ($recharge) {
          $status = 'Pending';

          if ($recharge['status'] == 'A') {
            $status = 'Active';
          }

          if ($recharge['status'] == 'I') {
            $status = 'Inactive';
          }

          $resA = [
            'status'   => 'OK',
            'response' => [
              'description'   => $recharge['description'],
              'amount'        => $recharge['amount'],
              'seller'        => $recharge['id_point'],
              'recharge_date' => $recharge['date_reg'],
              'status'        => $status]];
        } else {
          $resA = [
            'status' => 'FAIL',
            'cod'    => 'SALE_NOT_FOUND',
            'msg'    => 'No se consiguio la recarga.'];
        }
      } catch (PDOException $e) {
        $this->common->sendSlackNotification2('Error(BD): ' . $e->getMessage(), 'alert', [], $request);

        $resA = [
          'status' => 'FAIL',
          'cod'    => 'SYSTEM_FAILURE',
          'msg'    => 'Falla en el sistema.'];
      }
    } else {
      $resA = [
        'status' => 'FAIL',
        'cod'    => 'MISSING_DATA',
        'msg'    => 'Faltan datos.'];
    }

    $this->common->logV2(
      $idLog,
      (String) json_encode($resA),
      null,
      null,
      $timeStart,
      null,
      'NN',
      ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] : null
    );

    return $response->withJson($resA);
  }

/**
  * Primer paso para una recarga "verificacion de los datos."
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Post(
  *     path="/step1",
  *     tags={"Recargas"},
  *     operationId="step1", 
  *     summary="Test server status",      
  *     description="Primer paso para una recarga, verificacion de los datos.",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function step1(Request $request)
  {
    $timeStart  = microtime(true);
    $timeServ   = 0;
    $timeProf   = 0;
    $alertSlack = false;

    $resA = ['status' => 'FAIL'];

    $data     = $request->getParsedBody();
    $errorBan = false;

    $number  = !empty($data['msisdn']) ? $data['msisdn'] : null;
    $service = !empty($data['service']) ? $data['service'] : null;
    $seller  = !empty($data['seller']) ? $data['seller'] : null;
    $lat     = !empty($data['lat']) ? $data['lat'] : null;
    $lng     = !empty($data['lng']) ? $data['lng'] : null;

    $geo = null;

    if (!empty($data['lat']) && !empty($data['lng'])) {
      $geo = "POINT(" . $lat . " " . $lng . ")";
    }

    $token = $this->common->getAuthBasic($request);
    $date  = date("Y-m-d H:i:s");
    $ttl   = ttl_transaction;

    $idLog = $this->common->logV2(
      false,
      (String) json_encode($data),
      $request,
      $number
    );

    if (!empty($number) && !empty($seller)) {
      try {
        $dataNumber = $this->model->getDataNumber($number);

        if (!$dataNumber) {
          $resA = [
            'status' => 'FAIL',
            'cod'    => 'MSISDN_NOT_VALID',
            'msg'    => 'MSISDN no válido.'];
          $errorBan = true;
        } else {
          $tokenBearer         = $this->common->getKeyFromTokenSession($token->authUser);
          $idConcentrator      = $this->model->getIdConcentratorByKey($tokenBearer->key);
          $concentratorBalance = $this->model->getBalanceConcentrator($idConcentrator);
          $transactionNumber   = $this->model->getIdTransaction($idConcentrator);
          $typeService         = $dataNumber['type_buy'];
          $totalPayRemaining   = $this->model->getTotalPayCredit($dataNumber['msisdn'], $typeService);

          //Quitar este parche luego de acomodar la bd
          $dataNumber['msisdn'] = trim($dataNumber['msisdn']);

          //Aumentando ttl de transaccion a inconcert
          if ($idConcentrator == inconcert) {
            $ttl = (20 * 60);
          }

          if ($dataNumber['dn_type'] != 'F') {
            $timeProf = microtime(true);
            $statusDn = $this->common->getStatusDn($dataNumber['msisdn'], $tokenBearer->key);
            $timeProf = round((microtime(true) - $timeProf), 2);
          } else {
            //Fibra
            $statusDn['success'] = true;
            $statusDn['status']  = 'active';
          }
          $canBuyChHome  = $this->model->canBuyChangeHome($dataNumber);
          $canBuySuspend = $this->model->canBuySuspend($dataNumber);

          //validar estatus suspendida
          if (
            $statusDn['success'] &&
            (
              $statusDn['status'] == 'active' ||
              ($statusDn['status'] == 'suspend' && ($canBuyChHome || $canBuySuspend)) ||
              ($statusDn['status'] == 'barring' && $dataNumber['is_band_twenty_eight'] == 'N' && $dataNumber['is_suspend_by_b28'] == 'Y')
            )
          ) {
            //Validando si el cliente ya pago su deuda en caso de ser una compra a credito
            if ($dataNumber['total_debt'] <= $totalPayRemaining) {
              $typeService = 'CO';
            }

            if (empty($service)) {
              //Consultando ancho de banda disponible para el numero de la recarga
              if ($dataNumber['dn_type'] == 'H') {
                //Se consulta servicialidad solo si el dn pertenece a una lista y tiene servicialidad de 5mbps
                if (!empty($dataNumber['id_list_dns']) && $dataNumber["serviceability"] == 'broadband5') {
                  $servicesBroadband = $this->model->getServiceByList($dataNumber['id_list_dns']);
                  
                  $key = array_search('broadband10', array_column($servicesBroadband, 'broadband'));

                  if ($key !== false) {
                    $timeServ = microtime(true);

                    $url   = "serviceability/";
                    $jsonS = array(
                      'apiKey' => $tokenBearer->key,
                      'lng'    => $dataNumber['lng'],
                      'lat'    => $dataNumber['lat']);
                    $jsonS     = json_encode($jsonS);
                    $responseS = $this->model->executeCurlAltan($url, "POST", $jsonS);

                    $timeServ = round((microtime(true) - $timeServ), 2);

                    //Obteniendo servicialidad de la bd para usarla en caso de que altam falle
                    if (!$responseS->error && $responseS->data->status == 'success') {
                      $dataNumber['serviceability'] = $responseS->data->service;
                    }
                  }
                }
              }

              $fields = 'islim_services.id,
                     islim_services.title,
                     islim_services.gb,
                     islim_services.description,
                     islim_services.price_pay as price,
                     islim_services.broadband,
                     islim_periodicities.periodicity';

              if ($dataNumber['dn_type'] == 'F') {

                $fields .= ', islim_fiber_service_zone.service_pk';

                $services = $this->model->getServiceByTypeAndZone(
                  $typeService,
                  $dataNumber['serviceability'],
                  $fields,
                  $idConcentrator,
                  $dataNumber['id_list_dns'],
                  $dataNumber['msisdn'],
                  $statusDn['status'],
                  $dataNumber['dn_type'],
                  false,
                  $canBuyChHome,
                  $canBuySuspend,
                  $dataNumber['is_band_twenty_eight']
                );

              }else{

                $services = $this->model->getServiceByType(
                  $typeService,
                  $dataNumber['serviceability'],
                  $fields,
                  $idConcentrator,
                  $dataNumber['id_list_dns'],
                  $dataNumber['msisdn'],
                  $statusDn['status'],
                  $dataNumber['dn_type'],
                  false,
                  $canBuyChHome,
                  $canBuySuspend,
                  $dataNumber['is_band_twenty_eight']
                );

              }  

              if (is_array($services) && count($services) > 0) {
                //esta parte inserta null en la bd en los campos de servicio
                $servicio                = [];
                $servicio['id']          = null;
                $servicio['description'] = null;
                $servicio['price_pay']   = null;
                $servicio['GB']          = null;
              } else {
                $resA = [
                  'status' => 'FAIL',
                  'cod'    => 'SERVICES_NOT_FOUND',
                  'msg'    => 'No se encontraron servicios activos.'];
                $errorBan = true;
              }
            } else {
              /*Esta parte esta deprecada*/
              $servicio = $this->model->getDataService2(
                $service,
                $typeService,
                $idConcentrator,
                $dataNumber['id_list_dns'],
                $dataNumber['msisdn'],
                $dataNumber['dn_type'],
                $dataNumber['is_band_twenty_eight'],
                $statusDn['status'],
                $canBuyChHome
              );

              if (!$servicio) {
                $resA = [
                  'status' => 'FAIL',
                  'cod'    => 'SERVICE_NOT_VALID',
                  'msg'    => 'Servicio no válido.'];
                $errorBan = true;
              } elseif (!$concentratorBalance || $concentratorBalance < $servicio['price_pay']) {
                $resA = [
                  'status' => 'FAIL',
                  'cod'    => 'INSUFFICIENT_BALANCE',
                  'msg'    => 'Saldo insuficiente.'];
                $errorBan   = true;
                $alertSlack = true;
              }
            }

            if (!$errorBan) {
              $sqlTmpSale    = "INSERT INTO islim_tmp_sales (service_id, concentratos_id, unique_transaction, id_point, descriptión, amount, msisdn, lat, lng, position, date_fase1, status) values (:serviceId, :concentratorId, :transaction, :idSeller, :description, :amount, :msisdn, :lat, :lng, GeomFromText(:position), :dateTable, 'E')";
              $excSqlTmpSale = $this->db->prepare($sqlTmpSale);
              $excSqlTmpSale->bindParam(':concentratorId', $idConcentrator);
              $excSqlTmpSale->bindParam(':transaction', $transactionNumber);
              $excSqlTmpSale->bindParam(':idSeller', $seller);
              $excSqlTmpSale->bindParam(':msisdn', $number);
              $excSqlTmpSale->bindParam(':lat', $lat);
              $excSqlTmpSale->bindParam(':lng', $lng);
              $excSqlTmpSale->bindParam(':position', $geo, PDO::PARAM_STR);
              $excSqlTmpSale->bindParam(':dateTable', $date);
              $excSqlTmpSale->bindParam(':serviceId', $servicio['id']);
              $excSqlTmpSale->bindParam(':description', $servicio['description']);
              $excSqlTmpSale->bindParam(':amount', $servicio['price_pay']);
              $excSqlTmpSale->execute();

              $resA = [
                'status'   => 'OK',
                'response' => [
                  'transaction' => $transactionNumber,
                  'ttl'         => $ttl,
                  'createdAt'   => $date]];

              if (empty($service)) {
                $resA['response']['services'] = $services;
              }
            }
          } else {
            //$errorBan = true;
            if ($statusDn['success'] && $statusDn['status'] == 'suspend') {
              $resA = [
                'status' => 'FAIL',
                'cod'    => 'CAN_NOT_BUY_ACTIVE',
                'msg'    => 'Número temporalmente suspendido.'];
            } else {
              $alertSlack = true;
              $errormsg   = 'profile: ' . (!empty($statusDn['response']) ? $statusDn['response'] : '') . ' status: ' . (!empty($statusDn['status']) ? $statusDn['status'] : '');
              $resA       = [
                'status' => 'FAIL',
                'cod'    => 'ERROR_COMMUNICATION',
                'msg'    => 'Problemas con el concentrador, intente más tarde.'];
            }
          }
        }
      } catch (PDOException $e) {
        $this->common->sendSlackNotification2('Error(BD): ' . $e->getMessage(), 'alert', [], $request);

        $resA = [
          'status' => 'FAIL',
          'cod'    => 'SYSTEM_FAILURE',
          'msg'    => 'Falla en el sistema.'];
      }
    } else {
      $resA = [
        'status' => 'FAIL',
        'cod'    => 'MISSING_DATA',
        'msg'    => 'Faltan datos.'];
    }

    $this->common->logV2(
      $idLog,
      (String) json_encode($resA),
      null,
      null,
      $timeStart,
      'Tiempo profile: ' . $timeProf . ' Tiempo servicialidad: ' . $timeServ,
      ($alertSlack && !isServerTest) ? 'P' : 'NN',
      ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
    );

    return $response->withJson($resA);
  }

/*
  * Segundo paso para una recarga con comprobación de pago
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Post(
  *     path="/verification-pay-step2",
  *     tags={"Recargas"},
  *     operationId="verification-pay-step2", 
  *     summary="Test server status",      
  *     description="Segundo paso para una recarga con comprobación de pago",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function verificationPayStep2(Request $request)
  {
    $timeStart = microtime(true);

    $data = $request->getParsedBody();

    $resA       = ['status' => 'FAIL'];
    $alertSlack = false;

    $number            = !empty($data['msisdn']) ? $data['msisdn'] : null;
    $service           = !empty($data['service']) ? $data['service'] : null;
    $seller            = !empty($data['seller']) ? $data['seller'] : null;
    $transactionNumber = !empty($data['transaction']) ? $data['transaction'] : null;
    $payment           = !empty($data['payment']) ? $data['payment'] : null;

    $idLog = $this->common->logV2(
      false,
      (String) json_encode($data),
      $request,
      $number
    );

    $token = $this->common->getAuthBasic($request);

    if (!empty($number) && !empty($service) && !empty($seller) && !empty($transactionNumber) &&
      !empty($payment) && !empty($payment['method'])) {
      try {
        $tokenBearer    = $this->common->getKeyFromTokenSession($token->authUser);
        $idConcentrator = $this->model->getIdConcentratorByKey($tokenBearer->key);

        if ($idConcentrator) {
          $tmpData = $this->model->getTmpSale($transactionNumber, $idConcentrator);
          if ($tmpData) {
            $servicePrev = !empty($tmpData['service_id']) ? $tmpData['service_id'] : $service;
            if ($tmpData['id_point'] == $seller && $tmpData['msisdn'] == $number &&
              ($tmpData['status'] == 'E' || $tmpData['status'] == 'A') &&
              $servicePrev == $service) {

              $dataNumber = $this->model->getDataNumber($number);

              if (!$dataNumber) {
                $resA = [
                  'status' => 'FAIL',
                  'cod'    => 'MSISDN_NOT_VALID',
                  'msg'    => 'MSISDN no válido.'];
              } else {
                $typeService = $dataNumber['type_buy'];

                $totalPayRemaining = $this->model->getTotalPayCredit($dataNumber['msisdn'], $typeService);

                if ($dataNumber['price_remaining'] <= $totalPayRemaining) {
                  $typeService = 'CO';
                }

                $servicio = $this->model->getDataService(
                  $service,
                  $typeService,
                  $idConcentrator,
                  $dataNumber['id_list_dns'],
                  $dataNumber['msisdn'],
                  $dataNumber['dn_type'],
                  $dataNumber['is_band_twenty_eight']
                );

                if (!$servicio) {
                  $resA = [
                    'status' => 'FAIL',
                    'cod'    => 'SERVICE_NOT_VALID',
                    'msg'    => 'Servicio no válido.'];
                  $alertSlack = true;
                } else {
                  $serviceId     = $servicio['id'];
                  $amountService = $servicio['price_pay'];
                  $descService   = $servicio['title'];
                  $payisconfirm  = false;

                  if (strtolower($payment['method']) == 'mp' &&
                    !empty($payment['id']) &&
                    !empty($payment['last_four_digits'])) {

                    //Obteniendo datos del pago
                    $paymp = $this->mp->getPayment($payment['id']);

                    if ($paymp &&
                      $paymp->status &&
                      $paymp->external_reference &&
                      $paymp->date_approved &&
                      $paymp->transaction_amount &&
                      //$paymp->card &&
                      $paymp->status_detail &&
                      $paymp->payment_method_id &&
                      $paymp->status == 'approved' &&
                      //$paymp->card->last_four_digits == $payment['last_four_digits'] &&
                      $paymp->transaction_amount == $amountService &&
                      $paymp->external_reference == $transactionNumber
                    ) {

                      $isUsed = $this->model->getPayMP($payment['id']);

                      if (empty($isUsed)) {
                        $mpins = [
                          'status'             => $paymp->status,
                          'unique_transaction' => $paymp->external_reference,
                          'msisdn'             => $number,
                          'service_id'         => $serviceId,
                          'amount'             => $paymp->transaction_amount,
                          'mp_id'              => $payment['id'],
                          'payment_method'     => $paymp->payment_method_id,
                          'date_approved'      => $paymp->date_approved,
                          'status_detail'      => $paymp->status_detail,
                          'last_four_digits'   => $payment['last_four_digits'], //$paymp->card->last_four_digits
                        ];

                        $this->model->createPayMP($mpins);

                        $payisconfirm = true;
                      }
                    }
                  }

                  if ($payisconfirm) {
                    if ($dataNumber['dn_type'] == 'H') {
                      $typeMH       = $this->model->getLastService($dataNumber['msisdn']);
                      $dataCodAltan = $this->model->getAltanCode(
                        $servicio,
                        $dataNumber['serviceability'],
                        $typeMH['type_hbb'],
                        false
                      );

                      if (!$dataCodAltan) {
                        $this->model->updateTmpSale($tmpData['id'], 'altan');

                        $resA = [
                          'status' => 'FAIL',
                          'cod'    => 'ERROR_CODE_ALTAN',
                          'msg'    => 'No se econtro el código de altan.'];
                        $alertSlack = true;

                        $this->common->logV2(
                          $idLog,
                          (String) json_encode($resA),
                          null,
                          null,
                          $timeStart,
                          null,
                          ($alertSlack && !isServerTest) ? 'P' : 'NN',
                          ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
                        );

                        return $response->withJson($resA);
                      }

                      $codAltan = $dataCodAltan['codeAltan'];
                    } else {
                      //MOVILIDAD Y MIFI
                      if ($dataNumber['dn_type'] == 'T') {
                        $dataCodAltan = $this->model->getAltanCodeFT(
                          $dataNumber['msisdn'],
                          $dataNumber['is_band_twenty_eight'],
                          $servicio
                        );

                        if (!$dataCodAltan) {
                          $this->model->updateTmpSale($tmpData['id'], 'altan');

                          $resA = [
                            'status' => 'FAIL',
                            'cod'    => 'ERROR_CODE_ALTAN',
                            'msg'    => 'No se econtro el código de altan.'];

                          $this->common->logV2(
                            $idLog,
                            (String) json_encode($resA),
                            null,
                            null,
                            $timeStart,
                            null,
                            'P',
                            ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
                          );

                          return $response->withJson($resA);
                        }

                        $codAltan = $dataCodAltan['codeAltan'];
                      } else {
                        //Fibra
                        $codAltan = $servicio['codeAltan'];
                      }
                    }

                    $date = date("Y-m-d H:i:s");

                    $amountNeto   = $amountService - ($amountService * tax);
                    $comisionConc = $this->model->getConcentrator($idConcentrator, 'commissions');
                    $comisionConc = $comisionConc ? $comisionConc['commissions'] : 0;
                    $comision     = round($amountNeto * $comisionConc, 2);

                    //pasando data temporal a la tabla de ventas real
                    $sqlSale    = "INSERT INTO islim_sales (services_id, concentrators_id, api_key, unique_transaction, type, id_point, description, amount, amount_net, com_amount, msisdn, conciliation, lat, lng, position, date_reg, status, codeAltan, sale_type) values (:service, :conc, :key, :transaction, 'R', :seller, :description, :amount, :aneto, :comision, :msisdn, 'N', :lat, :lng, :position, :dateTable, 'EC', :codeAltan, :saleType)";
                    $excSqlSale = $this->db->prepare($sqlSale);
                    $excSqlSale->bindParam(':service', $servicio['id']);
                    $excSqlSale->bindParam(':conc', $tmpData['concentratos_id']);
                    $excSqlSale->bindParam(':key', $tokenBearer->key);
                    //$excSqlSale->bindParam(':altanOrder', $altan);
                    $excSqlSale->bindParam(':transaction', $tmpData['unique_transaction']);
                    $excSqlSale->bindParam(':seller', $tmpData['id_point']);
                    $excSqlSale->bindParam(':description', $descService);
                    $excSqlSale->bindParam(':amount', $amountService);
                    $excSqlSale->bindParam(':aneto', $amountNeto);
                    $excSqlSale->bindParam(':comision', $comision);
                    $excSqlSale->bindParam(':msisdn', $tmpData['msisdn']);
                    $excSqlSale->bindParam(':lat', $tmpData['lat']);
                    $excSqlSale->bindParam(':lng', $tmpData['lng']);
                    $excSqlSale->bindParam(':position', $tmpData['position']);
                    $excSqlSale->bindParam(':dateTable', $date);
                    $excSqlSale->bindParam(':codeAltan', $codAltan);
                    $excSqlSale->bindParam(':saleType', $dataNumber['dn_type']);
                    $excSqlSale->execute();
                    $idSale = $this->db->lastInsertId();

                    //cambiando estatus de la venta en la tabla temporal
                    $this->model->updateTmpSale($tmpData['id'], 'P');

                    //actualizando datos del credito al numero que se le activo el servicio
                    if ($typeService == 'CR') {
                      $this->model->updateCredit($dataNumber['msisdn'], $servicio, $idSale);
                    }

                    $resA = [
                      'status'   => 'OK',
                      'response' => [
                        'transaction' => $transactionNumber,
                        'estatus'     => 'TRANSACTION_SUCCESS',
                        'createdAt'   => $date]];
                  } else {
                    $resA = [
                      'status' => 'FAIL',
                      'cod'    => 'CAN_NOT_VALID',
                      'msg'    => 'No se pudo validar el pago id ' . $payment['id']];
                    $alertSlack = true;
                  }
                }
              }
            } else {
              $resA = [
                'status' => 'FAIL',
                'cod'    => 'CAN_NOT_BUY',
                'msg'    => 'No puede realizar la compra del servicio.'];
            }
          } else {
            $resA = [
              'status' => 'FAIL',
              'cod'    => 'CAN_NOT_BUY',
              'msg'    => 'No puede realizar la compra del servicio.'];
          }
        } else {
          $resA = [
            'status' => 'FAIL',
            'cod'    => 'CAN_NOT_BUY',
            'msg'    => 'No puede realizar la compra del servicio.'];
        }
      } catch (PDOException $e) {
        $this->common->sendSlackNotification2('Error(BD): ' . $e->getMessage(), 'alert', [], $request);

        $resA = [
          'status' => 'FAIL',
          'cod'    => 'SYSTEM_FAILURE',
          'msg'    => 'Falla en el sistema.'];
      }
    } else {
      $resA = [
        'status' => 'FAIL',
        'cod'    => 'MISSING_DATA',
        'msg'    => 'Faltan datos.'];
    }

    $this->common->logV2(
      $idLog,
      (String) json_encode($resA),
      null,
      null,
      $timeStart,
      null,
      ($alertSlack && !isServerTest) ? 'P' : 'NN',
      ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
    );

    return $response->withJson($resA);
  }

/*
  * Segundo paso para una recarga "recarga o activacion del plan."
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Post(
  *     path="/step2",
  *     tags={"Recargas"},
  *     operationId="step2", 
  *     summary="Test server status",      
  *     description="Home page",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function step2(Request $request)
  {
    $timeStart = microtime(true);

    $data = $request->getParsedBody();

    $resA       = ['status' => 'FAIL'];
    $alertSlack = false;

    $number            = !empty($data['msisdn']) ? $data['msisdn'] : null;
    $service           = !empty($data['service']) ? $data['service'] : null;
    $seller            = !empty($data['seller']) ? $data['seller'] : null;
    $transactionNumber = !empty($data['transaction']) ? $data['transaction'] : null;

    $idLog = $this->common->logV2(
      false,
      (String) json_encode($data),
      $request,
      $number
    );

    $token = $this->common->getAuthBasic($request);

    if (!empty($number) && !empty($service) && !empty($seller) && !empty($transactionNumber)) {
      try {
        $tokenBearer    = $this->common->getKeyFromTokenSession($token->authUser);
        $idConcentrator = $this->model->getIdConcentratorByKey($tokenBearer->key);

        if ($idConcentrator) {
          $tmpData = $this->model->getTmpSale($transactionNumber, $idConcentrator);
          if ($tmpData) {
            $servicePrev = !empty($tmpData['service_id']) ? $tmpData['service_id'] : $service;
            if ($tmpData['id_point'] == $seller && $tmpData['msisdn'] == $number && ($tmpData['status'] == 'E' || $tmpData['status'] == 'A') && $servicePrev == $service) {

              $dataNumber = $this->model->getDataNumber($number);

              if (!$dataNumber) {
                $resA = [
                  'status' => 'FAIL',
                  'cod'    => 'MSISDN_NOT_VALID',
                  'msg'    => 'MSISDN no válido.'];
              } else {
                $typeService = $dataNumber['type_buy'];

                $totalPayRemaining = $this->model->getTotalPayCredit($dataNumber['msisdn'], $typeService);

                if ($dataNumber['price_remaining'] <= $totalPayRemaining) {
                  $typeService = 'CO';
                }

                if ($dataNumber['dn_type'] == 'F') {

                  $servicio = $this->model->getDataServiceByZone(
                    $service,
                    $typeService,
                    $idConcentrator,
                    $dataNumber['id_list_dns'],
                    $dataNumber['msisdn'],
                    $dataNumber['dn_type'],
                    $dataNumber['is_band_twenty_eight']
                  );

                }else{

                  $servicio = $this->model->getDataService(
                    $service,
                    $typeService,
                    $idConcentrator,
                    $dataNumber['id_list_dns'],
                    $dataNumber['msisdn'],
                    $dataNumber['dn_type'],
                    $dataNumber['is_band_twenty_eight']
                  );

                }

                if (!$servicio) {
                  $resA = [
                    'status' => 'FAIL',
                    'cod'    => 'SERVICE_NOT_VALID',
                    'msg'    => 'Servicio no válido.'];
                } else {
                  $concentratorBalance = $this->model->getBalanceConcentrator($idConcentrator);
                  $concData            = $this->model->getConcentrator($idConcentrator, 'commissions, payment_verify');

                  $serviceId     = $servicio['id'];
                  $amountService = $servicio['price_pay'];
                  $descService   = $servicio['title'];

                  if ($dataNumber['dn_type'] == 'H') {
                    $typeMH = $this->model->getLastService($dataNumber['msisdn']);

                    $dataCodAltan = $this->model->getAltanCode(
                      $servicio,
                      $dataNumber['serviceability'],
                      $typeMH['type_hbb'],
                      false
                    );

                    if (!$dataCodAltan) {
                      $this->model->updateTmpSale($tmpData['id'], 'altan');

                      $resA = [
                        'status' => 'FAIL',
                        'cod'    => 'ERROR_CODE_ALTAN',
                        'msg'    => 'No se econtro el código de altan.'];

                      $this->common->logV2(
                        $idLog,
                        (String) json_encode($resA),
                        null,
                        null,
                        $timeStart,
                        null,
                        'P',
                        ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
                      );

                      return $response->withJson($resA);
                    }

                    $codAltan = $dataCodAltan['codeAltan'];
                  } else {
                    //Movilidad y MIFI y Huella Altan
                    if ($dataNumber['dn_type'] == 'T') {
                      $dataCodAltan = $this->model->getAltanCodeFT(
                        $dataNumber['msisdn'],
                        $dataNumber['is_band_twenty_eight'],
                        $servicio
                      );

                      if (!$dataCodAltan) {
                        $this->model->updateTmpSale($tmpData['id'], 'altan');

                        $resA = [
                          'status' => 'FAIL',
                          'cod'    => 'ERROR_CODE_ALTAN',
                          'msg'    => 'No se econtro el código de altan.'];

                        $this->common->logV2(
                          $idLog,
                          (String) json_encode($resA),
                          null,
                          null,
                          $timeStart,
                          null,
                          'P',
                          ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
                        );

                        return $response->withJson($resA);
                      }
                      $codAltan = $dataCodAltan['codeAltan'];
                    } else {
                      //Fibra
                      if($dataNumber['dn_type'] == 'F')
                          $codAltan = $servicio['service_pk'];
                        else
                          $codAltan = $servicio['codeAltan'];
                    }
                  }

                  if ($concentratorBalance &&
                    $concentratorBalance >= $servicio['price_pay'] &&
                    $concData['payment_verify'] == 'N'
                  ) {
                    $date = date("Y-m-d H:i:s");

                    $amountNeto   = $amountService - ($amountService * tax);
                    $comisionConc = $concData['commissions'] ? $concData['commissions'] : 0;
                    $comision     = round($amountNeto * $comisionConc, 2);
                    $newAmount    = $concentratorBalance - ($amountService - $comision);
                    $newBalance   = $this->model->setBalanceConcentrator($idConcentrator, $newAmount);

                    //pasando data temporal a la tabla de ventas real
                    $sqlSale    = "INSERT INTO islim_sales (services_id, concentrators_id, api_key, unique_transaction, type, id_point, description, amount, amount_net, com_amount, msisdn, conciliation, lat, lng, position, date_reg, status, codeAltan, sale_type) values (:service, :conc, :key, :transaction, 'R', :seller, :description, :amount, :aneto, :comision, :msisdn, 'N', :lat, :lng, :position, :dateTable, 'EC', :codeAltan, :saleType)";
                    $excSqlSale = $this->db->prepare($sqlSale);
                    $excSqlSale->bindParam(':service', $servicio['id']);
                    $excSqlSale->bindParam(':conc', $tmpData['concentratos_id']);
                    $excSqlSale->bindParam(':key', $tokenBearer->key);
                    //$excSqlSale->bindParam(':altanOrder', $altan);
                    $excSqlSale->bindParam(':transaction', $tmpData['unique_transaction']);
                    $excSqlSale->bindParam(':seller', $tmpData['id_point']);
                    $excSqlSale->bindParam(':description', $descService);
                    $excSqlSale->bindParam(':amount', $amountService);
                    $excSqlSale->bindParam(':aneto', $amountNeto);
                    $excSqlSale->bindParam(':comision', $comision);
                    $excSqlSale->bindParam(':msisdn', $tmpData['msisdn']);
                    $excSqlSale->bindParam(':lat', $tmpData['lat']);
                    $excSqlSale->bindParam(':lng', $tmpData['lng']);
                    $excSqlSale->bindParam(':position', $tmpData['position']);
                    $excSqlSale->bindParam(':dateTable', $date);
                    $excSqlSale->bindParam(':codeAltan', $codAltan);
                    $excSqlSale->bindParam(':saleType', $dataNumber['dn_type']);
                    $excSqlSale->execute();
                    $idSale = $this->db->lastInsertId();

                    //cambiando estatus de la venta en la tabla temporal
                    $this->model->updateTmpSale($tmpData['id'], 'P');

                    //actualizando datos del credito al numero que se le activo el servicio
                    if ($typeService == 'CR') {
                      $this->model->updateCredit($dataNumber['msisdn'], $servicio, $idSale);
                    }

                    $resA = [
                      'status'   => 'OK',
                      'response' => [
                        'transaction' => $transactionNumber,
                        'estatus'     => 'TRANSACTION_SUCCESS',
                        'createdAt'   => $date],
                    ];
                  } else {
                    $resA = [
                      'status' => 'FAIL',
                      'cod'    => 'INSUFFICIENT_BALANCE',
                      'msg'    => 'Saldo insuficiente.'];
                    $alertSlack = true;
                  }
                }
              }
            } else {
              $resA = [
                'status' => 'FAIL',
                'cod'    => 'CAN_NOT_BUY',
                'msg'    => 'No puede realizar la compra del servicio.'];
            }
          } else {
            $resA = [
              'status' => 'FAIL',
              'cod'    => 'CAN_NOT_BUY',
              'msg'    => 'No puede realizar la compra del servicio.'];
          }
        } else {
          $resA = [
            'status' => 'FAIL',
            'cod'    => 'CAN_NOT_BUY',
            'msg'    => 'No puede realizar la compra del servicio.'];
        }
      } catch (PDOException $e) {
        $this->common->sendSlackNotification2('Error(BD): ' . $e->getMessage(), 'alert', [], $request);

        $resA = [
          'status' => 'FAIL',
          'cod'    => 'SYSTEM_FAILURE',
          'msg'    => 'Falla en el sistema.'];
      }
    } else {
      $resA = [
        'status' => 'FAIL',
        'cod'    => 'SYSTEM_FAILURE',
        'msg'    => 'Falla en el sistema.'];
    }

    $this->common->logV2(
      $idLog,
      (String) json_encode($resA),
      null,
      null,
      $timeStart,
      null,
      ($alertSlack && !isServerTest) ? 'P' : 'NN',
      ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
    );

    return $response->withJson($resA);
  }

/*
  * Segundo paso para una recarga "recarga o activacion del plan."
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Post(
  *     path="/step2-seller",
  *     tags={"Recargas"},
  *     operationId="step2-seller", 
  *     summary="Test server status",      
  *     description="Segundo paso para una recarga o activacion del plan.",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function step2Seller(Request $request)
  {
    $timeStart = microtime(true);

    $data       = $request->getParsedBody();
    $timeAct    = 0;
    $alertSlack = false;

    $resA = ['status' => 'FAIL'];

    $number            = !empty($data['msisdn']) ? $data['msisdn'] : null;
    $service           = !empty($data['service']) ? $data['service'] : null;
    $seller            = !empty($data['seller']) ? $data['seller'] : null;
    $transactionNumber = !empty($data['transaction']) ? $data['transaction'] : null;

    $idLog = $this->common->logV2(
      false,
      (String) json_encode($data),
      $request,
      $number
    );

    $token = $this->common->getAuthBasic($request);

    if (!empty($number) && !empty($service) && !empty($seller) && !empty($transactionNumber)) {
      try {
        $tokenBearer    = $this->common->getKeyFromTokenSession($token->authUser);
        $idConcentrator = $this->model->getIdConcentratorByKey($tokenBearer->key);

        if ($idConcentrator) {
          $tmpData = $this->model->getTmpSale($transactionNumber, $idConcentrator);
          if ($tmpData) {
            $servicePrev = !empty($tmpData['service_id']) ? $tmpData['service_id'] : $service;
            if ($tmpData['id_point'] == $seller && $tmpData['msisdn'] == $number && ($tmpData['status'] == 'E' || $tmpData['status'] == 'A') && $servicePrev == $service) {

              $dataNumber = $this->model->getDataNumber($number);

              if (!$dataNumber) {
                $resA = [
                  'status' => 'FAIL',
                  'cod'    => 'MSISDN_NOT_VALID',
                  'msg'    => 'MSISDN no válido.'];
              } else {
                $typeService = $dataNumber['type_buy'];

                $totalPayRemaining = $this->model->getTotalPayCredit(
                  $dataNumber['msisdn'],
                  $typeService
                );

                if ($dataNumber['total_debt'] <= $totalPayRemaining) {
                  $typeService = 'CO';
                }

                if ($dataNumber['dn_type'] == 'F') {

                  $servicio = $this->model->getDataServiceByZone(
                    $service,
                    $typeService,
                    $idConcentrator,
                    $dataNumber['id_list_dns'],
                    $dataNumber['msisdn'],
                    $dataNumber['dn_type'],
                    $dataNumber['is_band_twenty_eight']
                  );

                }else{

                  $servicio = $this->model->getDataService(
                    $service,
                    $typeService,
                    $idConcentrator,
                    $dataNumber['id_list_dns'],
                    $dataNumber['msisdn'],
                    $dataNumber['dn_type'],
                    $dataNumber['is_band_twenty_eight']
                  );

                }

                if (!$servicio) {
                  $resA = [
                    'status' => 'FAIL',
                    'cod'    => 'SERVICE_NOT_VALID',
                    'msg'    => 'Servicio no válido.'];
                } else {
                  $concentratorBalance = $this->model->getBalanceConcentrator($idConcentrator);
                  $concData            = $this->model->getConcentrator($idConcentrator, 'payment_verify');

                  $serviceId     = $servicio['id'];
                  $amountService = $servicio['price_pay'];
                  $descService   = $servicio['title'];

                  if ($dataNumber['dn_type'] == 'H') {
                    $typeMH = $this->model->getLastService($dataNumber['msisdn']);

                    $dataCodAltan = $this->model->getAltanCode(
                      $servicio,
                      $dataNumber['serviceability'],
                      $typeMH['type_hbb'],
                      false
                    );

                    if (!$dataCodAltan) {
                      $this->model->updateTmpSale($tmpData['id'], 'altan');

                      $resA = [
                        'status' => 'FAIL',
                        'cod'    => 'SERVICE_NOT_VALID',
                        'msg'    => 'Servicio no válido.'];

                      $this->common->logV2(
                        $idLog,
                        (String) json_encode($resA),
                        null,
                        null,
                        $timeStart,
                        null,
                        ($alertSlack && !isServerTest) ? 'P' : 'NN',
                        ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
                      );

                      return $response->withJson($resA);
                    }

                    $codAltan = $dataCodAltan['codeAltan'];
                    $isSup    = $dataCodAltan['supplementary'];
                  } else {
                    //Movilidad y MIFI
                    if ($dataNumber['dn_type'] == 'T') {
                      $dataCodAltan = $this->model->getAltanCodeFT(
                        $dataNumber['msisdn'],
                        $dataNumber['is_band_twenty_eight'],
                        $servicio
                      );

                      if (!$dataCodAltan) {
                        $this->model->updateTmpSale($tmpData['id'], 'altan');

                        $resA = [
                          'status' => 'FAIL',
                          'cod'    => 'SERVICE_NOT_VALID',
                          'msg'    => 'Servicio no válido.'];

                        $this->common->logV2(
                          $idLog,
                          (String) json_encode($resA),
                          null,
                          null,
                          $timeStart,
                          null,
                          ($alertSlack && !isServerTest) ? 'P' : 'NN',
                          ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
                        );

                        return $response->withJson($resA);
                      }

                      $isSup    = $dataCodAltan['suplementary'];
                      $codAltan = $dataCodAltan['codeAltan'];
                    } else {
                      //Fibra
                      $isSup    = 'Y';
                      if($dataNumber['dn_type'] == 'F')
                          $codAltan = $servicio['service_pk'];
                        else
                          $codAltan = $servicio['codeAltan'];
                    }
                  }

                  if ($concentratorBalance &&
                    $concentratorBalance >= $servicio['price_pay'] &&
                    $concData['payment_verify'] == 'N'
                  ) {

                    //Verificando si es un dn suspendido por inactividad
                    $isInactive = $this->model->canBuySuspend($dataNumber);

                    //Parche para cobrar un cambio de coordenadas
                    $altan = false;
                    if ($codAltan == '999999CDDP' || $codAltan == '999999CDDS') {
                      $altan = 'CHCOO';
                    } else {
                      //Si es un servicio suplementario
                      if ($dataNumber['dn_type'] != 'F') {
                        if ($isSup == 'Y') {
                          $url = "supplementary/" . $dataNumber['msisdn'];
                        } else {
                          $url = "subscribers/" . $dataNumber['msisdn'];
                        }

                        $json = array(
                          'apiKey' => $tokenBearer->key,
                          'offer'  => $codAltan,
                        );

                        if ($dataNumber['dn_type'] == 'H') {
                          $json['lat'] = $dataNumber['lat'];
                          $json['lng'] = $dataNumber['lng'];
                        }

                        $json = json_encode($json);

                        if (statusSystem != 'local' && (!$isInactive && $dataNumber['is_suspend_by_b28'] == 'N')) {
                          $timeAct       = microtime(true);
                          $responseAltan = $this->model->executeCurlAltan($url, "POST", $json);
                          $timeAct       = round((microtime(true) - $timeAct), 2);

                          if (!$responseAltan->error && $responseAltan->data->status == 'success') {
                            $altan = $responseAltan->data->orderId;
                          } else {
                            $errormsg = !empty($responseAltan->original) ? $responseAltan->original : $responseAltan->data;
                          }
                        } else {
                          $altan = time();
                        }
                      } else {
                        //Fibra
                        $altan      = true;
                        $isInactive = true;
                      }
                    }

                    if ($altan) {
                      //se hizo la recarga exitosamente
                      $date = date("Y-m-d H:i:s");

                      $amountNeto   = $amountService - ($amountService * tax);
                      $comisionConc = $this->model->getConcentrator($idConcentrator, 'commissions');
                      $comisionConc = $comisionConc ? $comisionConc['commissions'] : 0;
                      $comision     = round($amountNeto * $comisionConc, 2);
                      $newAmount    = $concentratorBalance - ($amountService - $comision);
                      $newBalance   = $this->model->setBalanceConcentrator($idConcentrator, $newAmount);

                      if ($isInactive || $dataNumber['is_suspend_by_b28'] == 'Y') {
                        $statusR = 'EC';
                        $altan   = null;
                      } else {
                        $statusR = 'A';
                      }

                      //pasando data temporal a la tabla de ventas real
                      $sqlSale    = "INSERT INTO islim_sales (services_id, concentrators_id, api_key, order_altan, unique_transaction, type, id_point, description, amount, amount_net, com_amount, msisdn, conciliation, lat, lng, position, date_reg, status, codeAltan, sale_type) values (:service, :conc, :key, :altanOrder, :transaction, 'R', :seller, :description, :amount, :aneto, :comision, :msisdn, 'N', :lat, :lng, :position, :dateTable, :statusR, :codeAltan, :saleType)";
                      $excSqlSale = $this->db->prepare($sqlSale);
                      $excSqlSale->bindParam(':service', $servicio['id']);
                      $excSqlSale->bindParam(':conc', $tmpData['concentratos_id']);
                      $excSqlSale->bindParam(':key', $tokenBearer->key);
                      $excSqlSale->bindParam(':altanOrder', $altan);
                      $excSqlSale->bindParam(':transaction', $tmpData['unique_transaction']);
                      $excSqlSale->bindParam(':seller', $tmpData['id_point']);
                      $excSqlSale->bindParam(':description', $descService);
                      $excSqlSale->bindParam(':amount', $amountService);
                      $excSqlSale->bindParam(':aneto', $amountNeto);
                      $excSqlSale->bindParam(':comision', $comision);
                      $excSqlSale->bindParam(':msisdn', $tmpData['msisdn']);
                      $excSqlSale->bindParam(':lat', $tmpData['lat']);
                      $excSqlSale->bindParam(':lng', $tmpData['lng']);
                      $excSqlSale->bindParam(':position', $tmpData['position']);
                      $excSqlSale->bindParam(':dateTable', $date);
                      $excSqlSale->bindParam(':statusR', $statusR);
                      $excSqlSale->bindParam(':codeAltan', $codAltan);
                      $excSqlSale->bindParam(':saleType', $dataNumber['dn_type']);
                      $excSqlSale->execute();
                      $idSale = $this->db->lastInsertId();

                      //cambiando estatus de la venta en la tabla temporal
                      $this->model->updateTmpSale($tmpData['id'], 'P');

                      //actualizando datos del credito al numero que se le activo el servicio
                      if ($typeService == 'CR') {
                        $this->model->updateCredit($dataNumber['msisdn'], $servicio, $idSale);
                      }
                      if ($dataNumber['dn_type'] != 'F') {
                        //El update del servicio del cliente se hace en la api 815
                        $this->model->updateService($dataNumber['msisdn'], $serviceId, $servicio['broadband']);
                      }
                      //Envio de sms
                      if (statusSystem != 'local' && ($dataNumber['dn_type'] == 'H' || $dataNumber['dn_type'] == 'M' || $dataNumber['dn_type'] == 'MH' || $dataNumber['dn_type'] == 'F')) {
                        //SMS HBB, MIFI y Fibra
                        $this->model->smsPush(
                          $dataNumber['msisdn'],
                          $tmpData['concentratos_id'],
                          $descService
                        );
                      }

                      $resA = [
                        'status'   => 'OK',
                        'response' => [
                          'transaction' => $transactionNumber,
                          'estatus'     => 'TRANSACTION_SUCCESS',
                          'createdAt'   => $date]];
                    } else {
                      //algo paso con altan, reverso el proceso de compra.
                      $this->model->updateTmpSale($tmpData['id'], 'altan');
                      $resA = [
                        'status' => 'FAIL',
                        'cod'    => 'ERROR_COMMUNICATION',
                        'msg'    => 'Problemas con el concentrador, intente más tarde.'];
                      $alertSlack = true;
                    }
                  } else {
                    $resA = [
                      'status' => 'FAIL',
                      'cod'    => 'INSUFFICIENT_BALANCE',
                      'msg'    => 'Saldo insuficiente.'];
                  }
                }
              }
            } else {
              $resA = [
                'status' => 'FAIL',
                'cod'    => 'CAN_NOT_BUY',
                'msg'    => 'No puede realizar la compra del servicio.'];
            }
          } else {
            $resA = [
              'status' => 'FAIL',
              'cod'    => 'CAN_NOT_BUY',
              'msg'    => 'No puede realizar la compra del servicio.'];
          }
        } else {
          $resA = [
            'status' => 'FAIL',
            'cod'    => 'CAN_NOT_BUY',
            'msg'    => 'No puede realizar la compra del servicio.'];
        }
      } catch (PDOException $e) {
        $this->common->sendSlackNotification2('Error(BD): ' . $e->getMessage(), 'alert', [], $request);

        $resA = [
          'status' => 'FAIL',
          'cod'    => 'SYSTEM_FAILURE',
          'msg'    => 'Falla en el sistema.'];
      }
    } else {
      $resA = [
        'status' => 'FAIL',
        'cod'    => 'MISSING_DATA',
        'msg'    => 'Faltan datos.'];
    }

    $this->common->logV2(
      $idLog,
      (String) json_encode($resA),
      null,
      null,
      $timeStart,
      'Tiempo activación: ' . $timeAct,
      ($alertSlack && !isServerTest) ? 'P' : 'NN',
      ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
    );

    return $response->withJson($resA);
  }

/*
  * Metodo obtener el saldo de un concentrador
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Post(
  *     path="/balance",
  *     tags={"Recargas"},
  *     operationId="balance", 
  *     summary="Test server status",      
  *     description="Metodo obtener el saldo de un concentrador",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function balance(Request $request)
  {
    $timeStart  = microtime(true);
    $alertSlack = false;

    $resA = ['status' => 'FAIL'];

    $idLog = $this->common->logV2(
      false,
      null,
      $request
    );

    $token = $this->common->getAuthBasic($request);

    try {
      $tokenBearer         = $this->common->getKeyFromTokenSession($token->authUser);
      $idConcentrator      = $this->model->getIdConcentratorByKey($tokenBearer->key);
      $concentratorBalance = $this->model->getBalanceConcentrator($idConcentrator);

      if ($concentratorBalance >= 0) {
        $date                = date("Y-m-d H:i:s");
        $concentratorBalance = (int) $concentratorBalance;

        $resA = [
          'status'   => 'OK',
          'response' => [
            'balance' => $concentratorBalance,
            'date'    => $date]];
      } else {
        $resA = [
          'status' => 'FAIL',
          'cod'    => 'ERROR_GET_BALANCE',
          'msg'    => 'Error obteniendo saldo.'];
      }
    } catch (PDOException $e) {
      $this->common->sendSlackNotification2('Error(BD): ' . $e->getMessage(), 'alert', [], $request);

      $resA = [
        'status' => 'FAIL',
        'cod'    => 'SYSTEM_FAILURE',
        'msg'    => 'Falla en el sistema.'];
    }

    $this->common->logV2(
      $idLog,
      (String) json_encode($resA),
      null,
      null,
      $timeStart,
      null,
      ($alertSlack && !isServerTest) ? 'P' : 'NN',
      ($resA['status'] != 'OK' && !empty($resA['msg'])) ? $resA['msg'] . ' ' . (!empty($errormsg) ? $errormsg : '') : null
    );

    return $response->withJson($resA);
  }

/*
  * Metodo para procesar las recargas.
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Get(
  *     path="/do-recharge",
  *     tags={"Recargas"},
  *     operationId="do-recharge", 
  *     summary="Test server status",      
  *     description="Request para procesar las recargas.",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function doRecharge(Request $request)
  {
    //varificando si hay otro cron de recargas ejecutandose
    $msg = "OK";
    if (!$this->model->isBlockedCron('api-recharge')) {
      //Bloqueando los llamados de cron de recargas
      $this->model->blockCron('api-recharge');

      //Buscando recargas por activar
      $pendingRecharge = $this->model->getPendingRecharges();

      if ($pendingRecharge) {
        foreach ($pendingRecharge as $recharge) {
          $dataNumber = $this->model->getDataNumber($recharge['msisdn']);

          if ($dataNumber) {
            //Verificando si es un dn suspendido por inactividad
            $isInactive = $this->model->canBuySuspend($dataNumber);

            if ($dataNumber['is_suspend_by_b28'] == 'Y') {
              $responseS = $this->model->executeCurlAltan(
                'unbarring/' . $recharge['msisdn'],
                "POST",
                json_encode(['apiKey' => $recharge['api_key']])
              );

              if (!$responseS->error && $responseS->data->status == 'success') {
                $this->model->unBarringDBDN($recharge['msisdn']);
              } else {
                if (!isServerTest) {
                  $this->common->sendSlackNotification2(
                    'No se pudo reactivar por barring el cliente',
                    'alert',
                    [
                      'MSISDN' => $recharge['msisdn'],
                      'altan'  => !empty($responseS->original) ? $responseS->original : $responseS->data,
                    ],
                    $request
                  );
                }

                $this->common->logV2(
                  false,
                  null,
                  $request,
                  $recharge['msisdn'],
                  0,
                  'Error en reactivar por barring el cliente: ' . (!empty($responseS->original) ? $responseS->original : $responseS->data)
                );
                continue;
              }
            }

            if ($isInactive && $recharge['codeAltan'] != '999999CDDP' && $recharge['codeAltan'] != '999999CDDS') {
              $responseS = $this->model->executeCurlAltan(
                'resume/' . $recharge['msisdn'],
                "POST",
                json_encode(['apiKey' => $recharge['api_key']])
              );

              if (!$responseS->error && $responseS->data->status == 'success') {
                $this->model->reactivateDN($recharge['msisdn']);
              } else {
                if(!empty($responseS->data->description_altan) && strpos(strtolower($responseS->data->description_altan), 'status is active')){
                  $this->model->reactivateDN($recharge['msisdn']);
                }else{
                  if (!isServerTest) {
                    $this->common->sendSlackNotification2(
                      'No se pudo reactivar el cliente',
                      'alert',
                      [
                        'MSISDN' => $recharge['msisdn'],
                        'altan'  => !empty($responseS->original) ? $responseS->original : $responseS->data,
                      ],
                      $request
                    );
                  }

                  $this->common->logV2(
                    false,
                    null,
                    $request,
                    $recharge['msisdn'],
                    0,
                    'Error en reactivar cliente: ' . (!empty($responseS->original) ? $responseS->original : $responseS->data)
                  );
                  continue;
                }
              }
            }

            $servicio = $this->model->getServiceById($recharge['services_id']);
            //Parche para cobrar un cambio de coordenadas
            $altan = false;
            if ($recharge['codeAltan'] == '999999CDDP' || $recharge['codeAltan'] == '999999CDDS') {
              $altan = 'CHCOO';
            } else {
              if ($dataNumber['dn_type'] == 'H' || $dataNumber['dn_type'] == 'T') {
                $dataCodAltan = $this->model->getCodeAltanBycode($recharge['codeAltan']);
                $isSup        = $dataCodAltan['supplementary'];
              } else {
                $isSup = 'Y';
              }

              if ($isSup == 'Y') {
                $url = "supplementary/" . $recharge['msisdn'];
              } else {
                $url = "subscribers/" . $recharge['msisdn'];
              }

              $json = array(
                'apiKey' => $recharge['api_key'],
                'offer'  => $recharge['codeAltan'],
              );

              if ($dataNumber['dn_type'] == 'H') {
                $json['lat'] = $dataNumber['lat'];
                $json['lng'] = $dataNumber['lng'];
              }

              if (statusSystem != 'local') {
                $json          = json_encode($json);
                $responseAltan = $this->model->executeCurlAltan($url, "POST", $json);

                if (!$responseAltan->error && $responseAltan->data->status == 'success') {
                  $altan = $responseAltan->data->orderId;
                } else {
                  if (!isServerTest) {
                    $this->common->sendSlackNotification2(
                      'No se pudo activar el servicio al cliente',
                      'alert',
                      [
                        'MSISDN' => $recharge['msisdn'],
                        'altan'  => !empty($responseAltan->original) ? $responseAltan->original : $responseAltan->data,
                      ],
                      $request
                    );
                  }

                  $this->common->logV2(
                    false,
                    null,
                    $request,
                    $recharge['msisdn'],
                    0,
                    'Error en activar servicio al cliente: ' . (!empty($responseAltan->original) ? $responseAltan->original : $responseAltan->data)
                  );
                }
              } else {
                $altan = time();
              }
            }

            if ($altan) {
              $this->model->updateSaleStatus($recharge['id'], $altan);

              $this->model->reactivateDN($recharge['msisdn']);

              $this->model->updateService($recharge['msisdn'], $recharge['services_id'], $servicio['broadband']);

              //Envio de sms
              if ($dataNumber['dn_type'] == 'H' || $dataNumber['dn_type'] == 'M' || $dataNumber['dn_type'] == 'MH') {
                //SMS para HBB y MIFI
                $this->model->smsPush(
                  $recharge['msisdn'],
                  $recharge['concentrators_id'],
                  $recharge['description']
                );
              }
            }
          } else {
            if (!isServerTest) {
              $this->common->sendSlackNotification2(
                'MSISDN No registrado.',
                'alert',
                [
                  'MSISDN' => $recharge['msisdn']],
                $request
              );
            }

            $this->common->logV2(
              false,
              null,
              $request,
              $recharge['msisdn'],
              0,
              'MSISDN No registrado.'
            );
          }
        }
      } else {
        $msg = "No hay recargas pendientes";
      }

      //Desbloqueando cron
      $this->model->unblockCron('api-recharge');
    } else {
      $this->common->sendSlackNotification2(
        'Se esta ejecutando otro cron de recargas',
        'alert',
        [],
        $request
      );
    }

    return $response->withJson(['mensaje' => $msg]);
  }

/*
  * Verifica si el proceso de recarga tiene mas de un tiempo X ejcutandose y lo reinicia
  * Se debe ejecutar desde un cron cada minúto
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Get(
  *     path="/reset-recharge-process",
  *     tags={"Recargas"},
  *     operationId="reset-recharge-process", 
  *     summary="Test server status",      
  *     description="Verifica si el proceso de recarga tiene mas de un tiempo X ejcutandose y lo reinicia",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function resetRechargeProcess(Request $request)
  {
    $msg = "OK";
    $data = $this->model->getLastExc('api-recharge');

    if($data){
      if($data['unlook'] == 'Y'){
        $diffT = $this->common->getDiffTime($data['date_begin']);
        if(($diffT / 60) >= ttl_cron){
          //Desbloqueando cron
          $this->model->unblockCron('api-recharge');
          $msg = "Cron desbloqueado";
        }
      }
    }

    return $response->withJson(['mensaje' => $msg]);
  }

/*
  * Servicio que se ejecuta por cron 1 vez al día y activa recargas de promoción
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Get(
  *     path="/active-recharge-prom",
  *     tags={"Recargas"},
  *     operationId="active-recharge-prom", 
  *     summary="Test server status",      
  *     description="Servicio que se ejecuta por cron 1 vez al día y activa recargas de promoción",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function activeRechargeProm(Request $request)
  {
    //varificando si hay otro cron de recargas ejecutandose
    $msg = "OK";
    //$errorBan = false;

    if (!$this->model->isBlockedCron('api-recharge-prom')) {
      //Bloqueando los llamados de cron de recargas
      $this->model->blockCron('api-recharge-prom');

      //Buscando recargas por activar
      $pendingRecharge = $this->model->getPendingRechargesProm();

      foreach ($pendingRecharge as $recharge) {
        $dataNumber = $this->model->getDataNumber($recharge['msisdn']);

        if (!$dataNumber) {
          if (!isServerTest) {
            $this->common->sendSlackNotification2(
              'No se consiguio el dn.',
              'alert',
              [
                'MSISDN' => $recharge['msisdn']],
              $request
            );
          }
        } else {
          $date        = date('Y-m-d H:i:s');
          $transaction = $this->model->getIdTransaction('prom-');
          $service     = $this->model->getServiceById($recharge['service_id']);

          if ($dataNumber['dn_type'] == 'H') {
            $typeMH = $this->model->getLastService($dataNumber['msisdn']);

            $dataCodAltan = $this->model->getAltanCode(
              $service,
              $dataNumber['serviceability'],
              $typeMH['type_hbb'],
              false
            );

            if (!$dataCodAltan) {
              if (!isServerTest) {
                $this->common->sendSlackNotification2(
                  'No se econtro el código de altan para el servicio.',
                  'alert',
                  [
                    'MSISDN'  => $recharge['msisdn'],
                    'service' => $recharge['service_id']],
                  $request
                );
              }
              continue;
            }
            $codAltan = $dataCodAltan['codeAltan'];
          } else {
            //Movilidad, MIFI y Fibra
            $codAltan = $service['codeAltan'];
          }

          //Creando recarga en estatus pendiente
          $sqlSale    = "INSERT INTO islim_sales (services_id, concentrators_id, api_key, unique_transaction, type, id_point, description, amount, amount_net, com_amount, msisdn, conciliation, date_reg, status, codeAltan, sale_type) values (:service, '" . prom_concentrador . "', '" . prom_key . "', :transaction, 'R', 'Promoción', :description, '0', '0', '0', :msisdn, 'N', :dateTable, 'EC', :codeAltan, :saleType)";
          $excSqlSale = $this->db->prepare($sqlSale);
          $excSqlSale->bindParam(':service', $recharge['service_id']);
          $excSqlSale->bindParam(':transaction', $transaction);
          $excSqlSale->bindParam(':description', $service['description']);
          $excSqlSale->bindParam(':msisdn', $recharge['msisdn']);
          $excSqlSale->bindParam(':dateTable', $date);
          $excSqlSale->bindParam(':codeAltan', $codAltan);
          $excSqlSale->bindParam(':saleType', $dataNumber['dn_type']);
          $excSqlSale->execute();
          $idSale = $this->db->lastInsertId();

          $this->model->updateRechargeProm($recharge['id'], $idSale);
        }
      }
    }

    $this->model->unblockCron('api-recharge-prom');

    return $response->withJson(['mensaje' => $msg]);
  }

/*
  * Proceso que se ejecuta por cron, activa servicios "extras" (nav. nocturna) para las recargas
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Get(
  *     path="/extra-recharge",
  *     tags={"Recargas"},
  *     operationId="extra-recharge", 
  *     summary="Test server status",      
  *     description="Proceso que se ejecuta por cron, activa servicios extras (nav. nocturna) para las recargas",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function extraRecharge(Request $request)
  {
    $msg = "OK";

    if (!$this->model->isBlockedCron('api-recharge-extra')) {
      $this->model->blockCron('api-recharge-extra');

      $date_b = date('Y-m-d H:i:s', strtotime('-1 hour', time()));

      $recharges = $this->model->getSaleWext($date_b, 'R');

      $extraT = $this->model->getExtraService('R', 'T');

      $extraH = $this->model->getExtraService('R', 'H');

      if (count($extraH) || count($extraT)) {
        foreach ($recharges as $recharge) {
          if (strtotime('+ 10 minutes', strtotime($recharge['date_reg'])) > time()) {
            continue;
          }

          $status = 'E';
          $altan  = 0;
          if ($recharge['sale_type'] == 'H' && count($extraH)) {
            $dataNumber = $this->model->getDataNumber($recharge['msisdn']);

            foreach ($extraH as $ext) {
              if (!empty($ext['offer_rel'])) {
                $sup = $this->model->getSupoffertFromSale($recharge['id']);
                if (empty($sup) || (!empty($sup['codeAltan']) && $sup['codeAltan'] != $ext['offer_rel'])) {
                  continue;
                }
              }

              if ($ext['isSup']) {
                $url = "supplementary/" . $recharge['msisdn'];
              } else {
                $url = "subscribers/" . $recharge['msisdn'];
              }

              $json = array(
                'apiKey' => $recharge['api_key'],
                'offer'  => $ext['offer'],
                'lat'    => $dataNumber['lat'],
                'lng'    => $dataNumber['lng']);

              $responseAltan = $this->model->executeCurlAltan(
                $url,
                "POST",
                json_encode($json)
              );

              if (!$responseAltan->error && $responseAltan->data->status == 'success') {
                $altan  = $responseAltan->data->orderId;
                $status = 'A';
              } else {
                if (!isServerTest) {
                  $this->common->sendSlackNotification2(
                    'No se pudo activar el servicio (extra).',
                    'alert',
                    [
                      'MSISDN' => $recharge['msisdn'],
                      'altan'  => !empty($responseAltan->original) ? $responseAltan->original : $responseAltan->data,
                      'ofert'  => $ext['offer']],
                    $request
                  );
                }
              }

              $this->model->insertExtra([
                'sale_id'      => $recharge['id'],
                'extra'        => $ext['id'],
                'order'        => !empty($altan) ? $altan : 0,
                'response'     => (String) json_encode($responseAltan),
                'type_trigger' => 'R',
                'status'       => !empty($status) ? $status : 'E']);
            }
          }

          if ($recharge['sale_type'] == 'T' && count($extraT)) {
            foreach ($extraT as $ext) {
              if (!empty($ext['offer_rel'])) {
                $primary = $this->model->getSupoffertFromSale($recharge['id']);
                if (empty($primary) || $primary['codeAltan'] != $ext['offer_rel']) {
                  continue;
                }
              }

              if ($ext['isSup']) {
                $url = "supplementary/" . $recharge['msisdn'];
              } else {
                $url = "subscribers/" . $recharge['msisdn'];
              }

              $json = array(
                'apiKey' => $recharge['api_key'],
                'offer'  => $ext['offer']);

              $responseAltan = $this->model->executeCurlAltan(
                $url,
                "POST",
                json_encode($json)
              );

              if (!$responseAltan->error && $responseAltan->data->status == 'success') {
                $altan  = $responseAltan->data->orderId;
                $status = 'A';
              } else {
                if (!isServerTest) {
                  $this->common->sendSlackNotification2(
                    'No se pudo activar el servicio (extra).',
                    'alert',
                    [
                      'MSISDN' => $recharge['msisdn'],
                      'altan'  => !empty($responseAltan->original) ? $responseAltan->original : $responseAltan->data,
                      'ofert'  => $ext['offer']],
                    $request
                  );
                }
              }

              $this->model->insertExtra([
                'sale_id'      => $recharge['id'],
                'extra'        => $ext['id'],
                'order'        => !empty($altan) ? $altan : 0,
                'response'     => (String) json_encode($responseAltan),
                'type_trigger' => 'R',
                'status'       => !empty($status) ? $status : 'E']);
            }
          }
        }
      }

      $this->model->unblockCron('api-recharge-extra');
    }

    return $response->withJson(['mensaje' => $msg]);
  }

/*
  * Proceso que se ejecuta por cron, activa servicios "extras" (nav. nocturna) para las altas
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Get(
  *     path="/extra-register",
  *     tags={"Recargas"},
  *     operationId="extra-register", 
  *     summary="Test server status",      
  *     description="Proceso que se ejecuta por cron, activa servicios extras (nav. nocturna) para las altas",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function extraRegister(Request $request)
  {
    $msg = "OK";
    if (!$this->model->isBlockedCron('api-register-extra')) {
      $this->model->blockCron('api-register-extra');

      $date_b = date('Y-m-d H:i:s', strtotime('-1 hour', time()));

      $registers = $this->model->getSaleWext($date_b, 'P');

      $extraT = $this->model->getExtraService('P', 'T');

      $extraH = $this->model->getExtraService('P', 'H');

      if (count($extraH) || count($extraT)) {
        foreach ($registers as $register) {
          if (strtotime('+ 10 minutes', strtotime($register['date_reg'])) > time()) {
            continue;
          }
          $status = 'E';
          $altan  = 0;
          if ($register['sale_type'] == 'H' && count($extraH)) {
            $dataNumber = $this->model->getDataNumber($register['msisdn']);

            foreach ($extraH as $ext) {
              if (!empty($ext['offer_rel'])) {
                $sup = $this->model->getsupOffertFromPrimary($register['codeAltan']);
                if (empty($sup) || (!empty($sup['codeAltan']) && $sup['codeAltan'] != $ext['offer_rel'])) {
                  continue;
                }
              }

              if ($ext['doProfile'] == 'Y') {
                $next          = false;
                $responseAltan = $this->model->executeCurlAltan(
                  "profile/" . $register['msisdn'],
                  "POST",
                  json_encode(
                    ['apiKey' => $register['api_key']]
                  )
                );

                if ($responseAltan->error && !isServerTest) {
                  $this->common->sendSlackNotification2(
                    'falló consulta a profile en servicio (extra).',
                    'alert',
                    [
                      'MSISDN' => $register['msisdn'],
                      'altan'  => !empty($responseAltan->original) ? $responseAltan->original : $responseAltan->data,
                    ],
                    $request
                  );
                }

                if (
                  !$responseAltan->error &&
                  $responseAltan->data->status == 'success' &&
                  strtolower($responseAltan->data->msisdn->status) == 'active'
                  &&
                  (
                    empty($responseAltan->data->msisdn->is_reduced) ||
                    $responseAltan->data->msisdn->is_reduced == false
                  )
                ) {
                  $next = true;
                }
              } else {
                $next = true;
              }

              if ($next) {
                if ($ext['isSup']) {
                  $url = "supplementary/" . $register['msisdn'];
                } else {
                  $url = "subscribers/" . $register['msisdn'];
                }

                $json = array(
                  'apiKey' => $register['api_key'],
                  'offer'  => $ext['offer'],
                  'lat'    => $dataNumber['lat'],
                  'lng'    => $dataNumber['lng']);

                $responseAltan = $this->model->executeCurlAltan(
                  $url,
                  "POST",
                  json_encode($json)
                );

                if (!$responseAltan->error && $responseAltan->data->status == 'success') {
                  $altan  = $responseAltan->data->orderId;
                  $status = 'A';
                } else {
                  if (!isServerTest) {
                    $this->common->sendSlackNotification2(
                      'falló activación de servicio (extra).',
                      'alert',
                      [
                        'MSISDN' => $register['msisdn'],
                        'altan'  => !empty($responseAltan->original) ? $responseAltan->original : $responseAltan->data,
                        'offert' => $ext['offer']],
                      $request
                    );
                  }
                }
              }

              $this->model->insertExtra([
                'sale_id'      => $register['id'],
                'extra'        => $ext['id'],
                'order'        => !empty($altan) ? $altan : 0,
                'response'     => (String) json_encode($responseAltan),
                'type_trigger' => 'P',
                'status'       => !empty($status) ? $status : 'E']);
            }
          }

          if ($register['sale_type'] == 'T' && count($extraT)) {
            foreach ($extraT as $ext) {
              if (!empty($ext['offer_rel'])) {
                $sup = $this->model->getsupOffertFromPrimary($register['codeAltan']);
                if (empty($sup) || (!empty($sup['codeAltan']) && $sup['codeAltan'] != $ext['offer_rel'])) {
                  continue;
                }
              }

              if ($ext['doProfile'] == 'Y') {
                $next          = false;
                $responseAltan = $this->model->executeCurlAltan(
                  "profile/" . $register['msisdn'],
                  "POST",
                  json_encode(
                    ['apiKey' => $register['api_key']]
                  )
                );

                if ($responseAltan->error && !isServerTest) {
                  $this->common->sendSlackNotification2(
                    'falló consulta a profile en servicio (extra).',
                    'alert',
                    [
                      'MSISDN' => $register['msisdn'],
                      'altan'  => !empty($responseAltan->original) ? $responseAltan->original : $responseAltan->data,
                    ],
                    $request
                  );
                }

                if (
                  !$responseAltan->error &&
                  $responseAltan->data->status == 'success' &&
                  strtolower($responseAltan->data->msisdn->status) == 'active'
                  &&
                  (
                    empty($responseAltan->data->msisdn->is_reduced) ||
                    $responseAltan->data->msisdn->is_reduced == false
                  )
                ) {
                  $next = true;
                }
              } else {
                $next = true;
              }

              if ($next) {
                if ($ext['isSup']) {
                  $url = "supplementary/" . $register['msisdn'];
                } else {
                  $url = "subscribers/" . $register['msisdn'];
                }

                $json = array(
                  'apiKey' => $register['api_key'],
                  'offer'  => $ext['offer']);

                $responseAltan = $this->model->executeCurlAltan(
                  $url,
                  "POST",
                  json_encode($json)
                );

                if (!$responseAltan->error && $responseAltan->data->status == 'success') {
                  $altan  = $responseAltan->data->orderId;
                  $status = 'A';
                } else {
                  if (!isServerTest) {
                    $this->common->sendSlackNotification2(
                      'falló activación de servicio (extra).',
                      'alert',
                      [
                        'MSISDN' => $register['msisdn'],
                        'altan'  => !empty($responseAltan->original) ? $responseAltan->original : $responseAltan->data,
                        'offert' => $ext['offer']],
                      $request
                    );
                  }
                }
              }

              $this->model->insertExtra([
                'sale_id'      => $register['id'],
                'extra'        => $ext['id'],
                'order'        => !empty($altan) ? $altan : 0,
                'response'     => (String) json_encode($responseAltan),
                'type_trigger' => 'P',
                'status'       => !empty($status) ? $status : 'E']);
            }
          }
        }
      }

      $this->model->unblockCron('api-register-extra');
    }

    return $response->withJson(['mensaje' => $msg]);
  }

/*
  * Request que se debe ejecutar desde un cron cada minuto y envia las notificaciones al slack registradas en la tabla de logs
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Get(
  *     path="/send-alert-logs",
  *     tags={"Recargas"},
  *     operationId="send-alert-logs", 
  *     summary="Test server status",      
  *     description="Request que se debe ejecutar desde un cron cada minuto y envia las notificaciones al slack registradas en la tabla de logs",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function sendAlertLogs(Request $request)
  {
    $msg = "OK";

    if (!$this->model->isBlockedCron('recharge-slack')) {
      //Bloqueando los llamados del cron
      $this->model->blockCron('recharge-slack');
      $alerts = $this->model->getPendingAlerts();

      foreach ($alerts as $data) {
        $this->common->sendSlackNotification2(
          'Notificación: ' . (!empty($data['error']) ? $data['error'] : ''),
          'alert',
          $data,
          false
        );

        $this->model->alertNotified($data['id']);
      }

      //Desbloqueando cron
      $this->model->unblockCron('recharge-slack');
    }

    return $response->withJson(['mensaje' => $msg]);
  }

/*
  * Request que se debe ejecutar desde un cron una vez al dia preferiblemente a las 23:59
  * elimina los registros de la tabla logs que cumplan con la condición de tiempo
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Get(
  *     path="/remove-logs",
  *     tags={"Recargas"},
  *     operationId="remove-logs", 
  *     summary="Test server status",      
  *     description="Request que se debe ejecutar desde un cron una vez al dia preferiblemente a las 23:59 elimina los registros de la tabla logs que cumplan con la condición de tiempo",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function removeLogs(Request $request)
  {
    $msg = "OK";

    $date = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . '- ' . ttl_logs . ' months'));

    $this->model->deleteLogs($date);

    return $response->withJson(['mensaje' => $msg]);
  }

/*
  * Request para ser ejecutado desde un cron, genera archivo de conciliación para bluelabel
  * Va a consultar todas las recargas del dia anterior de 12:00:00 - 23:59:59.
  * Debe ejecutarse todos los días a las 02:00
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Get(
  *     path="/file-bluelabel",
  *     tags={"Recargas"},
  *     operationId="file-bluelabel", 
  *     summary="Test server status",      
  *     description="Request para ser ejecutado desde un cron, genera archivo de conciliación para bluelabel",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function fileBluelabel(Request $request)
  {
        $date = date('Y-m-d', strtotime(date('Y-m-d') . '- 1 days'));

        $recharges = $this->model->getRechargesByConcentrator(
          key_bluelabel,
          $date . ' 00:00:00',
          $date . ' 23:59:59'
        );

        $fileName = 'NETWEY_' . date('Ymd', strtotime(date('Y-m-d') . '- 1 days')) . '.txt';

        $file = fopen('conciliation/' . $fileName, 'w');

        fputcsv(
          $file,
          ['DATE', 'TIME', 'REFERENCE', 'AMOUNT', 'AUTHORIZATION', 'PRODUCT', 'DEVICEID'],
          '|'
        );

        foreach ($recharges as $recharge) {
          fputcsv(
            $file,
            [
              date('Y-m-d', strtotime($recharge['date_reg'])),
              date('H:i:s', strtotime($recharge['date_reg'])),
              $recharge['msisdn'],
              number_format($recharge['amount'], 2, '.', ','),
              $recharge['unique_transaction'],
              strtoupper($recharge['description']),
              $recharge['id_point']],
            '|',
            '"'
          );
        }

        fclose($file);

        $ftp = new \FtpClient\FtpClient();
        $ftp->connect(ip_ftp_bluelabel, false, 50);
        $ftp->login(user_ftp_bluelabel, password_ftp_bluelabel);
        $ftp->pasv(true);

        $ftp->putFromString(
          folder_ftp_bluelabel . '/' . $fileName,
          file_get_contents('conciliation/' . $fileName)
        );
  }

/**
  * Metodo para carga masiva de servicios de rentención
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Post(
  *     path="/massive-retention/{email}",
  *     tags={"Recargas"},
  *     operationId="massive-retention", 
  *     summary="Test server status",  
  *     description="Metodo para carga masiva de servicios de rentención",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function massiveRetention(Request $request)
  {
        $timeStart = microtime(true);
        $idLog     = $this->common->logV2(
          false,
          null,
          $request
        );

        $res = ['status' => 'FAIL', 'msg' => 'ocurrio un error.'];

        $email     = $args['email'];
        $user      = $this->model->getDataUser(trim($email));
        $csv       = $request->getBody()->getContents();
        $delimiter = ',';

        if ($email && $csv && !empty($user)) {
          $group = 'ret-' . date('YmdHis');
          $lines = explode("\n", $csv);

          //procesando csv
          $ban     = true;
          $process = 0;
          $error   = [];
          foreach ($lines as $line) {
            if ($ban) {
              $ban = false;
              continue;
            }

            $columns = explode($delimiter, $line);

            if (count($columns) == 3) {
              $dn      = $this->common->cleanTxt($columns[0]);
              $service = $this->common->cleanTxt($columns[1]);
              $reason  = $this->common->cleanTxt($columns[2]);

              if (!empty($dn) && strlen($dn) == 10 && !empty($service) && !empty($reason)) {
                //validando dn
                $dataDN = $this->model->getDataNumber($dn);

                if ($dataDN) {
                  //validando servicio
                  $dataService = $this->model->getServiceByIdAndType(
                    $service,
                    $dataDN['dn_type']
                  );

                  if (!empty($dataService) && $dataService['type'] == 'R') {
                    //validando motivo
                    $reasond = $this->model->getDataReason($reason);

                    if (!empty($reasond)) {
                      //Guardando en tabla de retenciones masivas
                      $this->model->createRetentionReg([
                        'user'       => $email,
                        'msisdn'     => $dn,
                        'service_id' => $service,
                        'offer'      => $dataService['codeAltan'],
                        'reason_id'  => $reason,
                        'group'      => $group,
                        'date_reg'   => date('Y-m-d H:i:s')]);

                      $process++;
                    } else {
                      $error[] = [
                        'motivo' => 'Motivo no válido',
                        'linea'  => $line];
                    }
                  } else {
                    $error[] = [
                      'motivo' => 'Servicio no válido',
                      'linea'  => $line];
                  }
                } else {
                  $error[] = [
                    'motivo' => 'Cliente no válido',
                    'linea'  => $line];
                }
              } else {
                $error[] = [
                  'motivo' => 'Datos no válidos',
                  'linea'  => $line];
              }
            } else {
              $error[] = [
                'motivo' => 'Formato no válido',
                'linea'  => $line];
            }
          }

          $res = [
            'status'             => 'OK',
            'group'              => $group,
            'retentions_process' => $process,
            'errors'             => $error];
        } else {
          $res['msg'] = 'Faltan datos para procesar el archivo';
        }

        $this->common->logV2(
          $idLog,
          (String) json_encode($res),
          null,
          null,
          $timeStart,
          null,
          'NN',
          $res['status'] != 'OK' ? $res['msg'] : null
        );

        return $response->withJson($res);
  }

/*
  * Request para ejecutar desde cron, activa las solicitudes de servicio de rentención
  *
  * @param Request $request
  *
  * @return mixed
  *
  * @throws
  * 
  * @OA\Get(
  *     path="/process-retention",
  *     tags={"Recargas"},
  *     operationId="process-retention", 
  *     summary="Test server status",      
  *     description="Request para ejecutar desde cron, activa las solicitudes de servicio de rentención",
  *     @OA\Response(response="default", description="Welcome page")
  * )    
*/
  public function processRetention(Request $request)
  {
        if (!$this->model->isBlockedCron('api-retention')) {
          //Bloqueando los llamados de cron de recargas
          $this->model->blockCron('api-retention');
          $servicesAct = $this->model->getActiveRetentions();

          $idConc = prom_concentrador;
          $key    = prom_key;
          foreach ($servicesAct as $act) {
            $msgErr   = false;
            $dataServ = $this->model->getServiceById($act['service_id']);

            if ($dataServ) {
              $resAltan = $this->model->executeCurlAltan(
                "supplementary/" . $act['msisdn'],
                "POST",
                json_encode([
                  'apiKey' => $key,
                  'offer'  => $act['offer']])
              );

              if (!$resAltan->error && $resAltan->data->status == 'success') {
                $orderId = $resAltan->data->orderId;
                $unique  = uniqid('RET-') . microtime(true);
                $date    = date('Y-m-d H:i:s');

                try {
                  //Creando venta
                  $sql = "INSERT INTO islim_sales (
                     services_id,
                     concentrators_id,
                     api_key,
                     order_altan,
                     unique_transaction,
                     codeAltan,
                     type,
                     id_point,
                     description,
                     amount,
                     amount_net,
                     com_amount,
                     msisdn,
                     date_reg,
                     sale_type,
                     status,
                     is_migration
                    )
                    values (
                     :services_id,
                     :concentrators_id,
                     :api_key,
                     :order_altan,
                     :unique_transaction,
                     :codeAltan,
                     'SR',
                     'RETENTION',
                     :description,
                     '0',
                     '0',
                     '0',
                     :msisdn,
                     :date_reg,
                     :sale_type,
                     'A',
                     'N'
                    )";

                  $excSql = $this->db->prepare($sql);
                  $excSql->bindParam(':services_id', $act['service_id']);
                  $excSql->bindParam(':concentrators_id', $idConc);
                  $excSql->bindParam(':api_key', $key);
                  $excSql->bindParam(':order_altan', $orderId);
                  $excSql->bindParam(':unique_transaction', $unique);
                  $excSql->bindParam(':codeAltan', $act['offer']);
                  $excSql->bindParam(':description', $dataServ['title']);
                  $excSql->bindParam(':msisdn', $act['msisdn']);
                  $excSql->bindParam(':date_reg', $date);
                  $excSql->bindParam(':sale_type', $dataServ['service_type']);
                  $excSql->execute();
                  $idSale = $this->db->lastInsertId();

                  //Creando registro de activación
                  $sql = "INSERT INTO islim_retention_activates (
                     msisdn,
                     user_creator,
                     services_id,
                     reason_id,
                     sales_id,
                     status,
                     is_view,
                     date_reg
                    )
                    values (
                     :msisdn,
                     :user_creator,
                     :services_id,
                     :reason_id,
                     :sales_id,
                     'A',
                     'N',
                     :date_reg
                    )";

                  $excSql = $this->db->prepare($sql);
                  $excSql->bindParam(':msisdn', $act['msisdn']);
                  $excSql->bindParam(':user_creator', $act['user']);
                  $excSql->bindParam(':services_id', $act['service_id']);
                  $excSql->bindParam(':reason_id', $act['reason_id']);
                  $excSql->bindParam(':sales_id', $idSale);
                  $excSql->bindParam(':date_reg', $date);
                  $excSql->execute();

                  //Actualizando estatus de la solicitud
                  $this->model->updateReqRetention($act['id'], 'P');
                } catch (PDOException $e) {
                  print_r($e->getMessage());
                }
              } else {
                //no se activo el servicio
                $msgErr = !empty($resAltan->original) ? $resAltan->original : $resAltan->data;

                $this->model->updateReqRetention($act['id'], 'E', $msgErr);
              }
            } else {
              $msgErr = 'No se consiguió el servicio.';
              $this->model->updateReqRetention($act['id'], 'E', $msgErr);
            }

            if ($msgErr) {
              $this->common->sendSlackNotification2(
                'No se pudo activar el servicio de retención al cliente',
                'alert',
                [
                  'solicitud' => $act['id'],
                  'grupo'     => $act['group_req'],
                  'msisdn'    => $act['msisdn'],
                  'servicio'  => $dataServ ? $dataServ['title'] : 'Desconocido',
                  'usuario'   => $act['user'],
                  'error'     => $msgErr],
                $request,
                slack_retention
              );
            }
          }
        }

        //Desbloqueando cron
        $this->model->unblockCron('api-retention');

        return $response->withJson(['status' => 'OK']);
  }
}