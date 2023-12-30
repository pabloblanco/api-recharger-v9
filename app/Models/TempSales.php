<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concentrator extends Model {
	protected $table = 'islim_tmp_sales';

	protected $fillable = [

    ];
    
    public $timestamps = false;

  //retorna el id de la transaccion
  public function getIdTransaction($idConc = false)
  {
    if ($idConc) {
      $transaction     = $idConc . uniqid() . time();
      $sqlTotalSale    = "SELECT id FROM islim_tmp_sales WHERE unique_transaction = :transaction";
      $excSqlTotalSale = $this->bd->prepare($sqlTotalSale);
      $excSqlTotalSale->bindParam(':transaction', $transaction);
      $excSqlTotalSale->execute();
      if ($excSqlTotalSale->rowCount() > 0) {
        $transaction = false;
        do {
          $transaction = $this->getIdTransaction($idConc);
        } while (!$transaction);
        return $transaction;
      } else {
        return $transaction;
      }
    }
    return false;
  }

  //retorna una venta de la tabla temporal dado un numero de transaccion y un id de concentrador
  public function getTmpSale($transactionNumber = false, $idcon = false)
  {
    if ($transactionNumber && $idcon) {
      $sqlTmSale    = "SELECT * FROM islim_tmp_sales WHERE unique_transaction = :transaction AND concentratos_id = :idconc";
      $excSqlTmSale = $this->bd->prepare($sqlTmSale);
      $excSqlTmSale->bindParam(':transaction', $transactionNumber);
      $excSqlTmSale->bindParam(':idconc', $idcon);
      $excSqlTmSale->execute();
      $data = $excSqlTmSale->fetch();
      if (!empty($data)) {
        return $data;
      }
    }
    return false;
  }

  //actualiza un registro de la tabla de ventas temporal dado su id y un estatus.
  public function updateTmpSale($idTmpSale = false, $status = false)
  {
    $common = new common();
    if ($idTmpSale && $status) {
      try {
        $date       = date("Y-m-d H:i:s");
        $isDate     = true;
        $sqlTmpSale = "UPDATE islim_tmp_sales SET status = :status [date] WHERE id = :id";
        if ($status == 'A' || $status == 'P') {
          $sqlTmpSale = str_replace('[date]', ', date_fase2 = :date', $sqlTmpSale);
        }

        /*elseif($status == 'P')
        $sqlTmpSale = str_replace('[date]', ', date_fase3 = :date', $sqlTmpSale);*/ else {
          $sqlTmpSale = str_replace('[date]', '', $sqlTmpSale);
          $isDate     = false;
        }

        $excSqlTmpSale = $this->bd->prepare($sqlTmpSale);
        $excSqlTmpSale->bindParam(':status', $status);
        if ($isDate) {
          $excSqlTmpSale->bindParam(':date', $date);
        }

        $excSqlTmpSale->bindParam(':id', $idTmpSale);
        return $excSqlTmpSale->execute();
      } catch (PDOException $e) {
        if (debug) {
          $common->log('updateTmpSale: ' . $e->getMessage(), null, 'out', 'error');
        }
        $error = array('status' => 'FAIL', 'cod' => 'SYSTEM_FAILURE', 'msg' => 'Falla en el sistema.');
        return $response->withJson($error);
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
            $obj = new Concentrator;
            $obj->setConnection($typeCon == 'W' ? 'netwey-w' : 'netwey-r');

            return $obj;
        }
        return null;
    }

    public static function getConcentrators(){
        return self::getConnect('R')
                    ->select('id', 'name', 'business_name', 'balance')
                    ->where('status', 'A')
                    ->get();
    }
}