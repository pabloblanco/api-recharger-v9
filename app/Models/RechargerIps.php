<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RechargerIps extends Model
{
  protected $table = 'islim_recharger_ips';

  protected $fillable = [
    'id',
    'token',
    'ip',
    'status',
    'date_reg',
    'propietario'];

  public $timestamps = false;

/**
 * [isIpValid Consulta si la IP de donde hace la peticion a la api esta registrada y activa]
 * @param  [type]  $ipRequest [description]
 * @return boolean            [description]
 */
  public static function isIpValid($ipRequest)
  {
    $IP = self::where([['ip', $ipRequest],
        ['status', 'A']])
      ->first();

    if (!empty($IP)) {
      return true;
    }
    return false;
  }

}