<?php

namespace Modules\KineticpayPaymentGateway\Http\Controllers;

use App\Enums\PaymentRouteEnum;
use App\Events\TenantRegisterEvent;
use App\Helpers\FlashMsg;
use App\Helpers\Payment\DatabaseUpdateAndMailSend\LandlordPricePlanAndTenantCreate;
use App\Mail\BasicMail;
use App\Mail\PlaceOrder;
use App\Mail\ProductOrderEmail;
use App\Mail\ProductOrderEmailAdmin;
use App\Mail\ProductOrderManualEmail;
use App\Mail\TenantCredentialMail;
use App\Models\PaymentLogs;
use App\Models\ProductOrder;
use App\Models\Tenant;
use App\Models\User;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;
use Modules\Wallet\Http\Services\WalletService;
use Xgenious\Paymentgateway\Base\PaymentGatewayHelpers;
use Xgenious\Paymentgateway\Facades\XgPaymentGateway;

class KineticpayPaymentGatewayController extends Controller
{
    /**
     * Display a listing of the resource.
     * @method chargeCustomer
     *
     * @return checkout url redirect user to the payment gateway website
     *
     * this method will receive all the information from the main script, while any user select any payment gateway for payment. this method will receive all of that data and make it ready for redirect user to the payment provider website for payment.
     *
     */
    public function chargeCustomer($args)
    {
        //detect it is coming from which method for which kind of payment
        //detect it for landlord or tenant website
        if (in_array($args["payment_type"],["price_plan","deposit"]) && $args["payment_for"] === "landlord"){
            return $this->chargeCustomerForLandlordPricePlanPurchase($args);
        }
        // all tenant payment process will from here....
        if (in_array($args["payment_type"],["shop_checkout"]) && $args["payment_for"] === "tenant"){
            return $this->chargeCustomerForLandlordPricePlanPurchase($args);
        }
        abort(404);
        //make a request to Siteways server to generate checkout url based on static data
    }

    /**
     * @method chargeCustomerForLandlordPricePlanPurchase
     * @param array $arg
     *
     * This method is responsible for sending request to the payment gatewy provider for redirect or charge your customer
     * */
    private function chargeCustomerForLandlordPricePlanPurchase($args){
//        dd($args,\request()->all());


        //todo:: test code start
        $bankid = \request()->kineticpay_bank;
        if (empty($bankid)){
            abort(501,__("you must have to select a bank for pay with kineticpay"));
        }
// This is merchant_key get from Collection page
        $secretkey = get_static_option("kineticpay_merchant_key");
        if (empty($secretkey)){
            abort(501,__("merchant key no provided"));
        }
        $payment_details = $args["payment_details"];
// This variable should be generated or populated from your system
        $reference = [
            'order_id' => XgPaymentGateway::wrapped_id($payment_details['id']),
            'payment_type' => $args['payment_type']
        ];
        $name = $payment_details["name"];
        $phone = $payment_details["phone"] ?? " ";
        $email = $payment_details["email"];
        $order_id = XgPaymentGateway::wrapped_id($payment_details["id"]);
        $amount = $args["total"];
        $description = json_encode($reference);
        $successURL = $args["success_url"];//route('kineticpaypaymentgateway.landlord.price.plan.ipn');
        $failURL = $args["cancel_url"];
        Session::put("keneticpay_last_order_id",$payment_details["id"]);


        if ($args["payment_type"] === "deposit" && $args["payment_for"] === "landlord"){
            $user_details = User::find($args["payment_details"]["user_id"] ?? "");
            if (!is_null($user_details)){
                $email = $user_details->email;
                $name = $user_details->name;
            }
            $product_name = __("Wallet Recharge");
            $success_url = route("landlord.user.wallet.history");
            $reference["history_id"] = $paymentInfo['id'] ?? " ";
        }

        $ipn_route_prefix = "landlord";

        if($args["payment_type"] === "shop_checkout" && $args["payment_for"] === "tenant"){
            $product_name = __("Payment for order")." ".$paymentInfo['id'];
            $ipn_route_prefix = "tenant";
        }

        $checkStatURL = route('kineticpaypaymentgateway.'.$ipn_route_prefix.'.price.plan.ipn')."?orderid=" . $order_id;

        $data = [
            'merchant_key' => $secretkey,
            'invoice' => $order_id,
            'amount' => $amount,
            'description' => $description,
            'bank' => $bankid,
            'callback_success' => $successURL,
            'callback_error' => $failURL,
            'callback_status' => $checkStatURL
        ];
// API Endpoint URL
        $url = "https://manage.kineticpay.my/payment/create";
        $res = Http::acceptJson()->post($url,$data);
        $result = $res->object();
        if (property_exists($result,"html")){
            return  $result?->html;
        }
        abort(501,__("failed to connect kineticpay server."));


    }


