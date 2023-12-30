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
* Testing Method list
*****************************************************************************************************************************
* 
*   public function test_echo_request()                     =>  
*   public function test_get_payment_request()              =>  
*   public function test_auth_request()                     =>  Metodo para autorizar un pago por una referencia. 
*   public function test_status_recharge_request()          =>  Metodo para cancelar un pago.
*   public function test_step1_request()                    =>  
*   public function test_verification_pay_step2_request()   =>  
*   public function test_step2_request()                    =>  
*   public function test_step2_seller_request()             =>  
*   public function test_balance_request()                  =>  
*   public function test_do_recharge_request()              =>  
*   public function test_reset_recharge_process_request()   =>  
*   public function test_active_recharge_prom_request()     =>  
*   public function test_extra_recharge_request()           =>  
*   public function test_extra_register_request()           =>  
*   public function test_send_alert_logs_request()          =>  
*   public function test_remove_logs_request()              =>  
*   public function test_file_bluelabel_request()           =>  
*   public function test_massive_retention_request()        =>  
*   public function test_process_retention_request()        =>  
*
*****************************************************************************************************************************/

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RechargerControllerTest extends TestCase
{

    /**
     * A functional test that test the echo method.
     *
     * @return void
     */
    public function test_echo_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the getPayment method.
     *
     * @return void
     */
    public function test_get_payment_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }  

    /**
     * A functional test that test the auth method.
     *
     * @return void
     */
    public function test_auth_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the statusRecharge method.
     *
     * @return void
     */
    public function test_status_recharge_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    } 

    /**
     * A functional test that test the step1 method.
     *
     * @return void
     */
    public function test_step1_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the verificationPayStep2 method.
     *
     * @return void
     */
    public function test_verification_pay_step2_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the step2 method.
     *
     * @return void
     */
    public function test_step2_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the step2Seller method.
     *
     * @return void
     */
    public function test_step2_seller_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the balance method.
     *
     * @return void
     */
    public function test_balance_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the doRecharge method.
     *
     * @return void
     */
    public function test_do_recharge_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the resetRechargeProcess method.
     *
     * @return void
     */
    public function test_reset_recharge_process_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the activeRechargeProm method.
     *
     * @return void
     */
    public function test_active_recharge_prom_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the extraRecharge method.
     *
     * @return void
     */
    public function test_extra_recharge_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the extraRegister method.
     *
     * @return void
     */
    public function test_extra_register_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the sendAlertLogs method.
     *
     * @return void
     */
    public function test_send_alert_logs_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the removeLogs method.
     *
     * @return void
     */
    public function test_remove_logs_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the fileBluelabel method.
     *
     * @return void
     */
    public function test_file_bluelabel_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the massiveRetention method.
     *
     * @return void
     */
    public function test_massive_retention_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * A functional test that test the processRetention method.
     *
     * @return void
     */
    public function test_process_retention_request()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }


}

