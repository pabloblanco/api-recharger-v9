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
*   @var protected $fillable    => The attributes that are mass assignable.
*   @var protected $hidden      => The attributes that should be hidden for serialization.
*   @var protected $casts       => The attributes that should be cast.
* 
*****************************************************************************************************************************/

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
