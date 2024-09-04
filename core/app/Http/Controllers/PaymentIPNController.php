<?php

namespace App\Http\Controllers;

use App\Order;
use App\PaymentLog;
use App\PaymentMethod;
use App\TraitsFolder\CommonTrait;
use Illuminate\Http\Request;
use Stripe\Charge;
use Stripe\Stripe;
use Stripe\Token;

class PaymentIPNController extends Controller
{
    use CommonTrait;
    public function paypalIpn()
    {
        $payment_status		=	$_POST['payment_status'];
        $payer_status		=	$_POST['payer_status'];
        $receiver_email		=	$_POST['receiver_email'];
        $mc_gross			=	$_POST['mc_gross'];
        $custom				=	$_POST['custom'];
        $ip = gethostbyaddr($_SERVER['REMOTE_ADDR']);
        $paypal = PaymentMethod::whereId(1)->first();
        $paypal_email = $paypal->val1;
        if($payer_status=="verified" && $payment_status=="Completed" && $receiver_email==$paypal_email && $ip=="notify.paypal.com"){

            $data = PaymentLog::where('order_number' , $custom)->wherePayment_id(1)->first();
            $totalamo = $data->usd;
            if($totalamo == $mc_gross)
            {
                $order = Order::whereOrder_number($custom)->first();
                $order->payment_status = 1;
                $order->status = 1;
                $order->save();
                $data->status = 1;
                $data->save();
                $this->paymentConfirm($order->user_id,$mc_gross,$custom,"Paypal");
                session()->flash('message','Payment Successfully Completed.');
                session()->flash('type','success');
                return redirect()->route('order-complete',$custom);
            }
        }
    }
    public function perfectIPN()
    {
        $pay = PaymentMethod::whereId(2)->first();
        $passphrase=strtoupper(md5($pay->val2));

        define('ALTERNATE_PHRASE_HASH',  $passphrase);
        define('PATH_TO_LOG',  '/somewhere/out/of/document_root/');
        $string=
            $_POST['PAYMENT_ID'].':'.$_POST['PAYEE_ACCOUNT'].':'.
            $_POST['PAYMENT_AMOUNT'].':'.$_POST['PAYMENT_UNITS'].':'.
            $_POST['PAYMENT_BATCH_NUM'].':'.
            $_POST['PAYER_ACCOUNT'].':'.ALTERNATE_PHRASE_HASH.':'.
            $_POST['TIMESTAMPGMT'];
        $hash=strtoupper(md5($string));
        $hash2 = $_POST['V2_HASH'];
        if($hash==$hash2){

            $amo = $_POST['PAYMENT_AMOUNT'];
            $unit = $_POST['PAYMENT_UNITS'];
            $custom = $_POST['PAYMENT_ID'];
            $data = PaymentLog::where('order_number' , $custom)->wherePayment_id(2)->first();
            if($_POST['PAYEE_ACCOUNT']=="$pay->val1" && $unit=="USD" && $amo == $data->usd){

                $order = Order::whereOrder_number($custom)->first();
                $order->payment_status = 1;
                $order->status = 1;
                $order->save();
                $data->status = 1;
                $data->save();
                $this->paymentConfirm($order->user_id,$amo,$custom,"Perfect Money");
                session()->flash('message','Payment Successfully Completed.');
                session()->flash('type','success');
                return redirect()->route('order-complete',$custom);

            }else{
                session()->flash('message', 'Something error In Payment');
                session()->flash('type', 'warning');
                return redirect()->route('home');
            }
        }
    }
    public function btcIPN(){

        $depoistTrack = $_GET['invoice_id'];
        $secret = $_GET['secret'];
        $address = $_GET['address'];
        $value = $_GET['value'];
        $confirmations = $_GET['confirmations'];
        $value_in_btc = $_GET['value'] / 100000000;
        $trx_hash = $_GET['transaction_hash'];
        $data = PaymentLog::whereOrder_number($depoistTrack)->wherePayment_id(3)->first();
        if($data->status == 0){

            if ($data->btc_amo == $value_in_btc && $data->btc_acc == $address && $secret=="bitcoin_secret" && $confirmations>2){

                $order = Order::whereOrder_number($depoistTrack)->first();
                $order->payment_status = 1;
                $order->status = 1;
                $order->save();
                $data->status = 1;
                $data->save();
                $this->paymentConfirm($order->user_id,$order->total,$depoistTrack,"Bitcoin Payment");
                session()->flash('message','Payment Successfully Completed.');
                session()->flash('type','success');
                return redirect()->route('order-complete',$depoistTrack);

            }
        }
    }
    public function submitStripe(Request $request)
    {
        $this->validate($request,[
            'amount' => 'required',
            'custom' => 'required',
            'cardNumber' => 'required|numeric',
            'cardExpiryMonth' => 'required|numeric|digits:2',
            'cardExpiryYear' => 'required|numeric|digits:4',
            'cardCVC' => 'required|numeric',
        ]);
        $data = PaymentLog::whereOrder_number($request->custom)->wherePayment_id(4)->first();
        $amm = $data->usd;
        $cc = $request->cardNumber;
        $emo = $request->cardExpiryMonth;
        $eyr = $request->cardExpiryYear;
        $cvc = $request->cardCVC;
        $basic = PaymentMethod::whereId(4)->first();
        Stripe::setApiKey($basic->val1);
        try{
            $token = Token::create(array(
                "card" => array(
                    "number" => $cc,
                    "exp_month" => $emo,
                    "exp_year" => $eyr,
                    "cvc" => $cvc
                )
            ));
            if (!isset($token['id'])) {
                session()->flash('message','Stripe Token not generated.');
                session()->flash('type','danger');
                return redirect()->route('payment',$request->custom);
            }
            $charge = Charge::create(array(
                'card' => $token['id'],
                'currency' => 'USD',
                'amount' => $amm * 100,
                'description' => 'Multi item',
            ));
            if ($charge['status'] == 'succeeded' ) {

                $order = Order::whereOrder_number($request->custom)->first();
                $order->payment_status = 1;
                $order->status = 1;
                $order->save();
                $data->status = 1;
                $data->save();
                $this->paymentConfirm($order->user_id,$order->total,$request->custom,"Stripe Card");
                session()->flash('message','Payment Successfully Completed.');
                session()->flash('type','success');
                return redirect()->route('order-complete',$request->custom);

            }else{
                session()->flash('message','Something Wrong With Card.');
                session()->flash('type','warning');
                return redirect()->route('payment',$request->custom);
            }

        }catch (\Exception $e){
            session()->flash('message','Something Wrong With Stripe.');
            session()->flash('type','warning');
            return redirect()->route('payment',$request->custom);
        }
    }
    public function skrillIPN()
    {
        $payment = PaymentMethod::whereId(5)->first();
        $concatFields = $_POST['merchant_id']
            .$_POST['transaction_id']
            .strtoupper(md5($payment->val2))
            .$_POST['mb_amount']
            .$_POST['mb_currency']
            .$_POST['status'];
        $MBEmail = $payment->val1;
        // Ensure the signature is valid, the status code == 2,
        // and that the money is going to you
        $custom = $_POST['transaction_id'];
        $data = PaymentLog::whereOrder_number($custom)->wherePayment_id(5)->first();
        if (strtoupper(md5($concatFields)) == $_POST['md5sig']
            && $_POST['status'] == 2
            && $_POST['pay_to_email'] == $MBEmail)
        {
            $order = Order::whereOrder_number($custom)->first();
            $order->payment_status = 1;
            $order->status = 1;
            $order->save();
            $data->status = 1;
            $data->save();
            $this->paymentConfirm($order->user_id,$order->total,$custom,"Skrill Payment");
            session()->flash('message','Payment Successfully Completed.');
            session()->flash('type','success');
            return redirect()->route('order-complete',$custom);
        }
        else
        {
            session()->flash('message','Something Wrong With Skrill.');
            session()->flash('type','warning');
            return redirect()->route('payment',$custom);
        }
    }