    /**
     * @method landlordPricePlanIpn
     * param $request
     *
     *  this is ipn/callback/webhook method for the payment gateway i am implementing, it will received information form the payment gatewya after successful payment by the user
     *
     * */
    public function landlordPricePlanIpn(Request $request){

        //write code for success callback , to receive callback and check it again that it is actually paid or not.
        $payment_data = $this->capturePaymentAndVerifyAgain();
        Log::info("keneticpay ipn start");
        Log::info($payment_data);
        Log::info("keneticpay ipn end");
        if ($payment_data["status"] === "complete"){
            if ($payment_data["order_type"] === "price_plan"){
                $this->runPostPaymentProcessForLandlordPricePlanSuccessPayment($payment_data);
                //redirect to success page
                return redirect()->to(route('landlord.frontend.order.payment.success', random_int(111111,999999).$payment_data['order_id'].random_int(111111,999999)));
            }elseif ($payment_data["order_type"] === "deposit"){
               return $this->runPostPaymentProcessForLandlordWalletDepositSuccessPayment($payment_data);
                //todo run order type deposit post payment processes
            }elseif ($payment_data["order_type"] === "shop_checkout"){
                $this->runPostPaymentProcessForTenantdShopCheckoutSuccessPayment($payment_data);
                //todo run order type deposit post payment processes
            }
        }
        //write code if the payment verify failed
    }

    /**
     * @method TenantSiteswayIpn
     * param $request
     *
     *  this is ipn/callback/webhook method for the payment gateway i am implementing, it will received information form the payment gatewya after successful payment by the user
     *
     * */
    public function TenantSiteswayIpn(Request $request){

        //write code for success callback , to receive callback and check it again that it is actually paid or not.
        $payment_data = $this->capturePaymentAndVerifyAgain();

        if ($payment_data["status"] === "complete"){
            if ($payment_data["order_type"] === "shop_checkout"){
                $this->runPostPaymentProcessForTenantdShopCheckoutSuccessPayment($payment_data);
                //todo run order type deposit post payment processes
                $order_id = random_int(111111,999999) . $payment_data['order_id'] . random_int(111111,999999);
                return redirect()->route('tenant.user.frontend.order.payment.success',$order_id);
            }
        }
        //write code if the payment verify failed
    }

