<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogRecharge extends Model
{
  protected $table = 'islim_log_recharge';

  protected $fillable = [
    'id',
    'ip', 
    'auth', 
    'request', 
    'msisdn', 
    'headers', 
    'data_in', 
    'data_out', 
    'erro'
  ];

  public $timestamps = false;

  //Retorna las alertas pendientes
  public function getPendingAlerts()
  {
    $sql = "SELECT id, ip, auth, request, msisdn, headers, data_in, data_out, error, time, message FROM islim_log_recharge WHERE notify = 'P'";

    $excSql = $this->bd->prepare($sql);
    $excSql->execute();

    return $excSql->fetchAll();
  }

  /*Elimina los logs*/
  public function deleteLogs($date)
  {
    $sql = "DELETE FROM islim_log_recharge WHERE date_reg <= :date";

    $excSql = $this->bd->prepare($sql);
    $excSql->bindParam(':date', $date);
    $excSql->execute();
  }

  //Marca como notificado un log
  public function alertNotified($id = false)
  {
    if ($id) {
      $sql    = "UPDATE islim_log_recharge SET notify = 'N' WHERE id = :id";
      $excSql = $this->bd->prepare($sql);
      $excSql->bindParam(':id', $id);
      $excSql->execute();

      return true;
    }

    return false;
  }

}