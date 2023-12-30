<?php
/*
Autor: Ing. Luis J. https://www.linkedin.com/in/ljpd2009
Mayo 2022
 */
namespace App\Http\Middleware;

use App\Models\Ninety_nine_ips;
use App\Models\Ninety_nine_logs;
use Closure;

class IPauthorized
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  public function handle($request, Closure $next)
  {
    if (Recharger_ips::isIpValid($request->ip())) {
      return $next($request);
    } else {
      $msg = 'Intento de conexion desde: ' . $request->ip();
      Recharger_logs::saveLogBD(false, false, false, false, 'INFO', $msg);
      return response('Origin Not authorized', 401);
    }
  }
}