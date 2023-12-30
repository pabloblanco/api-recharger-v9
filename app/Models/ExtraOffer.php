<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraOffer extends Model {
    protected $table = 'islim_extra_offer';

    protected $fillable = [
        
    ];

  public function getExtraBySup($sup = false)
  {
    if ($sup) {
      $sql = "SELECT * FROM islim_extra_offer
                    WHERE status = 'A' AND offer_rel = :sup
                    LIMIT 1";

      $exec = $this->bd->prepare($sql);
      $exec->bindParam(':sup', $sup);
      $exec->execute();

      return $exec->fetch();
    }

    return null;
  }

  public function getExtraService($type = false, $type_service = false)
  {
    if ($type && $type_service) {
      $sql = "SELECT * FROM islim_extra_offer
                    WHERE status = 'A' AND trigger_sale = :type
                    AND type_service = :type_service";

      $exec = $this->bd->prepare($sql);
      $exec->bindParam(':type', $type);
      $exec->bindParam(':type_service', $type_service);
      $exec->execute();

      $data = $exec->fetchAll();

      if (count($data)) {
        foreach ($data as $key => $value) {
          $today = time();
          $timeB = !empty($value['date_beg']) ? strtotime($value['date_beg']) : null;
          $timeE = !empty($value['date_end']) ? strtotime($value['date_end']) : null;

          if (!empty($timeB) && !empty($timeE) && ($timeB > $today || $timeE < $today)) {
            unset($data[$key]);
          } elseif (!empty($timeB) && $timeB > $today) {
            unset($data[$key]);
          } elseif (!empty($timeE) && $timeE < $today) {
            unset($data[$key]);
          }
        }

        return $data;
      }
    }

    return [];
  }

}