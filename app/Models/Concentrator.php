<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concentrator extends Model {
	protected $table = 'islim_concentrators';

	protected $fillable = [
        'id',
        'name',
        'rfc',
        'email',
        'dni',
        'business_name',
        'phone',
        'address',
        'balance',
        'commissions',
        'date_reg',
        'status',
        'postpaid',
        'amount_alert',
        'amount_allocate',
        'id_channel'
    ];
    
    public $timestamps = false;


  //retorna el saldo de un concentrador dado su id
  public function getBalanceConcentrator($idConc = false)
  {
    if ($idConc) {
      $sqlConcentrator    = "SELECT balance FROM islim_concentrators WHERE id = :idConc AND status = 'A'";
      $excSqlConcentrator = $this->bd->prepare($sqlConcentrator);
      $excSqlConcentrator->bindParam(':idConc', $idConc);
      $excSqlConcentrator->execute();
      $dataConc = $excSqlConcentrator->fetch();
      if (!empty($dataConc)) {
        return $dataConc['balance'];
      }
    }
    return false;
  }

  //actualiza saldo en controlador dado su id y nuevo saldo
  public function setBalanceConcentrator($idConc = false, $newAmount = 0)
  {
    $common = new common();
    if ($idConc && $newAmount >= 0) {
      try {
        $sqlConcentrator    = "UPDATE islim_concentrators SET balance = :amount WHERE id = :idConc AND status = 'A'";
        $excSqlConcentrator = $this->bd->prepare($sqlConcentrator);
        $excSqlConcentrator->bindParam(':amount', $newAmount);
        $excSqlConcentrator->bindParam(':idConc', $idConc);
        $excSqlConcentrator->execute();

        $sqlConcentrator    = "SELECT balance FROM islim_concentrators WHERE id = :idConc AND status = 'A'";
        $excSqlConcentrator = $this->bd->prepare($sqlConcentrator);
        $excSqlConcentrator->bindParam(':idConc', $idConc);
        $excSqlConcentrator->execute();
        $dataConc = $excSqlConcentrator->fetch();
        if (!empty($dataConc)) {
          return $dataConc['balance'];
        }
      } catch (PDOException $e) {
        if (debug) {
          $common->log('setBalanceConcentrator: ' . $e->getMessage(), null, 'out', 'error');
        }
        $error = array('status' => 'FAIL', 'cod' => 'SYSTEM_FAILURE', 'msg' => 'Falla en el sistema.');
        return $response->withJson($error);
      }
    }
    return false;
  }

  //Retorna los datos solicitados de un concentrador dado su id
  public function getConcentrator($idConc = false, $fields = '*')
  {
    if ($idConc) {
      $sqlConc    = "SELECT [fields] FROM islim_concentrators WHERE status = 'A' AND id = :id";
      $sqlConc    = str_replace('[fields]', $fields, $sqlConc);
      $excSqlConc = $this->bd->prepare($sqlConc);
      $excSqlConc->bindParam(':id', $idConc);
      $excSqlConc->execute();
      $data = $excSqlConc->fetch();
      if (!empty($data)) {
        return $data;
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