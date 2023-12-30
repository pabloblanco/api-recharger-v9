<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RechargerToken extends Model
{
  protected $table = 'islim_recharger_token';

  protected $fillable = [
    'token',
    'type',
    'status',
    'date_create'];

  public $timestamps = false;

  /**
   * [isTokenValid Retorna si es valido el token de conexion]
   * @return boolean [description]
   */
  public static function isTokenValid($request)
  {
    $entorno = env('APP_ENV', 'local');
    $type    = '';
    if ($entorno == 'local' || $entorno == 'test') {
      $type = 'D';
    } elseif ($entorno == 'production') {
      $type = 'P';
    }

    $KEY = self::select('islim_recharger_ips.id')
      ->join('islim_recharger_ips',
        'islim_recharger_ips.token',
        'islim_recharger_token.token')
      ->where([
        ['islim_recharger_ips.token', $request->bearerToken()],
        ['islim_recharger_token.type', $type],
        ['islim_recharger_token.status', 'A'],
        ['islim_recharger_ips.status', 'A'],
        ['islim_recharger_ips.ip', $request->ip()]])
      ->first();

    if (!empty($KEY)) {
      return true;
    }
    return false;
  }

}