<?php

namespace App\Http\Controllers;

use App\BasicSetting;
use App\Comment;
use App\Order;
use App\OrderItem;
use App\PaymentLog;
use App\PaymentMethod;
use App\Product;
use App\Review;
use App\TraitsFolder\CommonTrait;
use App\User;
use App\UserDetails;
use App\Wishlist;
use Carbon\Carbon;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class UserController extends Controller
{
    use CommonTrait;
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function editProfile()
    {
        $data['page_title'] = 'Edit Profile';
        $data['user'] = User::findOrFail(Auth::user()->id);
        return view('user.edit-profile',$data);
    }

    public function updateProfile(Request $request)
    {

        $user = User::findOrFail(Auth::user()->id);
        $request->validate([
            'email' => 'required|email|unique:users,email,'.$user->id,
            'first_name' => 'required',
            'last_name' => 'required',
            'image' => 'mimes:png,jpeg,jpg,gif'
        ]);
        $in = Input::except('_method','_token');
        if($request->hasFile('image')){
            $image = $request->file('image');
            $filename = 'user_'.time().'.'.$image->getClientOriginalExtension();
            $location = 'assets/images/user/' . $filename;
            Image::make($image)->resize(100,100)->save($location);
            $path = './assets/images/user/'.$user->image;
            if ($user->image != 'user-default.png'){
                File::delete($path);
            }
            $in['image'] = $filename;
        }
        $user->fill($in)->save();
        session()->flash('message','Profile Update Successfully.');
        session()->flash('type','success');
        return redirect()->back();
    }

    public function getPassword()
    {
        $data['page_title'] = 'Change Profile';
        return view('user.change-password',$data);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
           'old_password' => 'required',
            'password' => 'required|confirmed|min:5'
        ]);
        try {
            $c_password = Auth::user()->password;
            $c_id = Auth::user()->id;
            $user = User::findOrFail($c_id);
            if(Hash::check($request->old_password, $c_password)){
                $password = Hash::make($request->password);
                $user->password = $password;
                $user->save();
                session()->flash('message', 'Password Changes Successfully.');
                Session::flash('type', 'success');
                return redirect()->back();
            }else{
                session()->flash('message', 'Current Password Not Match');
                Session::flash('type', 'warning');
                return redirect()->back();
            }
        } catch (\PDOException $e) {
            session()->flash('message', $e->getMessage());
            Session::flash('type', 'warning');
            session()->flash('title','Opps');
            return redirect()->back();
        }

    }

    public function submitReview(Request $request)
    {

        $request->validate([
           'rating' => 'required',
            'comment' => 'required',
            'product_id' => 'required'
        ]);
        $user_id = Auth::user()->id;
        $reviewC = Review::whereUser_id($user_id)->whereProduct_id($request->product_id)->count();
        if ($reviewC == 0){
            $in = Input::except('_method','_token');
            $in['user_id'] = $user_id;
            Review::create($in);
            session()->flash('message','Review Submitted.');
            session()->flash('type','success');
            return redirect()->back();
        }else{
            session()->flash('message','Already Given Rating.');
            session()->flash('type','warning');
            return redirect()->back();
        }
    }

    public function submitComment(Request $request)
    {
        $request->validate([
            'comment' => 'required',
            'product_id' => 'required'
        ]);
        $user_id = Auth::user()->id;
        $in = Input::except('_method','_token');
        $in['user_id'] = $user_id;
        Comment::create($in);
        session()->flash('message','Comment Submitted.');
        session()->flash('type','success');
        return redirect()->back();

    }

    public function getCheckOut()
    {
        $data['page_title'] = "Check Out";
        $data['user'] = User::findOrFail(Auth::user()->id);
        $data['country'] = json_decode(file_get_contents('http://country.io/names.json'),true);
        $data['userDetails'] = UserDetails::whereUser_id(Auth::user()->id)->first();
        return view('home.checkout',$data);
    }

    public function submitDetails(Request $request)
    {

        $request->validate([
           's_name' => 'required',
           's_email' => 'required|email',
           's_number' => 'required|numeric',
           's_company' => 'required',
           's_country' => 'required',
           's_state' => 'required',
           's_city' => 'required',
           's_zip' => 'required',
           's_address' => 'required',
           's_landmark' => 'required',
           'b_name' => 'required',
           'b_email' => 'required|email',
           'b_number' => 'required|numeric',
           'b_company' => 'required',
           'b_country' => 'required',
           'b_state' => 'required',
           'b_city' => 'required',
           'b_zip' => 'required',
        ]);
        $in = Input::except('_method','_token');
        $in['user_id'] = Auth::user()->id;

        $userDetails = UserDetails::whereUser_id(Auth::user()->id)->first();
        if ($userDetails == null){
            UserDetails::create($in);
        }else{
            $userDetails->fill($in)->save();
        }
        session()->flash('message','Shipping Details Saved.');
        session()->flash('type','success');
        return redirect()->route('oder-overview');
    }

    public function orderOverview()
    {
        $data['page_title'] = 'Order Overview';
        $data['userDetails'] = UserDetails::whereUser_id(Auth::user()->id)->first();
        return view('home.order-overview',$data);
    }

    public function orderSubmit(Request $request)
    {
        if (Cart::count() == 0){
            session()->flash('message','Your Cart is Empty.');
            session()->flash('type','warning');
            return back();
        }else{
            $basic = BasicSetting::first();
            $order['user_id'] = Auth::user()->id;
            $order['order_number'] = strtoupper(Str::random(16));
            $order['subtotal'] = Cart::subtotal();
            $order['tax'] = Cart::tax();
            $order['total'] = Cart::total();
            $order['expire_time'] = Carbon::parse()->addMinutes($basic->expire_time);
            $r = Order::create($order);
            $cart = Cart::content();
            foreach ($cart as $c){
                $item['order_id'] = $r->id;
                $item['product_id'] = $c->id;
                $item['qty'] = $c->qty;
                OrderItem::create($item);
                $product = Product::findOrFail($c->id);
                $product->stock = $product->stock - $c->qty;
                $product->save();
            }
            $this->sendInvoice(Auth::user()->id,$r->id);
            session()->flash('message','Order Submitted Successfully.');
            session()->flash('type','success');
            return redirect()->route('payment',$r->order_number);
        }
    }
    public function getPayment($orderId)
    {
        $data['page_title'] = "Payment Page";
        $data['paymentMethod'] = PaymentMethod::whereStatus(1)->get();
        $data['order_id'] = $orderId;
        $data['orderDetails'] = Order::whereOrder_number($orderId)->first();
        Cart::destroy();
        return view('home.payment',$data);
    }

    public function getPaymentOverview($id, $orderNumber)
    {
        $data['page_title'] = "Payment Overview";
        $data['paymentMethod'] = PaymentMethod::findOrFail($id);
        $data['orderDetails'] = Order::whereOrder_number($orderNumber)->first();
        $order = PaymentLog::whereOrder_number($orderNumber)->first();
        $payment = PaymentMethod::findOrFail($id);
        $totalAmo = $data['orderDetails']->total;
        $charge = $payment->fix + round(($totalAmo*$payment->percent)/100,2);
        $usd = round(($totalAmo+$charge)/$payment->rate,2);
        if ($order == null){
            $payLog['user_id'] = Auth::user()->id;
            $payLog['order_number'] = $orderNumber;
            $payLog['payment_id'] = $id;
            $payLog['amount'] = $totalAmo;
            $payLog['charge'] = $charge;
            $payLog['net_amount'] = $totalAmo+$charge;
            $payLog['usd'] = $usd;
            if ($id == 3){

                try {
                    $blockchain_receive_root = "https://api.blockchain.info/";
                    $secret = "bitcoin_secret";
                    $my_xpub = $payment->val2;
                    $my_api_key = $payment->val1;
                    $invoice_id = $orderNumber;

                    $callback_url = route('btc_ipn',['invoice_id'=>$invoice_id,'secret'=>$secret]);

                    $url = $blockchain_receive_root."v2/receive?key=".$my_api_key."&callback=".urlencode($callback_url)."&xpub=".$my_xpub;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $resp = curl_exec($ch);
                    curl_close($ch);
                    $response = json_decode($resp);

                    /*$uu = $blockchain_receive_root."v2/receive?key=".$my_api_key."&callback=".urlencode($callback_url)."&xpub=".$my_xpub;
                    $resp = file_get_contents($uu);
                    $response = json_decode($resp);*/


                    $sendto = $response->address;
                    if ($sendto!="") {
                        $api = "https://blockchain.info/tobtc?currency=USD&value=".$usd;
                        $usd = file_get_contents($api);
                        $payLog['btc_amo'] = $usd;
                        $payLog['btc_acc'] = $sendto;
                        $var = "bitcoin:$sendto?amount=$usd";
                        $data['usd'] = $usd;
                        $data['add'] = $sendto;
                        $data['code'] =  "<img src=\"https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=$var&choe=UTF-8\" title='' style='width:300px;' />";
                    }else{
                        session()->flash('message', "SOME ISSUE WITH API");
                        Session::flash('type', 'warning');
                        return redirect()->back();
                    }
                } catch (\PDOException $e) {
                    session()->flash('message','Blockchain Something Error.');
                    session()->flash('type', 'warning');
                    return redirect()->back();
                }
            }
            $data['payment'] = PaymentLog::create($payLog);
        }else{
            $payLog['user_id'] = Auth::user()->id;
            $payLog['order_number'] = $orderNumber;
            $payLog['payment_id'] = $id;
            $payLog['amount'] = $totalAmo;
            $payLog['charge'] = $charge;
            $payLog['net_amount'] = $totalAmo+$charge;
            $payLog['usd'] = $usd;
            if ($id == 3){
                $blockchain_receive_root = "https://api.blockchain.info/";
                $secret = "bitcoin_secret";
                $my_xpub = $payment->val2;
                $my_api_key = $payment->val1;
                $invoice_id = $orderNumber;
                $callback_url = route('btc_ipn',['invoice_id'=>$invoice_id,'secret'=>$secret]);

                if ($order->btc_acc == null){

                    try {
                        $url = $blockchain_receive_root."v2/receive?key=".$my_api_key."&callback=".urlencode($callback_url)."&xpub=".$my_xpub;

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13");
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $resp = curl_exec($ch);
                        curl_close($ch);
                        
                        $response = json_decode($resp);

                        /*$uu = $blockchain_receive_root.'v2/receive?key='.$my_api_key.'&callback='.urlencode($callback_url).'&xpub='.$my_xpub;
                        $resp = file_get_contents($uu);
                        $response = json_decode($resp);*/

                        $sendto = $response->address;
                        if ($sendto!="") {
                            $api = "https://blockchain.info/tobtc?currency=USD&value=".$usd;
                            $usd = file_get_contents($api);
                            $order->btc_amo = $usd;
                            $order->btc_acc = $sendto;
                            $order->save();
                            $var = "bitcoin:$sendto?amount=$usd";
                            $data['usd'] = $usd;
                            $data['add'] = $sendto;
                            $data['code'] =  "<img src=\"https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=$var&choe=UTF-8\" title='' style='width:300px;' />";
                        }else{
                            session()->flash('message', "SOME ISSUE WITH API");
                            Session::flash('type', 'warning');
                            return redirect()->back();
                        }
                        
                    }catch (\PDOException $e) {
                        session()->flash('message','Blockchain Something Error.');
                        session()->flash('type', 'warning');
                        return redirect()->back();
                    }
                    
                }else{
                    $usd = $order->btc_amo;
                    $sendto = $order->btc_acc;
                    $var = "bitcoin:$sendto?amount=$usd";
                    $data['code'] =  "<img src=\"https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=$var&choe=UTF-8\" title='' style='width:300px;' />";
                }
            }
            $order->update($payLog);
            $data['payment'] = PaymentLog::whereOrder_number($orderNumber)->first();
        }
        return view('home.payment-overview',$data);
    }

    public function allOrder()
    {
        $data['page_title'] = "All Order";
        $data['order'] = Order::whereUser_id(Auth::user()->id)->latest()->paginate(10);
        return view('user.all-order',$data);
    }

    public function viewOrder($orderNumber)
    {
        $data['page_title'] = $orderNumber.' - Order Details';
        $data['order'] = Order::whereOrder_number($orderNumber)->first();
        $data['orderItem'] = OrderItem::whereOrder_id($data['order']->id)->get();
        $data['userDetails'] = UserDetails::whereUser_id($data['order']->user_id)->first();
        return view('user.order-view',$data);
    }

    public function addWishList()
    {
        $data['page_title'] = 'User Wishlist';
        $data['wishlist'] = Wishlist::whereUser_id(Auth::user()->id)->latest()->paginate(10);
        return view('user.wishlist',$data);
    }

}