    /**
     * @methodn capturePaymentAndVerifyAgain
     * return array $payment_data
     *
     * this method is responsible for capture payment info from success callback and verify it before return payment information to the post payment processor
     * */
    private function capturePaymentAndVerifyAgain(){

        // dd(\request()->all());

        // This is merchant_key get from Collection page
        $secretkey = get_static_option("kineticpay_merchant_key");
       $order_id = Session::get("keneticpay_last_order_id");
       Session::forget("keneticpay_last_order_id");
        if (empty($order_id)){
            abort(501, __("order id not found"));
        }
// This variable should be generated or populated from your system
// API Endpoint URL
        /*
         *
          'encoded_transaction_data' => '{"record":{
        "order_number":"10001",
        "order_description":"Payment for Order No 10001, Bu",
        "order_currency":"MYR",
        "order_amount":"2.00",
        "merchant_name":"KINETIC INNOVATIVE TECHNOLOGIES SDN BHD",
        "buyer_name":"Kinetic Test Bersih",
        "buyer_email":"faizalserver2010@gmail.com",
        "buyer_bank_name":"Maybank2U",
        "buyer_bank_code":"MB2U0227",
        "exchange_order_number":"1-686-799-407-105830",
        "show_custom_buyer_info_at_landing_page":null,
        "custom_buyer_name":null,
        "custom_buyer_email":null,
        "custom_buyer_personal_info":null,
        "custom_order_description":null,
        "html_form":false,
        "transaction_status":"Successful",
        "transaction_status_code":"00",
        "transaction_status_description":"Approved",
        "transaction_id":"2306151123270109",
        "transaction_date_time":"2023-06-15 11:23:27",
        "payment_model":"b2c"
        }}',
  'encrypted_transaction_data' => 'eyJpdiI6Ikp3amQ4bkxVSEQ4S2Juc3EzSGhiaHc9PSIsInZhbHVlIjoieVVucjE2SGJzZ3ExMW9nM2NqM0lUb0ZJYU5pS01EVDVrL0tJYkJSVlRkMkgxRW11VjVENC9yVWtkVUxDSmxKVVpWSmFxQ0t3cXNOVFVUbmNyd2Z5S0NRbzA4V1lzZTU1eWNkc3YvUkZWR0VyT2phc2pRVWxQRDdzWnhBOHJzTXB3b3U4SXhweUxza2QzRHRYTnh3SmhQdGdrc0xhSVpzYVMyTlN0RXNOYVFIK1pTaCtwNm4wYWVSK3d5SXVyT2gzUDU1a2R4T3c2dU15SnFXZGxWTmV1Ly9FNStCbE0zamNVeDJXS3BHVnJ5WkxBMEJCbCtwMUhBZ04zT05CY2tMdUJOZlVCNm9FZDFMdmdOZjliL1ZQUXhpb21ZVVM5Tm5TVjNoM0ZYN0RUcGN5L0pEb043b3ptQTNlb3A3THlVOG95LytnTUpzK05mWXFza2ZYNk96RCtUOUpkU2ZJMnFvK2NucVVLOHBycWFLMy94UG85SEkvYldyVE5jUUVuMzhlR01tVXZSRUtRczhrTFBrMHlqMmFObjE1Rjh3dEZMK3BMVnVnYXNxOTJldnljb1hod3hFL0FUNHFJbkxIT3phb0RRQ1h2Vy9YcnI4UW5Ic3JVRlpqbEVlNE05bjJJVGJOTTVXcnRFRDA2WEZHZFBUdTZTWlVtZFk0NFAvU2VKOG9XS2RINk00aFN1bXJlZHBBc2R4b2pMcmsreFYvYXhRS1J6Ukxld1N5NTJOSlpjQ0Z0TGJycGxSeHFaeXRPOUtaSlV2bHhJWmxmdFNobnc3b3YwMFF3SjhjYUxBY0NMZ0RZckgzMUdJZk1vR0xISURGazNlQ1ZTd2JaK0VQVHVLUnpZVmhla0lCbHdmZ1U2dHZHVUVlRk0zMjBMbGs1SWZwdEhhT3p1VSt4NEQxb3NoU08vbW1XY1VRYm1xVkVQUWtEZE5pcW1iWWk2dkFIUmtPdWZ3RWFvR3E0Z3VuMW5wRDZvbUl4V0dDUkU4ak1KRFZMc0FHc21kQTluVy9jQ3J1SFA4Q01lS21QdUNBcCszemlLNTNTMWVJbGlueWFlUUoyeTNyTmZhN0xVcVpxSFRaYlhkRFZ4QTJtdHVGRFdrcUYxcC8wcEJkVTlQcEZlN2xYc2Q3Q2wrUkRVU2VOQVdudWdBTjB5T3luVkhqYXNGSTNIMjFaMUpQbmh1NzBuL3krSFhVU29tZEs0ei9NMWI4K0ZOY2xodkU3N1gvaGVzUlhSWnppcVFHTlk2SEUxaUVJaWFhaWM1bHlidnROL2k3MHlMSmFyb3ovK0JUWmRReE9NUThGMHFzYWgvakxPVXNYV1NvRUp3bXdKWDBrNGpFekg0ZmZvbEo4SnJrOHRVTjdBbzF6L2M3THFzeThKbVFxdkFVWEt0SlZZeUExLy9YM2crSDdGQnZVdGNXcWZLdTlCeEZTMUNJakwyOFVDWDFKS21PbHVpTUJFQk4zazhXM2ovcit6RmpMSnJBWjRhZVJRRHI1Z00xVDRvQkQ4eXAzRG1zKytBZXhJSy9keDk2SVpZTzdPWVEvVXVOZmZKeUh1RndkRzdEb2h3QU40dkN3a0pzV0dvYmtBSVY1NDVJOUY0cU9DYkpPYjlWbkVHdnoxZHFKSFVGSmQ1blJaM3c2bWR0VVN4M3JQaHBFVHV2aEUwQVdwMGJWT0ZybnNVdnRJR3QvK2E1WHhja0xhME50RTFaTnYzVmVWeXljTjhreXRoUUtNQktFaWNBYmw3bGlzTWpIb0xJZzA4PSIsIm1hYyI6IjU1MDhmNWEyODI3OGU5MTFmZTQzOWNhYmE2ZTE4MjI0NTIwYWZiZTZlN2E2YTZkODlmNDIzZWE3YjJjZTQ5ZTIiLCJ0YWciOiIifQ==',
  'receipt_url' => 'https://nazmart.devrobin.com/kineticpaypaymentgateway/landlord-price-plan-kineticpay',
  'hide_page_header' => '1',
         * */
        $url = "https://manage.kineticpay.my/payment/status";
        $res = Http::acceptJson()->post($url . '?merchant_key=' . $secretkey . '&invoice=' . (string) $order_id );
        if ($res->ok()){
            $result = $res->object();
            $encoded_transaction_data = json_decode($result->encoded_transaction_data);
            $order_id = $result->record->order_number ?? "";
            $order_description = json_decode($result->record->order_description) ?? "";
            $transaction_status = $result->record->transaction_status;
            $transaction_id = $result->record->transaction_id;

            if (!empty($transaction_id) && !empty($order_id) && strtolower($transaction_status) === 'successful'){

                // add condition
                if ($order_description->payment_type === 'price_plan' ){
                    $paymentLogs = PaymentLogs::find(PaymentGatewayHelpers::unwrapped_id($order_id));
                    if (is_null($paymentLogs)){
                        abort(501,__("order id not found in our database"));
                    }
                }

                //todo check if it is wallet or not
                if ($order_description->payment_type === 'deposit' && !empty($order_description->history_id)){
                    WalletHistory::find($order_description->history_id);
                    if (is_null($paymentLogs)){
                        abort(501,__("wallet history id not found in our database"));
                    }
                }
                if ($order_description->payment_type === 'shop_checkout'){
                    //todo fetch tenant shop total amount...
                    $shopOrder = ProductOrder::find(PaymentGatewayHelpers::unwrapped_id($order_id ?? ""));
                    if (is_null($shopOrder)){
                        abort(501,__("shop order id not found in our database"));
                    }
                }

                return $this->verified_data([
                    'status' => 'complete',
                    'transaction_id' => $transaction_id ,
                    'order_id' => PaymentGatewayHelpers::unwrapped_id($order_id ?? ""),
                    'order_type' => $order_description->payment_type ?? "",
                    "history_id" => property_exists($order_description,"history_id") ? $order_description->history_id : " "
                ]);
            }
        }


        return $this->verified_data([
            'status' => 'failed',
            'order_id' => PaymentGatewayHelpers::unwrapped_id($order_id ?? ""),
            'order_type' => $data->payment_type ?? ""
        ]);
    }

