<?php
/*
Autor: Ing. Luis J. https://www.linkedin.com/in/ljpd2009
Mayo 2022
 */
namespace App\Helpers;

use App\Helpers\Curl;

//
class Slack
{
  public function __construct()
  {
    date_default_timezone_set('America/Mexico_City');
  }

  /**
   * [sendSlackNotification Envio de notificaciones al Slack]
   * @param  string  $message [description]
   * @param  string  $type    [Indicar si es Error, Alert, Warning]
   * @param  array   $data    [description]
   * @param  boolean $request [description]
   * @return [type]           [description]
   */
  public function sendSlackNotification($message = '', $type = 'ALERT', $data = [], $request = false, $data_return = false, $time = false)
  {

    $send = [
      'text'        => 'Mensaje de notificación',
      'attachments' => [[
        'footer'  => 'Fecha de la notificación',
        'ts'      => time(),
        'color'   => $type == 'ALERT' ? 'danger' : 'good',
        'pretext' => $message,
        'fields'  => [
          [
            'title' => 'Host',
            'value' => !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'],
            'short' => false,
          ],
        ],
      ]],
    ];

    if ($request) {
      if (!empty($request->ip())) {
        $send['attachments'][0]['fields'][] = [
          'title' => 'IP origin',
          'value' => $request->ip(),
          'short' => false,
        ];
      }

      if (!empty($request->method())) {
        $send['attachments'][0]['fields'][] = [
          'title' => 'Method',
          'value' => $request->method(),
          'short' => false,
        ];
      }

      if (!empty($request->path())) {

        $send['attachments'][0]['fields'][] = [
          'title' => 'URL',
          'value' => $request->path() . '/',
          'short' => false,
        ];
      }

      if (!empty($request->header())) {
        $send['attachments'][0]['fields'][] = [
          'title' => 'Headers',
          'value' => (string) json_encode($request->header()),
          'short' => false,
        ];
      }

      if (!empty($data_return)) {
        $send['attachments'][0]['fields'][] = [
          'title' => 'Data received',
          'value' => (string) json_encode($data_return),
          'short' => false,
        ];
      }
    }

    if (!empty($data)) {
      $send['attachments'][0]['fields'][] = [
        'title' => 'Data Send -------',
        'value' => '',
        'short' => false,
      ];
    }
//Valores enviados en la consulta
    if (count($data)) {
      foreach ($data as $key => $value) {
        if ($key != 'usuario' && $key != 'password') {
          $send['attachments'][0]['fields'][] = [
            'title' => $key,
            'value' => $value,
            'short' => false,
          ];
        } else {
          $send['attachments'][0]['fields'][] = [
            'title' => $key,
            'value' => "Lo sentimos, es un dato sencible que no se puede mostrar",
          ];
        }
      }
    }

    if (!empty($data)) {
      $send['attachments'][0]['fields'][] = [
        'title' => '---------------',
        'value' => '',
        'short' => false,
      ];
    }

    if (!empty($time)) {
      $send['attachments'][0]['fields'][] = [
        'title' => 'Response time',
        'value' => $time . ' Segundos',
        'short' => false,
      ];
    }

    $res = Curl::executeCurl(
      env('LOG_SLACK_WEBHOOK_URL'),
      'POST',
      [
        'Content-Type: application/json',
        "cache-control: no-cache"],
      $send
    );

    if ($res['success']) {
      return 'OK';
    } else {
      return 'NOT_OK';
    }
  }

}