    public function payzaIPN()
    {
        define("IPN_V2_HANDLER", "https://secure.payza.com/ipn2.ashx");
        define("TOKEN_IDENTIFIER", "token=");
        $token = urlencode($_POST['token']);
        $token = TOKEN_IDENTIFIER . $token;
        $response = '';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, IPN_V2_HANDLER);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        if (strlen($response) > 0) {
            if (urldecode($response) == "INVALID TOKEN") {
                session()->flash('message','Invalid Token in Payza.');
                session()->flash('type','warning');
                return redirect()->route('home');
            } else {
                $response = urldecode($response);
                $aps = explode("&", $response);
                $info = array();
                foreach ($aps as $ap) {
                    $ele = explode("=", $ap);
                    $info[$ele[0]] = $ele[1];
                }
                $receivedMerchantEmailAddress = $info['ap_merchant'];
                $transactionStatus = $info['ap_status'];
                $testModeStatus = $info['ap_test'];
                $purchaseType = $info['ap_purchasetype'];
                $totalAmountReceived = $info['ap_totalamount'];
                $feeAmount = $info['ap_feeamount'];
                $netAmount = $info['ap_netamount'];
                $transactionReferenceNumber = $info['ap_referencenumber'];
                $currency = $info['ap_currency'];
                $transactionDate = $info['ap_transactiondate'];
                $transactionType = $info['ap_transactiontype'];
                $customerFirstName = $info['ap_custfirstname'];
                $customerLastName = $info['ap_custlastname'];
                $customerAddress = $info['ap_custaddress'];
                $customerCity = $info['ap_custcity'];
                $customerState = $info['ap_custstate'];
                $customerCountry = $info['ap_custcountry'];
                $customerZipCode = $info['ap_custzip'];
                $customerEmailAddress = $info['ap_custemailaddress'];
                $myItemName = $info['ap_itemname'];
                $myItemCode = $info['ap_itemcode'];
                $myItemDescription = $info['ap_description'];
                $myItemQuantity = $info['ap_quantity'];
                $myItemAmount = $info['ap_amount'];
                $additionalCharges = $info['ap_additionalcharges'];
                $shippingCharges = $info['ap_shippingcharges'];
                $taxAmount = $info['ap_taxamount'];
                $discountAmount = $info['ap_discountamount'];
                $myCustomField_1 = $info['apc_1'];
                $myCustomField_2 = $info['apc_2'];
                $myCustomField_3 = $info['apc_3'];
                $myCustomField_4 = $info['apc_4'];
                $myCustomField_5 = $info['apc_5'];
                $myCustomField_6 = $info['apc_6'];
                $custom = $myItemCode;
                $data = PaymentLog::whereOrder_number($custom)->wherePayment_id(6)->first();
                if ($transactionStatus == 'Success') {

                    $order = Order::whereOrder_number($custom)->first();
                    $order->payment_status = 1;
                    $order->status = 1;
                    $order->save();
                    $data->status = 1;
                    $data->save();
                    $this->paymentConfirm($order->user_id,$order->total,$custom,"Payza Payment");
                    session()->flash('message','Payment Successfully Completed.');
                    session()->flash('type','success');
                    return redirect()->route('order-complete',$custom);

                } else {
                    session()->flash('message','Transaction Not Complete With Payza.');
                    session()->flash('type','warning');
                    return redirect()->route('payment',$custom);
                }
            }
        } else {
            session()->flash('message','Something Wrong With Payza.');
            session()->flash('type','warning');
            return redirect()->route('home');
        }
    }
}
