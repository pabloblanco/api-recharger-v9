<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceChanel extends Model {
	protected $table = 'islim_service_channel';
    
    public $timestamps = false;

  //Retorna los servicios asociados a una lista
  public function getServiceByList($idList = false)
  {
    if ($idList) {
      $sql = "SELECT 
                islim_services.title,
                islim_services.broadband,
                Islim_broadbands.num_broad
              FROM islim_service_channel 
              INNER JOIN islim_services on islim_service_channel.id_service = islim_services.id
              INNER JOIN Islim_broadbands on islim_services.broadband = Islim_broadbands.broadband
              WHERE islim_service_channel.id_list_dns = :list AND 
                    islim_service_channel.status = 'A' AND
                    islim_services.status = 'A'";

      $excSql = $this->bd->prepare($sql);
      $excSql->bindParam(':list', $idList);
      $excSql->execute();
      return $excSql->fetchAll();
    }

    return [];
  }

  //retorna un servicio dado un id de servicio
  //OJO cuando se active las recargas a credito se debe verificar este metodo
  public function getDataService($service = false, $type = "ALL", $id_conc, $list = null, $dn = false, $DNtype = 'H', $isband28 = 'Y')
  {
    if ($service) {
      $concentrador = $this->getConcentrator($id_conc, 'id_channel');

      if ($concentrador) {
        if (empty($list)) {
          $sqlService = "SELECT islim_services.*,
                                          islim_periodicities.periodicity
                                    FROM islim_service_channel
                                    INNER JOIN islim_services
                                    ON islim_services.id = islim_service_channel.id_service
                                    AND islim_services.status = 'A'
                                    AND islim_services.type = 'P'
                                    INNER JOIN islim_periodicities
                                    ON islim_periodicities.id = islim_services.periodicity_id
                                    AND islim_periodicities.status = 'A'
                                    WHERE islim_service_channel.status = 'A'
                                    AND islim_service_channel.id_service = :service
                                    AND islim_services.service_type = :dn_type
                                    [ST]
                                    AND (islim_service_channel.id_channel = :chanel OR islim_service_channel.id_concentrator = :conc)";
        } else {
          $sqlService = "SELECT islim_services.*,
                                          islim_periodicities.periodicity
                                    FROM islim_service_channel
                                    INNER JOIN islim_services
                                    ON islim_services.id = islim_service_channel.id_service
                                    AND islim_services.status = 'A'
                                    AND islim_services.type = 'P'
                                    INNER JOIN islim_periodicities
                                    ON islim_periodicities.id = islim_services.periodicity_id
                                    AND islim_periodicities.status = 'A'
                                    WHERE islim_service_channel.status = 'A'
                                    AND islim_service_channel.id_service = :service
                                    AND islim_services.service_type = :dn_type
                                    [ST]
                                    AND islim_service_channel.id_list_dns = :list";
        }

        if ($DNtype == 'T') {
          $sqlService = str_replace('[ST]', 'AND islim_services.is_band_twenty_eight = "' . $isband28 . '"', $sqlService);
        } else {
          $sqlService = str_replace('[ST]', '', $sqlService);
        }

        $excSqlService = $this->bd->prepare($sqlService);
        $excSqlService->bindParam(':service', $service);
        $excSqlService->bindParam(':dn_type', $DNtype);

        if (empty($list)) {
          $excSqlService->bindParam(':chanel', $concentrador['id_channel']);
          $excSqlService->bindParam(':conc', $id_conc);
        } else {
          $excSqlService->bindParam(':list', $list);
        }

        $excSqlService->execute();
        $data = $excSqlService->fetch();

        if (!empty($data)) {
          if ($type == 'CR') {
            $alta = $this->getUpbyDN($dn);

            if ($alta) {
              $financing = $this->getFinancing($alta['packs_id'], $alta['services_id']);
            }

            if (empty($financing)) {
              return false;
            }

            if (!empty($data['periodicity'])) {
              $data['price_pay'] += !empty($financing[$data['periodicity']]) ? $financing[$data['periodicity']] : 0;
            }
          }

          return $data;
        }
      }
    }
    return false;
  }

    //retorna un servicio dado un id de servicio
  //OJO cuando se active las recargas a credito se debe verificar este metodo
  public function getDataServiceByZone($service = false, $type = "ALL", $id_conc, $list = null, $dn = false, $DNtype = 'H', $isband28 = 'Y')
  {
    if ($service) {
      $concentrador = $this->getConcentrator($id_conc, 'id_channel');
      $fiber_zone_id = $this->getFiberZoneByDn($dn);

      if ($concentrador) {
        if (empty($list)) {
          $sqlService = "SELECT islim_services.*,
                      islim_periodicities.periodicity,
                      islim_fiber_service_zone.service_pk
                  FROM islim_service_channel
                  INNER JOIN islim_services
                  ON islim_services.id = islim_service_channel.id_service
                  AND islim_services.status = 'A'
                  AND islim_services.type = 'P'
                  INNER JOIN islim_fiber_service_zone
                  ON islim_services.id = islim_fiber_service_zone.service_id
                  INNER JOIN islim_periodicities
                  ON islim_periodicities.id = islim_services.periodicity_id
                  AND islim_periodicities.status = 'A'
                  WHERE islim_service_channel.status = 'A'
                  AND islim_service_channel.id_service = :service
                  AND islim_fiber_service_zone.fiber_zone_id = :fiber_zone_id
                  AND islim_services.service_type = :dn_type
                  [ST]
                  AND (islim_service_channel.id_channel = :chanel OR islim_service_channel.id_concentrator = :conc)";
        } else {
          $sqlService = "SELECT islim_services.*,
                      islim_periodicities.periodicity,
                      islim_fiber_service_zone.service_pk
                  FROM islim_service_channel
                  INNER JOIN islim_services
                  ON islim_services.id = islim_service_channel.id_service
                  AND islim_services.status = 'A'
                  AND islim_services.type = 'P'
                  INNER JOIN islim_fiber_service_zone
                  ON islim_services.id = islim_fiber_service_zone.service_id
                  INNER JOIN islim_periodicities
                  ON islim_periodicities.id = islim_services.periodicity_id
                  AND islim_periodicities.status = 'A'
                  WHERE islim_service_channel.status = 'A'
                  AND islim_service_channel.id_service = :service
                  AND islim_fiber_service_zone.fiber_zone_id = :fiber_zone_id
                  AND islim_services.service_type = :dn_type
                  [ST]
                  AND islim_service_channel.id_list_dns = :list";
        }

        if ($DNtype == 'T') {
          $sqlService = str_replace('[ST]', 'AND islim_services.is_band_twenty_eight = "' . $isband28 . '"', $sqlService);
        } else {
          $sqlService = str_replace('[ST]', '', $sqlService);
        }

        $excSqlService = $this->bd->prepare($sqlService);
        $excSqlService->bindParam(':service', $service);
        $excSqlService->bindParam(':dn_type', $DNtype);

        if (empty($list)) {
          $excSqlService->bindParam(':chanel', $concentrador['id_channel']);
          $excSqlService->bindParam(':conc', $id_conc);
        } else {
          $excSqlService->bindParam(':list', $list);
        }

        $excSqlService->bindParam(':fiber_zone_id', $fiber_zone_id);
        
        $excSqlService->execute();
        $data = $excSqlService->fetch();

        if (!empty($data)) {
          if ($type == 'CR') {
            $alta = $this->getUpbyDN($dn);

            if ($alta) {
              $financing = $this->getFinancing($alta['packs_id'], $alta['services_id']);
            }

            if (empty($financing)) {
              return false;
            }

            if (!empty($data['periodicity'])) {
              $data['price_pay'] += !empty($financing[$data['periodicity']]) ? $financing[$data['periodicity']] : 0;
            }
          }

          return $data;
        }
      }
    }
    return false;
  }

  public function getDataService2($service = false, $type = "ALL", $id_conc, $list = null, $dn = false, $DNtype = 'H', $isband28 = 'Y', $statusDN = 'active', $is_mobility = false)
  {
    if ($service) {
      $concentrador = $this->getConcentrator($id_conc, 'id_channel');

      if ($concentrador) {
        if (empty($list)) {
          $sqlService = "SELECT islim_services.*,
                                          islim_periodicities.periodicity
                                    FROM islim_service_channel
                                    INNER JOIN islim_services
                                    ON islim_services.id = islim_service_channel.id_service
                                    AND islim_services.status = 'A'
                                    AND islim_services.type = 'P'
                                    INNER JOIN islim_periodicities
                                    ON islim_periodicities.id = islim_services.periodicity_id
                                    AND islim_periodicities.status = 'A'
                                    WHERE islim_service_channel.status = 'A'
                                    AND islim_service_channel.id_service = :service
                                    AND islim_services.service_type = :dn_type
                                    [ST]
                  [OW]
                                    AND (islim_service_channel.id_channel = :chanel OR islim_service_channel.id_concentrator = :conc)";
        } else {
          $sqlService = "SELECT islim_services.*,
                                          islim_periodicities.periodicity
                                    FROM islim_service_channel
                                    INNER JOIN islim_services
                                    ON islim_services.id = islim_service_channel.id_service
                                    AND islim_services.status = 'A'
                                    AND islim_services.type = 'P'
                                    INNER JOIN islim_periodicities
                                    ON islim_periodicities.id = islim_services.periodicity_id
                                    AND islim_periodicities.status = 'A'
                                    WHERE islim_service_channel.status = 'A'
                                    AND islim_service_channel.id_service = :service
                                    AND islim_services.service_type = :dn_type
                                    [ST]
                  [OW]
                                    AND islim_service_channel.id_list_dns = :list";
        }

        if ($statusDN == 'suspend' && $is_mobility) {
          $sqlService = str_replace('[OW]', 'AND islim_services.id IN ' . service_chcooArr, $sqlService);
        } else {
          $sqlService = str_replace('[OW]', 'AND islim_services.id NOT IN ' . service_chcooArr, $sqlService);
        }

        if ($DNtype == 'T') {
          $sqlService = str_replace('[ST]', 'AND islim_services.is_band_twenty_eight = "' . $isband28 . '"', $sqlService);
        } else {
          $sqlService = str_replace('[ST]', '', $sqlService);
        }

        $excSqlService = $this->bd->prepare($sqlService);
        $excSqlService->bindParam(':service', $service);
        $excSqlService->bindParam(':dn_type', $DNtype);

        if (empty($list)) {
          $excSqlService->bindParam(':chanel', $concentrador['id_channel']);
          $excSqlService->bindParam(':conc', $id_conc);
        } else {
          $excSqlService->bindParam(':list', $list);
        }

        $excSqlService->execute();
        $data = $excSqlService->fetch();

        if (!empty($data)) {
          if ($type == 'CR') {
            $alta = $this->getUpbyDN($dn);

            if ($alta) {
              $financing = $this->getFinancing($alta['packs_id'], $alta['services_id']);
            }

            if (empty($financing)) {
              return false;
            }

            if (!empty($data['periodicity'])) {
              $data['price_pay'] += !empty($financing[$data['periodicity']]) ? $financing[$data['periodicity']] : 0;
            }
          }

          return $data;
        }
      }
    }
    return false;
  }

  //Retorna los servicios activos dado un  metodo de pago
  public function getServiceByType($method = false, $wideUSer = false, $fields = '*', $id_conc, $list = null, $dn = false, $statusDN = 'active', $DNtype = 'H', $father = false, $is_mobility = false, $is_inactive = false, $isband28 = 'Y')
  {
    if ($method && $dn) {
      $concentrador = $this->getConcentrator($id_conc, 'id_channel');

      if ($concentrador) {
        if (empty($list)) {
          $sqlServices = "SELECT [fields]
                                    FROM islim_service_channel
                                    INNER JOIN islim_services
                                    ON islim_services.id = islim_service_channel.id_service
                                    AND islim_services.status = 'A'
                                    AND islim_services.type = 'P'
                                    INNER JOIN islim_periodicities
                                    ON islim_periodicities.id = islim_services.periodicity_id
                                    AND islim_periodicities.status = 'A'
                                    WHERE islim_service_channel.status = 'A'
                                    AND (islim_service_channel.id_channel = :chanel OR islim_service_channel.id_concentrator = :conc)
                                    AND islim_services.service_type = :dn_type
                                    [ST]
                                    [OW]
                                    GROUP BY islim_services.id
                                    ORDER BY islim_services.price_pay DESC";
        } else {
          $sqlServices = "SELECT [fields]
                                    FROM islim_service_channel
                                    INNER JOIN islim_services
                                    ON islim_services.id = islim_service_channel.id_service
                                    AND islim_services.status = 'A'
                                    AND islim_services.type = 'P'
                                    INNER JOIN islim_periodicities
                                    ON islim_periodicities.id = islim_services.periodicity_id
                                    AND islim_periodicities.status = 'A'
                                    WHERE islim_service_channel.status = 'A'
                                    AND islim_service_channel.id_list_dns = :list
                                    AND islim_services.service_type = :dn_type
                                    [ST]
                                    [OW]
                                    GROUP BY islim_services.id
                                    ORDER BY islim_services.price_pay DESC";
        }

        if ($statusDN == 'suspend' && $is_mobility) {
          $sqlServices = str_replace('[OW]', 'AND islim_services.id IN ' . service_chcooArr, $sqlServices);
        } else {
          $sqlServices = str_replace('[OW]', 'AND islim_services.id NOT IN ' . service_chcooArr, $sqlServices);
        }

        if ($DNtype == 'T') {
          $sqlServices = str_replace('[ST]', 'AND islim_services.is_band_twenty_eight = "' . $isband28 . '"', $sqlServices);
        } else {
          $sqlServices = str_replace('[ST]', '', $sqlServices);
        }

        $sqlServices = str_replace('[fields]', $fields, $sqlServices);

        $excSqlServices = $this->bd->prepare($sqlServices);
        $excSqlServices->bindParam(':dn_type', $DNtype);

        if (empty($list)) {
          $excSqlServices->bindParam(':chanel', $concentrador['id_channel']);
          $excSqlServices->bindParam(':conc', $id_conc);
        } else {
          $excSqlServices->bindParam(':list', $list);
        }

        $excSqlServices->execute();

        $data = $excSqlServices->fetchAll();

        if ($method == 'CR') {
          $alta = $this->getUpbyDN($dn);

          if ($alta) {
            $financing = $this->getFinancing($alta['packs_id'], $alta['services_id']);
          }
        }

        //Validando si el cliente no ha pagado el credito se consiga el financiamiento
        if ($method == 'CR' && empty($financing)) {
          return false;
        }

        if (!empty($data)) {
          $dataW = [];

          foreach ($data as $service) {
            if ($method == 'CR') {
              if (!empty($service['periodicity'])) {
                $service['price'] += !empty($financing[$service['periodicity']]) ? $financing[$service['periodicity']] : 0;
              }
            }

            $dataW[] = $service;
          }
          return $dataW;
        }
      }
    }
    return false;
  }


  //Retorna los servicios activos dado un  metodo de pago y una zona
  public function getServiceByTypeAndZone($method = false, $wideUSer = false, $fields = '*', $id_conc, $list = null, $dn = false, $statusDN = 'active', $DNtype = 'H', $father = false, $is_mobility = false, $is_inactive = false, $isband28 = 'Y')
  {
    if ($method && $dn) {
      $concentrador = $this->getConcentrator($id_conc, 'id_channel');
      $fiber_zone_id = $this->getFiberZoneByDn($dn);

      if ($concentrador) {
        if (empty($list)) {
          $sqlServices = "SELECT [fields]
                  FROM islim_service_channel
                  INNER JOIN islim_services
                  ON islim_services.id = islim_service_channel.id_service
                  AND islim_services.status = 'A'
                  AND islim_services.type = 'P'
                  INNER JOIN islim_fiber_service_zone
                  ON islim_services.id = islim_fiber_service_zone.service_id
                  INNER JOIN islim_periodicities
                  ON islim_periodicities.id = islim_services.periodicity_id
                  AND islim_periodicities.status = 'A'
                  WHERE islim_service_channel.status = 'A'
                  AND (islim_service_channel.id_channel = :chanel OR islim_service_channel.id_concentrator = :conc)
                  AND islim_fiber_service_zone.fiber_zone_id = :fiber_zone_id
                  AND islim_services.service_type = :dn_type
                  [ST]
                  [OW]
                  GROUP BY islim_services.id
                  ORDER BY islim_services.price_pay DESC";
        } else {
          $sqlServices = "SELECT [fields]
                  FROM islim_service_channel
                  INNER JOIN islim_services
                  ON islim_services.id = islim_service_channel.id_service
                  AND islim_services.status = 'A'
                  AND islim_services.type = 'P'
                  INNER JOIN islim_fiber_service_zone
                  ON islim_services.id = islim_fiber_service_zone.service_id
                  INNER JOIN islim_periodicities
                  ON islim_periodicities.id = islim_services.periodicity_id
                  AND islim_periodicities.status = 'A'
                  WHERE islim_service_channel.status = 'A'
                  AND islim_service_channel.id_list_dns = :list
                  AND islim_fiber_service_zone.fiber_zone_id = :fiber_zone_id
                  AND islim_services.service_type = :dn_type
                  [ST]
                  [OW]
                  GROUP BY islim_services.id
                  ORDER BY islim_services.price_pay DESC";
        }

        if ($statusDN == 'suspend' && $is_mobility) {
          $sqlServices = str_replace('[OW]', 'AND islim_services.id IN ' . service_chcooArr, $sqlServices);
        } else {
          $sqlServices = str_replace('[OW]', 'AND islim_services.id NOT IN ' . service_chcooArr, $sqlServices);
        }

        if ($DNtype == 'T') {
          $sqlServices = str_replace('[ST]', 'AND islim_services.is_band_twenty_eight = "' . $isband28 . '"', $sqlServices);
        } else {
          $sqlServices = str_replace('[ST]', '', $sqlServices);
        }

        $sqlServices = str_replace('[fields]', $fields, $sqlServices);

        $excSqlServices = $this->bd->prepare($sqlServices);
        $excSqlServices->bindParam(':dn_type', $DNtype);

        if (empty($list)) {
          $excSqlServices->bindParam(':chanel', $concentrador['id_channel']);
          $excSqlServices->bindParam(':conc', $id_conc);
        } else {
          $excSqlServices->bindParam(':list', $list);
        }

        $excSqlServices->bindParam(':fiber_zone_id', $fiber_zone_id);

        $excSqlServices->execute();

        $data = $excSqlServices->fetchAll();

        if ($method == 'CR') {
          $alta = $this->getUpbyDN($dn);

          if ($alta) {
            $financing = $this->getFinancing($alta['packs_id'], $alta['services_id']);
          }
        }

        //Validando si el cliente no ha pagado el credito se consiga el financiamiento
        if ($method == 'CR' && empty($financing)) {
          return false;
        }

        if (!empty($data)) {
          $dataW = [];

          foreach ($data as $service) {
            if ($method == 'CR') {
              if (!empty($service['periodicity'])) {
                $service['price'] += !empty($financing[$service['periodicity']]) ? $financing[$service['periodicity']] : 0;
              }
            }

            $dataW[] = $service;
          }
          return $dataW;
        }
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
            $obj = new ServiceChanel;
            $obj->setConnection($typeCon == 'W' ? 'netwey-w' : 'netwey-r');

            return $obj;
        }
        return null;
    }


    public static function getConcService($service = false){
    	if($service){
    		return self::getConnect('R')
    					->select(
    						'islim_concentrators.id',
    						'islim_concentrators.name'
    					)
    					->join(
                            'islim_concentrators',
                            'islim_concentrators.id',
                            'islim_service_channel.id_concentrator'
                        )
                        ->where([
                            ['islim_service_channel.status', 'A'],
                            ['islim_service_channel.id_service', $service],
                            ['islim_concentrators.status', 'A']
                        ])
                        ->get();
    	}

    	return [];
    }

    public static function getChService($service = false){
    	if($service){
    		return self::getConnect('R')
    					->select(
    						'islim_channels.id', 
    						'islim_channels.name'
    					)
    					->join(
                            'islim_channels',
                            'islim_channels.id',
                            'islim_service_channel.id_channel'
                        )
                        ->where([
                            ['islim_service_channel.status', 'A'],
                            ['islim_service_channel.id_service', $service],
                            ['islim_channels.status', 'A']
                        ])
                        ->get();
    	}

    	return [];
    }

    public static function getListService($service = false){
    	if($service){
    		return self::getConnect('R')
    					->select(
    						'islim_list_dns.id',
    						'islim_list_dns.name'
    					)
    					->join(
                            'islim_list_dns',
                            'islim_list_dns.id',
                            'islim_service_channel.id_list_dns'
                        )
                        ->where([
                            ['islim_service_channel.status', 'A'],
                            ['islim_service_channel.id_service', $service],
                            ['islim_list_dns.status', 'A']
                        ])
                        ->get();
    	}

    	return [];
    }
}