    /**
     * @method runPostPaymentProcessForLandlordPricePlanSuccessPayment
     * @param array $payment_data
     * this method will run process for after a successfully payment for landlord price plan payment.
     * */
    private function runPostPaymentProcessForLandlordPricePlanSuccessPayment($payment_data)
    {
        if (isset($payment_data['status']) && $payment_data['status'] === 'complete') {
            try {
                $this->landlordPricePlanPostPaymentUpdateDatabase($payment_data['order_id'], $payment_data['transaction_id']);
                $this->landlordPricePlanPostPaymentSendOrderMail($payment_data['order_id']);
                $this->landlordPricePlanPostPaymentTenantCreateEventWithCredentialMail($payment_data['order_id']);
                $this->landlordPricePlanPostPaymentUpdateTenant($payment_data);

            } catch (\Exception $exception) {
                $message = $exception->getMessage();
                if(str_contains($message,'Access denied')){
                    if(request()->ajax()){
                        abort(462,__('Database created failed, Make sure your database user has permission to create database'));
                    }
                }

                $payment_details = PaymentLogs::where('id',$payment_data['order_id'])->first();
                if(empty($payment_details))
                {
                    abort(500,__('Does not exist, Tenant does not exists'));
                }
                LandlordPricePlanAndTenantCreate::store_exception($payment_details->tenant_id,'Domain create',$exception->getMessage(), 0);

                //todo: send an email to admin that this user databse could not able to create automatically

                try {
                    $message = sprintf(__('Database Creating failed for user id %1$s , please checkout admin panel and generate database for this user from admin panel manually'),
                        $payment_details->user_id);
                    $subject = sprintf(__('Database Crating failed for user id %1$s'),$payment_details->user_id);
                    Mail::to(get_static_option('site_global_email'))->send(new BasicMail($message,$subject));

                } catch (\Exception $e) {
                    LandlordPricePlanAndTenantCreate::store_exception($payment_details->tenant_id,'domain failed email',$e->getMessage(), 0);
                }
            }

            $order_id = wrap_random_number($payment_data['order_id']);
            return redirect()->route("landlord.frontend.order.payment.success", $order_id);
        }

        return $this->landlordPricePlanPostPaymentCancelPage();
    }

