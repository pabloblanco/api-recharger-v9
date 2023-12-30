<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class mobility extends Model
{
    protected $table = 'islim_mobility';
    public $timestamps = false;

    /**
     * Metodo para seleccionar conexion a la bd, escritura-lectura o solo escritura
     * @param String $typeCon
     *
     * @return App\Product
    */
    public static function getConnect($typeCon = false){
        if($typeCon){
            $obj = new mobility;
            $obj->setConnection($typeCon == 'W' ? 'netwey-w' : 'netwey-r');

            return $obj;
        }
        return null;
    }

    public static function getClientSuspend() {
    	$cs = mobility::getConnect('R')->select(
    							'islim_mobility.date_affec',
    						   	'islim_client_netweys.msisdn',
    						   	'islim_clients.name',
    						   	'islim_clients.last_name',
    						   	'islim_clients.phone_home',
    						   	'islim_mobility.lat',
    						   	'islim_mobility.lng'
    						  )
    				  ->join('islim_client_netweys','islim_client_netweys.msisdn','=','islim_mobility.msisdn')
    				  ->join('islim_clients','islim_clients.dni','=','islim_client_netweys.clients_dni')
                      ->where('islim_mobility.status','A')
    				  ->orderBy('islim_mobility.date_affec','DESC')->get();
    	return $cs;
    }
}
