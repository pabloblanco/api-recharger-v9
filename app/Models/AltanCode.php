<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sale;
use App\Helpers\Common;

class AltanCode extends Model
{
    protected $table = 'islim_altan_codes';

	protected $fillable = [
        'services_id', 'codeAltan', 'supplementary', 'status'
    ];

    public $timestamps = false;

    public function getAltanCodeFT($msisdn = false, $isb28 = 'Y', $service = false)
    {

        if ($msisdn && $service) {

            $ls = Sale::getLastService($msisdn);

            if (!empty($ls)) {

                if ($ls['is_band_twenty_eight'] != $isb28) {

                    $data = AltanCode::select('codeAltan', 'supplementary')
                        ->where([
                            'services_id' => $service,
                            'supplementary' => 'N',
                            'status' => 'A'
                        ])->get();

                    if (!empty($data)) {

                        return [
                            'codeAltan'    => $data['codeAltan'],
                            'suplementary' => $data['supplementary']
                        ];

                    }

                } else {

                    return [
                        'codeAltan'    => $service['codeAltan'],
                        'suplementary' => 'Y'
                    ];

                }
            }
        }

        return null;

    }

    //Retorna el codigo de servicio de altan
    public function getAltanCode($service = false, $serviceability = false, $typeHbb = 'IS', $forceNS = false)
    {

        if ($service && $serviceability) {

            $sup    = 'N';

            if (Common::compareWide($serviceability, $service['broadband'], true) && $typeHbb == $service['type_hbb'] && !$forceNS) {
                $sup = 'Y';
            }

            $data = AltanCode::select('codeAltan', 'supplementary')
                ->where([
                    'services_id' => $service,
                    'supplementary' => $sup,
                    'status' => 'A'
                ])->get();

            if (!empty($data)) {

                return $data;

            }

        }

        return false;

    }

    public function getCodeAltanBycode($code = false)
    {
        if ($code) {

            $data = AltanCode::where([
                    'codeAltan' => $code,
                    'status' => 'A'
                ])->first();

            if (!empty($data)) {

                return $data;

            }

        }

        return false;

    }

}
