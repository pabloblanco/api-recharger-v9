<?php
/****************************************************************************************************************************
*   2021-2022 GDALab
*****************************************************************************************************************************
* 
*   NOTICE OF LICENSE
*
*
*   DISCLAIMER
*
*
*****************************************************************************************************************************
*
*   @author     GDALab <contact@gdalab.com>
*   @copyright  
*   @license    
*   @web        https://www.gdalab.com/
* 
*****************************************************************************************************************************
* Variables list
*****************************************************************************************************************************
* 
*   @var protected $table           => Table that belongs to this model class.
*   @var protected $fillable        => The attributes that are mass assignable.
*   @var protected $timestamps      => Indicates if this model have an automatic timestamp field.
* 
*****************************************************************************************************************************
* Method list
*****************************************************************************************************************************
* 
*   public function boot()   => Metodo para registrar cualquier authentication / authorization services.
*
*****************************************************************************************************************************/

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\Service;

/**
 * Class Sale.
 *
 * @OA\Schema(
 *     title="Sale model",
 *     description="Sale model",
 * )
 */
class Sale extends Model {
	protected $table = 'islim_sales';

	protected $fillable = [
		'services_id',
        'concentrators_id',
        'assig_pack_id',
        'inv_arti_details_id',
        'api_key',
        'users_email',
        'packs_id',
        'order_altan',
        'unique_transaction',
        'codeAltan',
        'type',
        'id_point',
        'description',
        'fee_paid',
        'amount',
        'amount_net',
        'com_amount',
        'msisdn',
        'conciliation',
        'lat',
        'lng',
        'position',
        'date_reg',
        'status',
        'sale_type'
    ];

    /**
     * @OA\Property(
     *     format="boolean",
     *     title="timestamps",
     *     default=false,
     *     description="timestamps",
     * )
     *
     * @var boolean
     */
    public $timestamps = false;


  //Retorna una venta dado un numero de transaccion y un id de concentrador
  public function getSale($transaction = false, $idConc = false, $msisdn = false)
  {
    if ($transaction && $idConc) {
      $sqlSale = "SELECT * FROM islim_sales WHERE unique_transaction = :transaction AND concentrators_id = :idConc AND status != 'T'";

      if ($msisdn) {
        $sqlSale .= " AND msisdn = :dn";
      }

      $excSqlSale = $this->bd->prepare($sqlSale);
      $excSqlSale->bindParam(':transaction', $transaction);
      $excSqlSale->bindParam(':idConc', $idConc);

      if ($msisdn) {
        $excSqlSale->bindParam(':dn', $msisdn);
      }

      $excSqlSale->execute();
      $data = $excSqlSale->fetch();

      if (!empty($data)) {
        return $data;
      }
    }
    return false;
  }

  public function getLastSale($msisdn = false)
  {
    if ($msisdn) {
      $sql = "SELECT id, type, msisdn, codeAltan, api_key
                    FROM islim_sales
                    WHERE msisdn = :msisdn AND (status = 'A' OR status ='E')
                    ORDER BY id DESC
                    LIMIT 1";

      $exec = $this->bd->prepare($sql);
      $exec->bindParam(':msisdn', $msisdn);
      $exec->execute();

      return $exec->fetch();
    }

    return null;
  }

    function getSaleWext($time = false, $type = false)
  {
    if ($time && $type) {
      $coord_serv = service_chcooArr; //service_chcoo;

      $sql = "SELECT islim_sales.id, 
                           islim_sales.msisdn,
                           islim_sales.codeAltan,
                           islim_sales.sale_type,
                           islim_sales.api_key,
                           islim_sales.date_reg,
                           islim_sales.services_id
                    FROM islim_sales
                    WHERE islim_sales.date_reg >= :date_beg
                          AND islim_sales.type = :type
                          AND islim_sales.services_id IN :coord 
                          AND (islim_sales.status = 'A' OR islim_sales.status = 'E')
                    AND (SELECT count(id)
                         FROM islim_extra_offer_active
                         WHERE islim_extra_offer_active.sale_id = islim_sales.id AND (islim_extra_offer_active.status = 'A' OR islim_extra_offer_active.status = 'E')
                             AND islim_extra_offer_active.type_trigger = :trigger
                        ) = 0";

      $exec = $this->bd->prepare($sql);
      $exec->bindParam(':type', $type);
      $exec->bindParam(':trigger', $type);
      $exec->bindParam(':coord', $coord_serv);
      $exec->bindParam(':date_beg', $time);
      $exec->execute();

      return $exec->fetchAll();
    }

    return [];
  }

