<?php

namespace App\Models;

use App\Client;
use App\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClientNetwey extends Model
{
  protected $table = 'islim_client_netweys';

  protected $fillable = [
    'msisdn',
    'clients_dni',
    'service_id',
    'address',
    'type_buy',
    'periodicity',
    'num_dues',
    'paid_fees',
    'unique_transaction',
    'serviceability',
    'lat',
    'lng',
    'point',
    'date_buy',
    'price_remaining',
    'date_reg',
    'date_expire',
    'date_cd30',
    'date_cd90',
    'type_cd90',
    'status',
    'obs',
    'credit',
    'n_update_coord',
    'n_sim_swap',
    'tag',
    'id_list_dns',
    'dn_type'];

  protected $primaryKey = 'msisdn';

  public $incrementing = false;

  public $timestamps = false;

  //retorna la data de un numero registrado si el numero no esta registrado devuelve un falso
  public function getDataNumber($number = false)
  {
    if ($number) {
      $sqlNumber    = "SELECT * FROM islim_client_netweys WHERE msisdn = :msisdn AND (status = 'A' OR status = 'S')";
      $excSqlNumber = $this->bd->prepare($sqlNumber);
      $excSqlNumber->bindParam(':msisdn', $number);
      $excSqlNumber->execute();
      $data = $excSqlNumber->fetch();

      if (!empty($data)) {
        return $data;
      }
    }
    return false;
  }

  //Retorna la zona a la que pertenece un DN
  public function getFiberZoneByDn($dn = false)
  {
    $sqlSale = "SELECT id_fiber_zone
          FROM islim_client_netweys
          WHERE msisdn = :dn";

    $excSqlSale = $this->bd->prepare($sqlSale);
    $excSqlSale->bindParam(':dn', $dn);
    $excSqlSale->execute();

    $data = $excSqlSale->fetch();

    if (!empty($data)) {
      return $data['id_fiber_zone'];
    }

    return false;
  }
  
  /******************************************************************/
  /* quitar desde aqui para abajo 
  */

  public static function existDN($msisdnTransit = false)
  {
    if ($msisdnTransit) {
      return self::getConnect('R')
        ->select('msisdn', 'dn_type', 'status', 'date_reg')
        ->where('msisdn', $msisdnTransit)
        ->first();
    }

    return null;
  }

  public static function getDNIClient($msisdn)
  {
    return self::getConnect('R')
      ->select('clients_dni')
      ->where('msisdn', $msisdn)
      ->first();
  }

  public static function getClient($key)
  {
    $client         = ClientNetwey::select('msisdn', 'clients_dni', 'service_id', 'address', 'type_buy', 'periodicity', 'num_dues', 'paid_fees', 'unique_transaction', 'serviceability', 'lat', 'lng', 'date_buy', 'price_remaining', 'status', 'obs', 'credit')->where(['msisdn' => $key, 'status' => 'A'])->first();
    $client->client = Client::find($client->clients_dni);
    return $client;
  }

  public static function getReport($services = [], $status = null, $date_ini = null, $date_end = null, $msisdns = null, $type_line = null)
  {
    $report = self::getConnect('R')
      ->select(
        'islim_client_netweys.date_buy AS client_date',
        'islim_client_netweys.date_buy',
        'islim_client_netweys.dn_type',
        'islim_client_netweys.msisdn',
        'islim_clients.date_reg AS prospect_date',
        'islim_clients.name',
        'islim_clients.last_name',
        'islim_clients.email',
        'islim_clients.phone_home',
        'islim_clients.address',
        'islim_services.title AS service',
        DB::raw('CONCAT(Islim_broadbands.num_broad, " Mbps") AS speed'),
        'islim_sales.typePayment'
      )
      ->join('islim_clients', 'islim_clients.dni', '=', 'islim_client_netweys.clients_dni')
      ->join('islim_services', 'islim_services.id', '=', 'islim_client_netweys.service_id')
      ->leftJoin('Islim_broadbands', 'Islim_broadbands.broadband', '=', 'islim_services.broadband')
      ->join('islim_sales', 'islim_sales.msisdn', 'islim_client_netweys.msisdn')
      ->where([
        ['islim_clients.name', '!=', 'TEMPORAL'],
        ['islim_sales.type', 'P']
      ])
      ->orderBy('islim_client_netweys.date_buy');

    if (!empty($services) && count($services)) {
      $report = $report->whereIn('islim_services.id', $services);
    }

    if (!empty($type_line)) {
      $report = $report->where('islim_client_netweys.dn_type', $type_line);
    }

    if (!empty($status)) {
      $report = $report->whereIn('islim_client_netweys.status', $status);
    }

    if (!empty($date_ini) && !empty($date_end)) {
      $report = $report->whereBetween('islim_client_netweys.date_buy', [$date_ini . ' 00:00:00', $date_end . ' 23:59:59']);
    } else {
      if (!empty($date_ini)) {
        $report = $report->where('islim_client_netweys.date_buy', '>=', $date_ini . ' 00:00:00');
      }

      if (!empty($date_end)) {
        $report = $report->where('islim_client_netweys.date_buy', '<=', $date_end . ' 23:59:59');
      }
    }

    if (!empty($msisdns)) {
      $report = $report->whereIn('islim_client_netweys.msisdn', $msisdns);
    }

    return $report->get();
  }

  public static function getFinancingReport($filters = null)
  {
    $diff = DB::raw('(islim_financing.total_amount - islim_client_netweys.price_remaining) as pay');

    $query = ClientNetwey::getConnect('R')->select(
      'islim_client_netweys.msisdn',
      'islim_client_netweys.num_dues',
      'islim_client_netweys.price_remaining',
      'islim_sales.date_reg',
      'islim_financing.amount_financing',
      'islim_financing.total_amount',
      $diff
    )
      ->join('islim_sales', function ($join) {
        $join->on('islim_sales.unique_transaction', '=', 'islim_client_netweys.unique_transaction')
          ->where('islim_sales.type', 'P');
      })
      ->join(
        'islim_users',
        'islim_users.email',
        '=',
        'islim_sales.users_email'
      )
      ->join('islim_pack_prices', function ($join) {
        $join->on('islim_pack_prices.pack_id', '=', 'islim_sales.packs_id')
          ->where('islim_pack_prices.service_id', DB::raw('islim_sales.services_id'));
      })
      ->join(
        'islim_financing',
        'islim_financing.id',
        'islim_pack_prices.id_financing'
      )
      ->where([
        ['islim_client_netweys.type_buy', 'CR'],
      ]);

    //Filtros del reporte
    if (!empty($filters) && is_array($filters)) {
      if (!empty($filters['org'])) {
        $query = $query->where('islim_users.id_org', $filters['org']);
      } else {
        $orgs  = Organization::getOrgsPermitByOrgs(session('user.id_org'));
        $query = $query->where('islim_users.id_org', $orgs->pluck('id'));
      }

      if (!empty($filters['db']) && !empty($filters['de'])) {
        $query = $query->whereBetween('islim_sales.date_reg', [$filters['db'] . ' 00:00:00', $filters['de'] . ' 23:59:59']);
      }

      if (empty($filters['db']) && !empty($filters['de'])) {
        $query = $query->where('islim_sales.date_reg', '<=', $filters['de'] . ' 23:59:59');
      }

      if (!empty($filters['db']) && empty($filters['de'])) {
        $query = $query->where('islim_sales.date_reg', '>=', $filters['db'] . ' 00:00:00');
      }

      if (!empty($filters['fi'])) {
        $query = $query->where('islim_financing.id', $filters['fi']);
      }
    }

    return $query;
  }
}
