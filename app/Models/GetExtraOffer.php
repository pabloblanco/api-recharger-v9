<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GetExtraOffer extends Model {
    protected $table = 'islim_get_extra_offert';

    protected $fillable = [
        
    ];

  /*Metodo para optener ofertas suplementaria nav nocturna para altan*/
  public function getDNsExtraOffert()
  {
    $sql  = "SELECT * FROM islim_get_extra_offert WHERE offer IS NULL";
    $exec = $this->bd->prepare($sql);
    $exec->execute();

    return $exec->fetchAll();
  }

  public function updateOfferExtra($id = false, $offer = false)
  {
    if ($id && $offer) {
      $sql  = "UPDATE islim_get_extra_offert SET offer = :offer WHERE id = :id";
      $exec = $this->bd->prepare($sql);
      $exec->bindParam(':offer', $offer);
      $exec->bindParam(':id', $id);
      $exec->execute();

      return true;
    }
    return false;
  }

}