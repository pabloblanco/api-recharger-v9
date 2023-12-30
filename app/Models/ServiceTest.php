<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceTest extends Model {
	protected $table = 'islim_services_test';
    
    public $timestamps = false;

  //Retorna servicios de pruebas
  public function getDataServiceTest($service = false, $type = "ALL")
  {
    if ($service) {
      $sqlService = "SELECT * FROM islim_services_test WHERE id = :service AND status = 'A' AND type = 'P'";
      if ($type != "ALL") {
        $sqlService = $sqlService . " AND method_pay = :type";
      }

      $excSqlService = $this->bd->prepare($sqlService);
      $excSqlService->bindParam(':service', $service);
      if ($type != "ALL") {
        $excSqlService->bindParam(':type', $type);
      }

      $excSqlService->execute();
      $data = $excSqlService->fetch();
      if (!empty($data)) {
        return $data;
      }
    }
    return false;
  }


  //Retorna los servicios activos dado un  metodo de pago (Test)
  public function getServiceByTypeTest($method = false, $wide = "ALL", $fields = '*')
  {
    if ($method) {
      $sqlServices    = "SELECT [fields] FROM islim_services_test WHERE status = 'A' AND method_pay = :method AND type = 'P'";
      $sqlServices    = str_replace('[fields]', $fields, $sqlServices);
      $excSqlServices = $this->bd->prepare($sqlServices);
      $excSqlServices->bindParam(':method', $method);
      $excSqlServices->execute();
      $data = $excSqlServices->fetchAll();

      if ($wide != 'ALL' && !empty($data)) {
        $common = new common();
        $dataW  = [];
        foreach ($data as $service) {
          if ($common->compareWide($wide, $service['broadband'])) {
            $dataW[] = $service;
          }
        }
        if (count($dataW) > 0) {
          return $dataW;
        }
      } elseif (!empty($data)) {
        return $data;
      }
    }
    return false;
  }

 }  