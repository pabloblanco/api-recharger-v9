<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SMSNotification extends Model
{
  protected $table = 'islim_sms_notifications';

  protected $fillable = [
    'bash',
    'msisdn',
    'phone_sms',
    'sms_type',
    'concentrator_id',
    'service',
    'sms_attribute',
    'sms',
    'response',
    'status',
    'date_reg',
    'date_process'
  ];

  protected $primaryKey = 'id';

  public $timestamps = false;

  /**
   * Metodo para seleccionar conexion a la bd, escritura-lectura o solo escritura
   * @param String $typeCon
   *
   * @return App\SMSNotification
   **/
  public static function getConnect($typeCon = false)
  {
    if ($typeCon) {
      $obj = new self;
      $obj->setConnection($typeCon == 'W' ? 'netwey-w' : 'netwey-r');

      return $obj;
    }
    return null;
  }

  public static function saveSms($data = []){
    return self::getConnect('W')
                ->insert([
                  'msisdn' => $data['msisdn'],
                  'sms_type' => 'O',
                  'concentrator_id' => 1,
                  'sms_attribute' => 'SMSFINSERVICEFIBRA',
                  'date_reg' => date('Y-m-d H:i:s')
                ]);
  }
}
