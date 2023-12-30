<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Concentrator;

class APIKey extends Model {
    protected $table = 'islim_api_keys';

    protected $fillable = [
        'api_key', 'concentrators_id', 'type', 'date_reg', 'status',
    ];

    protected $primaryKey = 'api_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

      //retorna el id del concentrador dado un key
  public function getIdConcentratorByKey($key = false)
  {
    if ($key) {
      $sqlConcentratorKey    = "SELECT concentrators_id FROM islim_api_keys WHERE api_key = :key AND status = 'A'";
      $excSqlConcentratorKey = $this->bd->prepare($sqlConcentratorKey);
      $excSqlConcentratorKey->bindParam(':key', $key);
      $excSqlConcentratorKey->execute();
      $dataKey = $excSqlConcentratorKey->fetch();
      if (!empty($dataKey)) {
        return $dataKey['concentrators_id'];
      }
    }
    return false;
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
            $obj = new APIKey;
            $obj->setConnection($typeCon == 'W' ? 'netwey-w' : 'netwey-r');

            return $obj;
        }
        return null;
    }

    public static function getAPIKey ($key) {
    	$apykey = APIKey::where('api_key', $key)->first();
    	$apykey->concentrator = Concentrator::find($apykey->concentrators_id);
    	return $apykey;
    }
}