  public function getPrimaryoffertFromUp($msisdn = false)
  {
    if ($msisdn) {
      $sql = "SELECT islim_sales.codeAltan
                    FROM islim_sales
                    WHERE islim_sales.msisdn = :msisdn
                    AND (islim_sales.status = 'A' OR islim_sales.status = 'E')
                    AND islim_sales.type = 'P'";

      $exec = $this->bd->prepare($sql);
      $exec->bindParam(':msisdn', $msisdn);
      $exec->execute();

      $data = $exec->fetch();

      if (!empty($data)) {
        return $data;
      }
    }

    return null;
  }

  public function getPrimaryoffertFromSale($msisdn = false)
  {
    if ($msisdn) {
      $sql = "SELECT islim_sales.codeAltan
                    FROM islim_sales
                    INNER JOIN islim_altan_codes
                    ON islim_sales.codeAltan = islim_altan_codes.codeAltan
                    WHERE islim_sales.msisdn = :msisdn
                    AND islim_altan_codes.status = 'A'
                    AND islim_altan_codes.supplementary = 'N'
                    ORDER BY islim_sales.id DESC
                    LIMIT 1";

      $exec = $this->bd->prepare($sql);
      $exec->bindParam(':msisdn', $msisdn);
      $exec->execute();

      $data = $exec->fetch();

      if (!empty($data)) {
        return $data;
      }
    }

    return null;
  }

  public function getSupoffertFromSale($id = false)
  {
    if ($id) {
      /*$sql = "SELECT islim_altan_codes.codeAltan
      FROM islim_sales
      INNER JOIN islim_altan_codes
      ON islim_sales.codeAltan = islim_altan_codes.codeAltan
      WHERE islim_sales.id = :id
      AND islim_altan_codes.status = 'A'
      AND islim_altan_codes.supplementary = 'Y'";*/

      $sql = "SELECT codeAltan
                    FROM islim_sales
                    WHERE islim_sales.id = :id";

      $exec = $this->bd->prepare($sql);
      $exec->bindParam(':id', $id);
      $exec->execute();

      $data = $exec->fetch();

      if (!empty($data)) {
        return $data;
      }
    }

    return null;
  }

  //Retorna la venta del alta dado un dn
  public function getUpbyDN($dn = false)
  {
    if ($dn) {
      $sqlSale    = "SELECT * FROM islim_sales WHERE msisdn = :msisdn AND type = 'P' AND status != 'T'";
      $excSqlSale = $this->bd->prepare($sqlSale);
      $excSqlSale->bindParam(':msisdn', $dn);
      $excSqlSale->execute();

      $data = $excSqlSale->fetch();

      if (!empty($data)) {
        return $data;
      }
    }
    return false;
  }

      /******************************************************************/
  /* quitar desde aqui para abajo 
  */  

    /*Retorna monto total que ha pagado un cliente en recargas*/
    public static function getTotalPayment($dn = false){
        if($dn){
            return Sale::where([
                            ['msisdn', $dn],
                            ['status', '!=', 'T'],
                            ['type', 'R']
                          ])
                          ->sum('fee_paid');
        }

        return 0;
    }

    /*Retorna el alta de un dn dado*/
    public static function getRegisterDn($dn = false){
        if($dn){
            return Sale::where([
                            ['type', 'P'],
                            ['status', '!=', 'T'],
                            ['msisdn', $dn]
                        ])
                        ->first();
        }

        return null;
    }

    public static function getLastService($msisdn = false){
        if($msisdn){
            $sale = self::select('services_id')
                          ->where('msisdn', $msisdn)
                          ->orderBy('id', 'DESC')
                          ->first();

            if(!empty($sale)){
                return Service::getServiceById($sale->services_id);
            }
        }

        return null;
    }

    public static function isOldOffert($msisdn = false){
        return false;
        if($msisdn){
            $raw = DB::raw('(SELECT codeAltan 
                        FROM islim_sales AS s 
                        WHERE s.msisdn = islim_sales.msisdn 
                        AND s.codeAltan LIKE "11%" 
                        AND s.status IN ("A","E") 
                        ORDER BY s.id DESC LIMIT 1) as lastCode');

            $data = self::select(
                            'islim_sales.msisdn',
                            'islim_sales.codeAltan as firstCode',
                            $raw
                        )
                        ->where([
                            ['islim_sales.type', 'P'],
                            ['islim_sales.msisdn', $msisdn]
                        ])
                        ->whereIn('islim_sales.status', ['A', 'E'])
                        ->first();

            if(!empty($data)){
                $oldOfferts = [
                  '1100500044',
                  '1101000042',
                  //'1100500043',
                  '1100501000',
                  '1100500040',
                  '1100501001',
                  '1100500042',
                  //'1100501003',
                  '1100501002',
                  '1100500041'
                ];

                if(in_array($data->lastCode, $oldOfferts)){
                    return true;
                }
            }
        }

        return false;
    }

    public static function getSaleByTransaction($transaction = false){
        if($transaction){
            return self::select('msisdn')
                        ->where('unique_transaction', $transaction)
                        ->first();
        }

        return null;
    }
}