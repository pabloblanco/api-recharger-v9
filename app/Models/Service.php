<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Broadband;
use App\Periodicity;
use App\ServiceChanel;
use App\PackPrices;
use App\ListDns;

use Illuminate\Support\Facades\Log;

class Service extends Model {
	protected $table = 'islim_services';

	protected $fillable = [
		'id',
        'periodicity_id',
        'codeAltan',
        'title',
        'description',
        'price_pay',
        'price_remaining',
        'broadband',
        'supplementary',
        'date_reg',
        'status',
        'type',
        'method_pay',
        'gb',
        'plan_type',
        'service_type',
        'primary_service',
        'type_hbb',
        'min',
        'sms',
        'is_band_twenty_eight'
    ];

    public $timestamps = false;


  //Retorna servicio padre del plan que tiene el dn dado (movilidad)
  public function getServiceFather($service = false)
  {
    if ($service) {
      $sql  = "SELECT id, type, codeAltan, primary_service FROM islim_services WHERE id = :idService";
      $esql = $this->bd->prepare($sql);
      $esql->bindParam(':idService', $service);
      $esql->execute();
      $data = $esql->fetch();

      if ($data['type'] == 'A') {
        $sql = "SELECT id FROM islim_services
                        WHERE status = 'A' AND
                        service_type = 'T' AND
                        type = 'P' AND
                        codeAltan = :code";

        $esql = $this->bd->prepare($sql);
        $esql->bindParam(':code', $data['codeAltan']);
        $esql->execute();
        $data = $esql->fetch();

        if (!empty($data)) {
          return $data['id'];
        }
      } elseif (!empty($data['primary_service'])) {
        return $data['primary_service'];
      } elseif (!empty($data['id'])) {
        return $data['id'];
      }
    }

    return 0;
  }

    /******************************************************************/
  /* quitar desde aqui para abajo 
  */  
  
    /**
     * Metodo para seleccionar conexion a la bd, escritura-lectura o solo escritura
     * @param String $typeCon
     *
     * @return App\Product
    */
    public static function getConnect($typeCon = false){
        if($typeCon){
            $obj = new Service;
            $obj->setConnection($typeCon == 'W' ? 'netwey-w' : 'netwey-r');

            return $obj;
        }
        return null;
    }

    public static function getActiveServiceByType($type = false, $type_service = false, $band_te = false){
        $data = self::getConnect('R')
                     ->select(
                        'id',
                        'title',
                        'description',
                        'price_pay'
                     )
                     ->where('status', 'A');

        if($type){
            $data->where('service_type', $type);
        }

        if($type_service){
            $data->where('type', $type_service);
        }

        if($band_te){
            $data->where('is_band_twenty_eight', $band_te);
        }

        return $data->get();
    }

    public static function getServicesFullData($status = [], $type = false){
        if(count($status)){
            $data = self::getConnect('R')
                          ->select(
                            'islim_periodicities.periodicity',
                            'Islim_broadbands.num_broad',
                            'islim_altan_codes.codeAltan as sup',
                            'islim_services.*',
                            'islim_blim_services.name as blim_service_name'
                          )
                          ->join(
                            'islim_periodicities',
                            'islim_periodicities.id',
                            'islim_services.periodicity_id'
                          )
                          ->leftJoin('Islim_broadbands',function($join){
                            $join->on(
                                'Islim_broadbands.broadband',
                                'islim_services.broadband'
                            )
                            ->where('Islim_broadbands.status', 'A');
                          })
                          ->leftJoin('islim_altan_codes', function($join){
                            $join->on(
                                'islim_altan_codes.services_id',
                                'islim_services.id'
                            )
                            ->where([
                                ['islim_altan_codes.status', 'A'],
                                ['islim_altan_codes.supplementary', 'Y']
                            ]);
                          })
                          ->leftJoin(
                            'islim_blim_services',
                            'islim_blim_services.id',
                            'islim_services.blim_service'
                          )
                          ->where([
                            ['islim_periodicities.status', 'A']
                          ])
                          ->whereIn('islim_services.status', $status);

            if($type){
                $data->where('islim_services.type', $type);
            }

            //  $query = vsprintf(str_replace('?', '%s', $data->toSql()), collect($data->getBindings())->map(function ($binding) {
            //     return is_numeric($binding) ? $binding : "'{$binding}'";
            // })->toArray());

            // Log::info($query);


            $data = $data->orderBy('islim_services.id', 'DESC')->get();

            foreach($data as $service){
                $service->concentrators = ServiceChanel::getConcService($service->id);
                $service->channels = ServiceChanel::getChService($service->id);
                $service->lists = ServiceChanel::getListService($service->id);
            }

            return $data;
        }

        return [];
    }

