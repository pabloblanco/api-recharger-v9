<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraOfferActive extends Model {
    protected $table = 'islim_extra_offer_active';

    protected $fillable = [
        
    ];

  public function insertExtra($data = [])
  {
    if (count($data)) {
      $date = date('Y-m-d H:i:s');

      $sql = "INSERT INTO islim_extra_offer_active
          (
           sale_id,
           extra_offer_id,
           order_id,
           response,
           type_trigger,
           date_reg,
           status
          )
          VALUES (
           :sale_id,
           :extra,
           :order,
           :response,
           :type_trigger,
           :date_reg,
           :status
          )";

      $excI = $this->bd->prepare($sql);
      $excI->bindParam(':sale_id', $data['sale_id']);
      $excI->bindParam(':extra', $data['extra']);
      $excI->bindParam(':order', $data['order']);
      $excI->bindParam(':response', $data['response']);
      $excI->bindParam(':type_trigger', $data['type_trigger']);
      $excI->bindParam(':date_reg', $date);
      $excI->bindParam(':status', $data['status']);
      $excI->execute();

      return true;
    }

    return false;
  }

}