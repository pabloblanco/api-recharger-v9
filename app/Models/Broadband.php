<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broadband extends Model {
	protected $table = 'Islim_broadbands';

	protected $fillable = [
		'id', 'broadband', 'num_broad', 'status'
    ];

    public $timestamps = false;

    /**
     * Metodo para seleccionar conexion a la bd, escritura-lectura o solo escritura
     * @param String $typeCon
     *
     * @return App\Product
    */
    public static function getConnect($typeCon = false){
        if($typeCon){
            $obj = new Broadband;
            $obj->setConnection($typeCon == 'W' ? 'netwey-w' : 'netwey-r');

            return $obj;
        }
        return null;
    }
}