    /**
     * @method landlordPricePlanPostPaymentUpdateDatabase
     * @param id $order_id, string  $transaction_id
     *
     * update database for the payment success record
     * */

    private function landlordPricePlanPostPaymentUpdateDatabase($order_id, $transaction_id)
    {
        PaymentLogs::where('id', $order_id)->update([
            'transaction_id' => $transaction_id,
            'status' => 'complete',
            'payment_status' => 'complete',
            'updated_at' => Carbon::now()
        ]);
    }

    /**
     * @method landlordPricePlanPostPaymentSendOrderMail
     * @param id $order_id
     * send mail to admin and user regarding the payment
     * */
    private function landlordPricePlanPostPaymentSendOrderMail($order_id)
    {
        $package_details = PaymentLogs::where('id', $order_id)->first();
        $all_fields = [];
        unset($all_fields['package']);
        $all_attachment = [];
        $order_mail = get_static_option('order_page_form_mail') ? get_static_option('order_page_form_mail') : get_static_option('site_global_email');

        try {
            Mail::to($order_mail)->send(new PlaceOrder($all_fields, $all_attachment, $package_details, "admin", 'regular'));
            Mail::to($package_details->email)->send(new PlaceOrder($all_fields, $all_attachment, $package_details, 'user', 'regular'));

        } catch (\Exception $e) {
            //return redirect()->back()->with(['type' => 'danger', 'msg' => $e->getMessage()]);
        }
    }

