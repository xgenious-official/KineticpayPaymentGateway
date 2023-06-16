<?php

namespace Modules\KineticpayPaymentGateway\Http\Controllers;

use App\Helpers\ModuleMetaData;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class KineticpayPaymentGatewayAdminPanelController extends Controller
{
    public function settings()
    {
        $all_module_meta_data = (new ModuleMetaData("KineticpayPaymentGateway"))->getExternalPaymentGateway();
        $kineticpay = array_filter($all_module_meta_data,function ( $item ){
            if ($item->name === "KineticPay"){
                return $item;
            }
        });
        $kineticpay = current($kineticpay);
        return  view("kineticpaypaymentgateway::admin.settings",compact("kineticpay"));
    }

    public function settingsUpdate(Request $request){
        $request->validate([
            "kineticpay_merchant_key" => "required|string",
        ]);

        update_static_option("kineticpay_merchant_key",$request->kineticpay_merchant_key);

        if(is_null(tenant())){
            $jsonModifier = json_decode(file_get_contents("core/Modules/KineticpayPaymentGateway/module.json"));
            $jsonModifier->nazmartMetaData->paymentGateway->status = $request?->kineticpay_status === 'on';
            $jsonModifier->nazmartMetaData->paymentGateway->test_mode = $request?->kineticpay_test_mode_status === 'on';
            $jsonModifier->nazmartMetaData->paymentGateway->admin_settings->show_admin_landlord = $request?->kineticpay_landlord_status === 'on';
            $jsonModifier->nazmartMetaData->paymentGateway->admin_settings->show_admin_tenant = $request?->kineticpay_tenant_status === 'on';

            file_put_contents("core/Modules/KineticpayPaymentGateway/module.json",json_encode($jsonModifier));
        }



        return back()->with(["msg" => __("Settings Update"),"type" => "success"]);
    }
}
