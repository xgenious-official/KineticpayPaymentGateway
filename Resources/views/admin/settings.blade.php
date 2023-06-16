@extends(route_prefix()."admin.admin-master")
@section('title') {{__('Kineticpay  Settings')}}@endsection
@section("content")
    <div class="col-12 stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">{{__('Kineticpay Settings')}}</h4>
                <x-error-msg/>
                <x-flash-msg/>
                <form class="forms-sample" method="post" action="{{route('kineticpaypaymentgateway.'.route_prefix().'admin.settings')}}">
                    @csrf
                    <x-fields.input type="text" value="{{get_static_option('kineticpay_merchant_key')}}" name="kineticpay_merchant_key" label="{{__('Kineticpay Merchant Key')}}"/>


{{--                    <x-fields.switcher label="{{__('Kineticpay Test Mode Enable/Disable')}}" name="kineticpay_test_mode_status" value="{{$kineticpay->test_mode}}"/>--}}

                    <x-fields.switcher label="{{__('Kineticpay Enable/Disable')}}" name="kineticpay_status" value="{{$kineticpay->status}}"/>
                    @if(is_null(tenant()))
                    <x-fields.switcher label="{{__('Kineticpay Enable/Disable Landlord Websites')}}" name="kineticpay_landlord_status" value="{{$kineticpay->admin_settings->show_admin_landlord}}"/>
                    <x-fields.switcher label="{{__('Kineticpay Enable/Disable Tenant Websites')}}" name="kineticpay_tenant_status" value="{{$kineticpay->admin_settings->show_admin_tenant}}"/>
                    @endif
                    <button type="submit" class="btn btn-gradient-primary mt-5 me-2">{{__('Save Changes')}}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
