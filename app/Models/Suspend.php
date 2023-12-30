<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Suspend extends Model
{
    protected $table = 'islim_suspends';

    protected $fillable = [
        'msisdn',
        'response',
        'date_reg',
        'from'
    ];

    public $timestamps = false;

     /**
     * Metodo para seleccionar conexion a la bd, escritura-lectura o solo escritura
     * @param String $typeCon
     *
     * @return App\Suspend
    */
    public static function getConnect($typeCon = false){
        if($typeCon){
            $obj = new self;
            $obj->setConnection($typeCon == 'W' ? 'netwey-w' : 'netwey-r');
            return $obj;
        }
        return null;
    }
}