    public static function getServices($status, $type) {
        $services;
        if (!isset($type))  {
            $services = Service::where('status', $status)->get();
        } else {
            $services = Service::where(['status' => $status, 'type' => $type])->get();
        }
        foreach ($services as $service) {
            $service->periodicity = Periodicity::where(['status' => 'A', 'id' => $service->periodicity_id])->first();
            $service->broadband = Broadband::where(['status' => 'A', 'broadband' => $service->broadband])->first();
            $codeAltanSuplementary = AltanCode::select('codeAltan')->where(['services_id' => $service->id, 'status' => 'A', 'supplementary' => 'Y'])->first();
            $service->codeAltanSuplementary = isset($codeAltanSuplementary) ? $codeAltanSuplementary->codeAltan : null;

            $service->concentrators = ServiceChanel::select('islim_concentrators.id', 'islim_concentrators.name')
                                                   ->join(
                                                        'islim_concentrators',
                                                        'islim_concentrators.id',
                                                        '=',
                                                        'islim_service_channel.id_concentrator'
                                                    )
                                                   ->where([
                                                        ['islim_service_channel.status', 'A'],
                                                        ['islim_service_channel.id_service', $service->id],
                                                        ['islim_concentrators.status', 'A']
                                                    ])
                                                   ->get();

            $service->channels = ServiceChanel::select('islim_channels.id', 'islim_channels.name')
                                                   ->join(
                                                        'islim_channels',
                                                        'islim_channels.id',
                                                        '=',
                                                        'islim_service_channel.id_channel'
                                                    )
                                                   ->where([
                                                        ['islim_service_channel.status', 'A'],
                                                        ['islim_service_channel.id_service', $service->id],
                                                        ['islim_channels.status', 'A']
                                                    ])
                                                   ->get();

            $service->lists = ServiceChanel::select('islim_list_dns.id', 'islim_list_dns.name')
                                                   ->join(
                                                        'islim_list_dns',
                                                        'islim_list_dns.id',
                                                        '=',
                                                        'islim_service_channel.id_list_dns'
                                                    )
                                                   ->where([
                                                        ['islim_service_channel.status', 'A'],
                                                        ['islim_service_channel.id_service', $service->id],
                                                        ['islim_list_dns.status', 'A']
                                                    ])
                                                   ->get();
        }
        return $services;
    }

    public static function getService($id, $status){
        $service = Service::where(['id' => $id, 'status' => $status])->first();
        $service->periodicity = Periodicity::where(['status' => 'A'])->first();
        $service->broadband = Broadband::where(['status' => 'A', 'broadband' => $service->broadband])->first();
        return $service;
    }

    public static function getPeriodicity($id_service = false){
        if($id_service){
            return self::getConnect('R')
                         ->select('periodicity_id', 'periodicity', 'days')
                         ->join(
                            'islim_periodicities',
                            'islim_periodicities.id',
                            'islim_services.periodicity_id'
                         )
                         ->where('islim_services.id', $id_service)
                         ->first();
        }

        return null;
    }
}