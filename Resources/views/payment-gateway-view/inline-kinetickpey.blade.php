<div class="payment_box payment_method_kineticPay">
    <h6>{{__("Pay securely with kineticPay.")}}</h6>
    <div class="kineticpay-bank" >
       <label for="kineticpay_bank" class="">{{__("Choose Payment Method")}}, “kineticpay&nbsp;<abbr class="required" title="required">*</abbr></label>
        <div class="woocommerce-input-wrapper">
                <select name="kineticpay_bank" id="kineticpay_bank" class="select " data-allow_clear="true" data-placeholder="Select Bank">
                    <option value="" selected="selected">Select Bank</option>
                    <option value="ABMB0212">Alliance Bank Malaysia Berhad</option>
                    <option value="ABB0233">Affin Bank Berhad</option>
                    <option value="AMBB0209">AmBank (M) Berhad</option>
                    <option value="BCBB0235">CIMB Bank Berhad</option>
                    <option value="BIMB0340">Bank Islam Malaysia Berhad</option>
                    <option value="BKRM0602">Bank Kerjasama Rakyat Malaysia Berhad</option>
                    <option value="BMMB0341">Bank Muamalat (Malaysia) Berhad</option>
                    <option value="BSN0601">Bank Simpanan Nasional Berhad</option>
                    <option value="CIT0219">Citibank Berhad</option>
                    <option value="HLB0224">Hong Leong Bank Berhad</option>
                    <option value="HSBC0223">HSBC Bank Malaysia Berhad</option>
                    <option value="KFH0346">Kuwait Finance House</option>
                    <option value="MB2U0227">Maybank2u / Malayan Banking Berhad</option>
                    <option value="MBB0228">Maybank2E / Malayan Banking Berhad E</option>
                    <option value="OCBC0229">OCBC Bank (Malaysia) Berhad</option>
                    <option value="PBB0233">Public Bank Berhad</option>
                    <option value="RHB0218">RHB Bank Berhad</option>
                    <option value="SCB0216">Standard Chartered Bank (Malaysia) Berhad</option>
                    <option value="UOB0226">United Overseas Bank (Malaysia) Berhad</option>
                </select>
            </div>
        <div>
        </div>
    </div>
</div>
<style>
    .payment_box.payment_method_kineticPay {
        background-color: #f1f1f1;
        padding: 20px;
        margin: 20px 0;
    }
    .payment_box.payment_method_kineticPay h6 {
        margin-bottom: 20px;
    }

    .payment_box.payment_method_kineticPay label {
        margin-bottom: 10px;
        font-weight: 600;
    }

    .payment_box.payment_method_kineticPay select {
        border: 1px solid #f2f2f2;
        font-size: 14px;
        padding: 10px;
    }
    abbr.required {
        color: red;
    }
</style>
<script type="text/javascript">
    //if user select paddle then hide send a request to the server with all order info so that in can send users to paddle server for overlay checkout
    window.addEventListener("DOMContentLoaded", (event) => {
        const orderFormSubmitButton = document.querySelector("form.contact-page-form.order-form button[type='submit']");
        const paymentGatewayButton = document.querySelector(".payment-gateway-wrapper ul li");

        // paymentGatewayButton.addEventListener("click",function (event){
        //     let selectedPaymentGatewayName = this.getAttribute("data-gateway");
        //     if( selectedPaymentGatewayName === "kinetickpey"){
        //         let keniticPayBank = document.querySelector("#kineticpay_bank").value;
        //         orderFormSubmitButton.setAttribute("disabled",true);
        //         if(keniticPayBank == ""){
        //         }
        //     }else{
        //         orderFormSubmitButton.setAttribute("disabled",false);
        //     }
        // });

        orderFormSubmitButton.addEventListener("click",function (event){
            let keniticPayBank = document.querySelector("#kineticpay_bank").value;
            let selectedPaymentGatewayName = document.querySelector(".payment-gateway-wrapper ul li.selected").getAttribute("data-gateway");
           if(selectedPaymentGatewayName === "kinetickpey" && keniticPayBank == ""){
               alert("{{__('select a payment method first...')}}")
               // formContainer;
           }
        });
{{--        @if(request()->is("plan-order*"))--}}
{{--            document.querySelector('.payment-gateway-wrapper li[data-gateway="wipay"]').style.display = "none";--}}
{{--        @endif--}}
    });
</script>
