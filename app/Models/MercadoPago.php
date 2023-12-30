<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class MercadoPago extends Model {
    protected $table = 'islim_mercado_pago';

    protected $fillable = [

    ];

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = false;

    /**
     * Metodo para seleccionar conexion a la bd, escritura-lectura o solo escritura
     * @param String $typeCon
     *
     * @return App\MercadoPago
    */
    public static function getConnect($typeCon = false){
        if($typeCon){
            $obj = new MercadoPago;
            $obj->setConnection($typeCon == 'W' ? 'netwey-w' : 'netwey-r');

            return $obj;
        }
        return null;
    }


    public static function getPenddingPaymentRef($date_ini,$date_end){
        $dini=substr($date_ini,6,4)."-".substr($date_ini,3,2)."-".substr($date_ini,0,2)." 00:00:00";
        $dend=substr($date_end,6,4)."-".substr($date_end,3,2)."-".substr($date_end,0,2)." 23:59:59";

        $clients=MercadoPago::getConnect('R')->select(
                'islim_clients.name as Nombre',
                'islim_clients.last_name as Apellido',
                'islim_clients.email as Email',
                'islim_clients.phone_home as Telefono',
                'islim_mercado_pago.date_reg as Fecha_Registro',
                'islim_mercado_pago.payment_method as Metodo',
                'islim_inv_articles.title as Equipo',
                'islim_mercado_pago.status as Estado'
            )
            ->join('islim_cars', 'islim_cars.id', 'islim_mercado_pago.service_id')
            ->join('islim_cars_detail', 'islim_cars_detail.car_id', 'islim_cars.id')
            ->join('islim_clients', 'islim_clients.dni', 'islim_cars.ine')
            ->join('islim_inv_articles','islim_inv_articles.id','islim_cars_detail.product_id')
            ->whereIn('islim_mercado_pago.status', ['cancelled', 'pending'])
            ->where('islim_mercado_pago.type','S')
            ->where([
                ['islim_mercado_pago.date_reg', '>=', $dini],
                ['islim_mercado_pago.date_reg', '<=', $dend]
            ]);

        return $clients;
    }

    public static function getPayment($order = false){
        if($order){
            return self::getConnect('R')
                        ->select('id', 'payment_method')
                        ->where([
                            ['order_id', $order],
                            ['type', 'S'],
                            ['status', 'approved']
                        ])
                        ->first();
        }

        return null;
    }
}