    /**
     * @method landlordPricePlanPostPaymentTenantCreateEventWithCredentialMail
     * @param int $order_id
     * create tenant, create database, migrate database table, seed database dummy data, with a default admin account
     * */
    private function landlordPricePlanPostPaymentTenantCreateEventWithCredentialMail($order_id)
    {
        $log = PaymentLogs::findOrFail($order_id);
        if (empty($log))
        {
            abort(462,__('Does not exist, Tenant does not exists'));
        }

        $user = User::where('id', $log->user_id)->first();
        $tenant = Tenant::find($log->tenant_id);

        if (!empty($log) && $log->payment_status == 'complete' && is_null($tenant)) {
            event(new TenantRegisterEvent($user, $log->tenant_id, get_static_option('default_theme')));
            try {
                $raw_pass = get_static_option_central('tenant_admin_default_password') ??'12345678';
                $credential_password = $raw_pass;
                $credential_email = $user->email;
                $credential_username = get_static_option_central('tenant_admin_default_username') ?? 'super_admin';

                Mail::to($credential_email)->send(new TenantCredentialMail($credential_username, $credential_password));

            } catch (\Exception $e) {

            }

        } else if (!empty($log) && $log->payment_status == 'complete' && !is_null($tenant) && $log->is_renew == 0) {
            try {
                $raw_pass = get_static_option_central('tenant_admin_default_password') ?? '12345678';
                $credential_password = $raw_pass;
                $credential_email = $user->email;
                $credential_username = get_static_option_central('tenant_admin_default_username') ?? 'super_admin';

                Mail::to($credential_email)->send(new TenantCredentialMail($credential_username, $credential_password));

            } catch (\Exception $exception) {
                $message = $exception->getMessage();
                if(str_contains($message,'Access denied')){
                    abort(463,__('Database created failed, Make sure your database user has permission to create database'));
                }
            }
        }

        return true;
    }
/**
 * @method landlordPricePlanPostPaymentUpdateTenant
 * @param array $payment_data
 *
 * */
    private function landlordPricePlanPostPaymentUpdateTenant(array $payment_data)
    {
        try{
            $payment_log = PaymentLogs::where('id', $payment_data['order_id'])->first();
            $tenant = Tenant::find($payment_log->tenant_id);

            \DB::table('tenants')->where('id', $tenant->id)->update([
                'renew_status' => $renew_status = is_null($tenant->renew_status) ? 0 : $tenant->renew_status+1,
                'is_renew' => $renew_status == 0 ? 0 : 1,
                'start_date' => $payment_log->start_date,
                'expire_date' => get_plan_left_days($payment_log->package_id, $tenant->expire_date)
            ]);


        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            if(str_contains($message,'Access denied')){
                abort(462,__('Database created failed, Make sure your database user has permission to create database'));
            }
        }
    }

    /**
     * @method landlordPricePlanPostPaymentCancelPage
     * @return static cancel page for landlord price plan order
     * */

    private function landlordPricePlanPostPaymentCancelPage()
    {
        return redirect()->route('landlord.frontend.order.payment.cancel.static');
    }

