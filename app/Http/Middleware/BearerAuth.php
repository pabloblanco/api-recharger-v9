<?php
/*
Autor: Ing. Luis J. https://www.linkedin.com/in/ljpd2009
Mayo 2022
 */
namespace App\Http\Middleware;

use App\Models\Ninety_nine_logs;
use App\Models\Ninety_nine_token;
use Closure;

class BearerAuth
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
    if (Ninety_nine_token::isTokenValid($request)) {
      return $next($request);
    } else {
      $msg = 'Intento de conexion desde IP: ' . $request->ip() . ' Token: ' . $request->bearerToken() . ' Ambiente: ' . env('APP_ENV');
      Ninety_nine_logs::saveLogBD(false, false, false, false, 'INFO', $msg);
      return response('Combination Token-Ip is invalid ', 401);
    }
  }
}