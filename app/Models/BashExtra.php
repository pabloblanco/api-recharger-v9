<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BashExtra extends Model {
    protected $table = 'islim_bash_extra';

    protected $fillable = [
        
    ];

  public function updateExtraMasive($id = false, $data = [])
  {
    if ($id && count($data)) {
      $date = date('Y-m-d H:i:s');

      if ($data['status'] == 'P') {
        $sql = "UPDATE islim_bash_extra
                        SET status = 'P', order_id = :ord, date_reg = :date_reg, offer = :offer
                        WHERE id = :id";

        $exec = $this->bd->prepare($sql);
        $exec->bindParam(':ord', $data['order']);
        $exec->bindParam(':date_reg', $date);
        $exec->bindParam(':offer', $data['offer']);
        $exec->bindParam(':id', $id);
        $exec->execute();
      } else {
        $sql = "UPDATE islim_bash_extra
                        SET status = 'E', response = :res, date_reg = :date_reg
                        WHERE id = :id";

        $exec = $this->bd->prepare($sql);
        $exec->bindParam(':res', $data['response']);
        $exec->bindParam(':date_reg', $date);
        $exec->bindParam(':id', $id);
        $exec->execute();
      }
    }

    return false;
  }

  public function getBashExtra()
  {
    $sql = "SELECT * FROM islim_bash_extra
        WHERE status = 'A'
        ORDER BY type DESC
        LIMIT 30";

    $exec = $this->bd->prepare($sql);
    $exec->execute();

    return $exec->fetchAll();
  }

}