    /**
     * payment gateway verified data return as payment_data
     * @method verified_data
     * @param $args
     * @return array $payment_data
     * */
    private function verified_data(array $args)
    {
        return array_merge(['status' => 'complete'],$args);
    }
    /**
     * write code for post process the payment information
     * @method runPostPaymentProcessForLandlordWalletDepositSuccessPayment
     * @param $payment_data
     * */
    private function runPostPaymentProcessForLandlordWalletDepositSuccessPayment(array $payment_data)
    {
//        dd($payment_data);
        if (isset($payment_data['status']) && $payment_data['status'] === 'complete'){
            $order_id = $payment_data['order_id'];
            $history_id = $payment_data["history_id"];
            $this->walletDepositUpdateDatabase($order_id, $payment_data['transaction_id'],$history_id);
            $this->walletDepositSendMailToAdmin($order_id);
            $new_order_id =  $order_id;
            return redirect()->to(route('landlord.user.wallet.history'))->with(['type' => 'success', 'msg' => 'Your wallet successfully credited']);
        }
    }
    /**
     * write code for post process the payment information for wallet balance update
     * @method walletDepositUpdateDatabase
     * @param mixed $order_id, mixed $transaction_id, mixed $history_id
     * */
    private function walletDepositUpdateDatabase(mixed $order_id, mixed $transaction_id, mixed $history_id)
    {
        $deposit_details = WalletHistory::find($history_id);

        DB::beginTransaction();
        try {
            WalletHistory::where('id', $history_id)->update([
                'payment_status' => 'complete',
                'transaction_id' => $transaction_id,
                'status' => 1,
            ]);

            $get_balance_from_wallet = Wallet::where('user_id',$deposit_details->user_id)->first();
            Wallet::where('user_id', $deposit_details->user_id)->update([
                'balance' => $get_balance_from_wallet->balance + $deposit_details->amount,
            ]);

            WalletService::check_wallet_balance($deposit_details->user_id);

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            //return redirect()->route('landlord.user.wallet.history')->with(FlashMsg::explain('danger', 'Something went wrong. Please try again after some while'));
        }
    }

    /**
     * write code for post process the sending mail to admin about wallet recharge by users
     * @method walletDepositSendMailToAdmin
     * @param $last_deposit_id
     * */
    public function walletDepositSendMailToAdmin($last_deposit_id)
    {
        if(empty($last_deposit_id)){
            return;
        }
        //Send order email to buyer
        try {
            $message_body = __('Hello an user just deposit to his wallet.').'</br>'.'<span class="verify-code">'.__('Deposit ID: ').$last_deposit_id.'</span>';
            \Mail::to(get_static_option('site_global_email'))->send(new BasicMail($message_body, __('Deposit Confirmation')));

        } catch (\Exception $e) {
            //
        }
    }
    /**
     * write code for post process the payment data for tenant shop checkout
     * @method runPostPaymentProcessForTenantdShopCheckoutSuccessPayment
     * @param $payment_data
     * */
    private function runPostPaymentProcessForTenantdShopCheckoutSuccessPayment(array $payment_data)
    {
        if (isset($payment_data['status']) && $payment_data['status'] === 'complete') {
            $this->TenantShopCheckoutSendOrderMail($payment_data['order_id']);
            $order_id = wrap_random_number($payment_data['order_id']);
            ProductOrder::find($payment_data['order_id'])->update([
                'payment_status' => 'success'
            ]);

            Cart::instance("default")->destroy();
            //todo

        }

    }
    /**
     * write code for post process the payment data for sending mail to admin and user about the product orders
     * @method TenantShopCheckoutSendOrderMail
     * @param $order_id
     * */
    private function TenantShopCheckoutSendOrderMail(mixed $order_id)
    {
        $order_details = ProductOrder::where('id', $order_id)->firstOrFail();
        $order_mail = get_static_option('order_page_form_mail') ?? get_static_option('tenant_site_global_email');

        try {
            //To User/Customer
            if ($order_details->checkout_type === 'digital')
            {
                Mail::to($order_mail)->send(new ProductOrderEmail($order_details));
            } else {
                Mail::to($order_mail)->send(new ProductOrderManualEmail($order_details));
            }

            // To Admin
            $admin_email = get_static_option('order_receiving_email') ?? get_static_option('tenant_site_global_email');
            if ($admin_email == null)
            {
                $admin = \App\Models\Admin::whereHas("roles", function($q){
                    $q->where("name", "Super Admin");
                })->first();
                $admin_email = $admin->email;
            }

            Mail::to($admin_email)->send(new ProductOrderEmailAdmin($order_details));

        } catch (\Exception $e) {

        }
    }

}
