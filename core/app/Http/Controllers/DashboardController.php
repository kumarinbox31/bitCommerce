<?php

namespace App\Http\Controllers;

use App\Admin;
use App\Advertisement;
use App\Category;
use App\ChildCategory;
use App\Comment;
use App\Order;
use App\OrderItem;
use App\PaymentLog;
use App\PaymentMethod;
use App\Product;
use App\ProductImage;
use App\ProductSpecification;
use App\Review;
use App\Staff;
use App\Subcategory;
use App\Subscribe;
use App\User;
use App\UserDetails;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Input;
use Intervention\Image\Facades\Image;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }
    public function getDashboard()
    {
        $data['page_title'] = "Dashboard";
        $data['totalProduct'] = Product::all()->count();
        $data['totalCategory'] = Category::all()->count();
        $data['totalSubCategory'] = Subcategory::all()->count();
        $data['totalChildCategory'] = ChildCategory::all()->count();
        $data['totalOrder'] = Order::all()->count();
        $data['pendingOrder'] = Order::whereStatus(0)->count();
        $data['cancelOrder'] = Order::whereStatus(2)->count();
        $data['confirmOrder'] = Order::whereStatus(1)->count();

        $start = Carbon::parse()->subDays(19);
        $end = Carbon::now();
        $stack = [];
        $date = $start;
        while ($date <= $end) {
            $stack[] = $date->copy();
            $date->addDays(1);
        }
        $dL = [];
        $dV = [];
        foreach (array_reverse($stack) as $d){
            $dL[] .= Carbon::parse($d)->format('dS M');

        }
        foreach (array_reverse($stack) as $d){
            $date = Carbon::parse($d)->format('Y-m-d');
            $start = $date.' '.'00:00:00';
            $end = $date.' '.'23:59:59';
            $dC= Order::whereBetween('created_at',[$start,$end])->get();
            $dV[] .= count($dC);
        }
        $data['dV'] = $dV;
        $data['dL'] = $dL;
        return view('dashboard.dashboard',$data);
    }
    public function manageCategory()
    {
        $data['page_title'] = "Product Category";
        $data['category'] = Category::all();
        return view('dashboard.category', $data);
    }
    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:categories,name',
        ]);
        $product = Category::create($request->input());
        $product_id = $product->id;
        $product['menuStatus'] = '<button type="button" class="btn btn-sm btn-warning bold uppercase delete_button"
                                                data-toggle="modal" data-target="#DelModal"
                                                data-id="'.$product_id.'">
                                            <i class=\'fa fa-times\'></i> NO
                                        </button>';
        return response()->json($product);

    }
    public function editCategory($product_id)
    {
        $product = Category::find($product_id);
        return response()->json($product);
    }
    public function updateCategory(Request $request,$product_id)
    {
        $product = Category::find($product_id);
        $request->validate([
            'name' => 'required|unique:categories,name,'.$product->id,
        ]);
        $product->name = $request->name;
        $product->save();
        if ($product->status == 0){
            $product['menuStatus'] = '<button type="button" class="btn btn-sm btn-warning bold uppercase delete_button"
                                                data-toggle="modal" data-target="#DelModal"
                                                data-id="'.$product_id.'">
                                            <i class=\'fa fa-times\'></i> NO
                                        </button>';
        }else{
            $product['menuStatus'] = '<button type="button" class="btn btn-sm btn-primary bold uppercase delete_button"
                                                data-toggle="modal" data-target="#DelModal"
                                                data-id="'.$product_id.'">
                                            <i class=\'fa fa-check\'></i> YES
                                        </button>';
        }
        return response()->json($product);
    }
    public function categoryStatus(Request $request)
    {
        $request->validate([
            'id' => 'required'
        ]);
        $channel = Category::findOrFail($request->id);
        if ($channel->status == 1){
            $channel->status = 0;
            $channel->save();
        }else{
            $channel->status = 1;
            $channel->save();
        }
        session()->flash('message','Category Action Completed.');
        session()->flash('type','success');
        return redirect()->back();
    }
    public function manageSubCategory()
    {
        $data['page_title'] = "Product Subcategory";
        $data['category'] = Category::all();
        $data['subcategory'] = Subcategory::all();
        return view('dashboard.subcategory', $data);
    }
    public function storeSubCategory(Request $request)
    {
        $request->validate([
            'category_id' => 'required',
            'name' => 'required|unique:subcategories,name,NULL,id,category_id,'.$request->category_id,
        ]);
        $product = Subcategory::create($request->input());
        $product['categoryName'] = Category::findOrFail($request->category_id)->name;
        return response()->json($product);

    }
    public function editSubCategory($product_id)
    {
        $product = Subcategory::find($product_id);
        return response()->json($product);
    }
    public function updateSubCategory(Request $request,$product_id)
    {
        $product = Subcategory::find($product_id);
        $request->validate([
            'name' => 'required|unique:subcategories,name,NULL,id,category_id,'.$request->category_id.$product->id,
            'category_id' => 'required'
        ]);
        $product->name = $request->name;
        $product->category_id = $request->category_id;
        $product->save();
        $product['categoryName'] = Category::findOrFail($request->category_id)->name;
        return response()->json($product);
    }

    public function manageChildCategory()
    {
        $data['page_title'] = "Product Child Category";
        $data['subcategory'] = Subcategory::all();
        $data['childcategory'] = ChildCategory::orderBy('id','desc')->get();
        return view('dashboard.childcategory', $data);
    }
    public function storeChildCategory(Request $request)
    {
        $request->validate([
            'subcategory_id' => 'required',
            'name' => 'required|unique:child_categories,name,NULL,id,subcategory_id,'.$request->subcategory_id,
        ]);
        $product = ChildCategory::create($request->input());
        $product['subcategoryName'] = Subcategory::findOrFail($request->subcategory_id)->name;
        $product['categoryName'] = Category::findOrFail($request->subcategory_id)->name;
        return response()->json($product);

    }
    public function editChildCategory($product_id)
    {
        $product = ChildCategory::find($product_id);
        return response()->json($product);
    }
    public function updateChildCategory(Request $request,$product_id)
    {
        $product = ChildCategory::find($product_id);
        $request->validate([
            'name' => 'required|unique:child_categories,name,NULL,id,subcategory_id,'.$request->subcategory_id.$product->id,
            'subcategory_id' => 'required'
        ]);
        $product->name = $request->name;
        $product->subcategory_id = $request->subcategory_id;
        $product->save();
        $product['subcategoryName'] = Subcategory::findOrFail($request->subcategory_id)->name;
        $product['categoryName'] = Category::findOrFail($request->subcategory_id)->name;
        return response()->json($product);
    }

    public function newAdvertisement()
    {
        $data['page_title'] = "New Advertisement";
        return view('dashboard.advertisement-new', $data);
    }
    public function storeAdvertisement(Request $request)
    {
        $this->validate($request,[
            'advert_type' => 'required',
            'advert_size' => 'required',
            'title' => 'required',
            'val1' => 'mimes:png,jpg,jpeg,gif'
        ]);
        $in  = Input::except('_method','_token');
        $in['status'] = $request->status == 'on' ? '1' : '0';
        if($request->hasFile('val1')){
            $image = $request->file('val1');
            $filename = 'advertise_'.time().'.'.$image->getClientOriginalExtension();
            $location = 'assets/images/advertise/' . $filename;
            Image::make($image)->save($location);
            $in['val1'] = $filename;
        }
        Advertisement::create($in);
        session()->flash('message','Advertisement Added Successfully.');
        session()->flash('title','Success');
        session()->flash('type','success');
        return redirect()->back();
    }
    public function allAdvertisement()
    {
        $data['page_title'] = "All Advertisement";
        $data['advert'] = Advertisement::orderBy('id','desc')->get();
        return view('dashboard.advertisement-all', $data);
    }
    public function editAdvertisement($id)
    {
        $data['page_title'] = "Edit Advertisement";
        $data['advert'] = Advertisement::findOrFail($id);
        return view('dashboard.advertisement-edit', $data);
    }
    public function updateAdvertisement(Request $request,$id)
    {
        $ad = Advertisement::findOrFail($id);
        $this->validate($request,[
            'advert_size' => 'required',
            'title' => 'required',
            'val1' => 'mimes:png,jpg,jpeg,gif'
        ]);
        $in  = Input::except('_method','_token');
        $in['status'] = $request->status == 'on' ? '1' : '0';
        if($request->hasFile('val1')){
            $image = $request->file('val1');
            $filename = 'advertise_'.time().'.'.$image->getClientOriginalExtension();
            $location = 'assets/images/advertise/' . $filename;
            Image::make($image)->save($location);
            $in['val1'] = $filename;
            $path = './assets/images/advertise/';
            $link = $path.$ad->val1;
            if (file_exists($link)){
                unlink($link);
            }
        }
        $ad->fill($in)->save();
        session()->flash('message','Advertisement Updated Successfully.');
        session()->flash('title','Success');
        session()->flash('type','success');
        return redirect()->back();
    }

    public function mangeSubscribe()
    {
        $data['page_title'] = "All Subscribe";
        $data['subscribe'] = Subscribe::orderBy('id','desc')->get();
        return view('dashboard.subscribe',$data);
    }

    public function deleteSubscribe(Request $request)
    {
        $request->validate([
           'id' => 'required'
        ]);
        Subscribe::destroy($request->id);
        session()->flash('message','Subscriber Delete Successfully.');
        session()->flash('type','success');
        return redirect()->back();
    }

    public function mangeUsers()
    {
        $data['page_title'] = "All Users";
        $data['users'] = User::orderBy('id','desc')->get();
        return view('dashboard.users',$data);
    }
    public function deleteUser(Request $request)
    {
        $request->validate([
            'id' => 'required'
        ]);
        $user = User::findOrFail($request->id);
        Review::whereUser_id($request->id)->delete();
        Comment::whereUser_id($request->id)->delete();
        UserDetails::whereUser_id($request->id)->delete();
        Order::whereUser_id($request->id)->delete();
        PaymentLog::whereUser_id($request->id)->delete();
        $path = './assets/images/user/'.$user->image;
        if ($user->image != 'user-default.png'){
            File::delete($path);
        }
        $user->delete();
        session()->flash('message','User Delete Successfully.');
        session()->flash('type','success');
        return redirect()->back();
    }

    public function paymentMethod()
    {
        $data['page_title'] = 'Payment Method';
        $data['paypal'] = PaymentMethod::whereId(1)->first();
        $data['perfect'] = PaymentMethod::whereId(2)->first();
        $data['btc'] = PaymentMethod::whereId(3)->first();
        $data['stripe'] = PaymentMethod::whereId(4)->first();
        $data['skrill'] = PaymentMethod::whereId(5)->first();
        $data['payza'] = PaymentMethod::whereId(6)->first();
        return view('dashboard.payment-method',$data);
    }
    public function updatePaymentMethod(Request $request)
    {
        $this->validate($request,[
            'paypal_name' => 'required',
            'paypal_image' => 'mimes:png,jpeg,jpg',
            'paypal_email' => 'required',
            'perfect_name' => 'required',
            'perfect_image' => 'mimes:png,jpeg,jpg',
            'perfect_account' => 'required',
            'perfect_alternate' => 'required',
            'btc_name' => 'required',
            'btc_image' => 'mimes:png,jpeg,jpg',
            'btc_api' => 'required',
            'btc_xpub' => 'required',
            'stripe_name' => 'required',
            'stripe_image' => 'mimes:png,jpeg,jpg',
            'stripe_secret' => 'required',
            'stripe_publishable' => 'required',
            'skrill_name' => 'required',
            'skrill_image' => 'mimes:png,jpeg,jpg',
            'skrill_email' => 'required',
            'skrill_secret' => 'required',
            'payza_name' => 'required',
            'payza_image' => 'mimes:png,jpeg,jpg',
            'payza_email' => 'required',
        ]);

        $paypal = PaymentMethod::whereId(1)->first();
        $perfect = PaymentMethod::whereId(2)->first();
        $btc = PaymentMethod::whereId(3)->first();
        $stripe = PaymentMethod::whereId(4)->first();
        $skrill = PaymentMethod::whereId(5)->first();
        $payza = PaymentMethod::whereId(6)->first();

        $paypal->name = $request->paypal_name;
        $paypal->val1 = $request->paypal_email;
        $paypal->status = $request->paypal_status == 'on' ? '1' : '0';
        $paypal->fix = $request->paypal_fix;
        $paypal->rate = $request->paypal_rate;
        $paypal->percent = $request->paypal_percent;
        if($request->hasFile('paypal_image')){
            $image3 = $request->file('paypal_image');
            $filename3 = 'paypal_'.time().'h3'.'.'.$image3->getClientOriginalExtension();
            $location = 'assets/images/payment/' . $filename3;
            Image::make($image3)->resize(290,190)->save($location);
            $path = './assets/images/payment/';
            $link = $path.$paypal->image;
            if (file_exists($link)){
                unlink($link);
            }
            $paypal->image = $filename3;
        }
        $perfect->name = $request->perfect_name;
        $perfect->val1 = $request->perfect_account;
        $perfect->val2 = $request->perfect_alternate;
        $perfect->status = $request->perfect_status == 'on' ? '1' : '0';
        $perfect->fix = $request->perfect_fix;
        $perfect->rate = $request->perfect_rate;
        $perfect->percent = $request->perfect_percent;
        if($request->hasFile('perfect_image')){
            $image3 = $request->file('perfect_image');
            $filename3 = 'perfect_'.time().'h4'.'.'.$image3->getClientOriginalExtension();
            $location = 'assets/images/payment/' . $filename3;
            Image::make($image3)->resize(290,190)->save($location);
            $path = './assets/images/payment/';
            $link = $path.$perfect->image;
            if (file_exists($link)){
                unlink($link);
            }
            $perfect->image = $filename3;
        }
        $btc->name = $request->btc_name;
        $btc->val1 = $request->btc_api;
        $btc->val2 = $request->btc_xpub;
        $btc->status = $request->btc_status == 'on' ? '1' : '0';
        $btc->fix = $request->btc_fix;
        $btc->rate = $request->btc_rate;
        $btc->percent = $request->btc_percent;
        if($request->hasFile('btc_image')){
            $image3 = $request->file('btc_image');
            $filename3 = 'btc_'.time().'h5'.'.'.$image3->getClientOriginalExtension();
            $location = 'assets/images/payment/' . $filename3;
            Image::make($image3)->resize(290,190)->save($location);
            $path = './assets/images/payment/';
            $link = $path.$btc->image;
            if (file_exists($link)){
                unlink($link);
            }
            $btc->image = $filename3;
        }
        $stripe->name = $request->stripe_name;
        $stripe->val1 = $request->stripe_secret;
        $stripe->val2 = $request->stripe_publishable;
        $stripe->status = $request->stripe_status == 'on' ? '1' : '0';
        $stripe->fix = $request->stripe_fix;
        $stripe->rate = $request->stripe_rate;
        $stripe->percent = $request->stripe_percent;
        if($request->hasFile('stripe_image')){
            $image3 = $request->file('stripe_image');
            $filename3 = 'stripe_'.time().'h6'.'.'.$image3->getClientOriginalExtension();
            $location = 'assets/images/payment/' . $filename3;
            Image::make($image3)->resize(290,190)->save($location);
            $path = './assets/images/payment/';
            $link = $path.$stripe->image;
            if (file_exists($link)){
                unlink($link);
            }
            $stripe->image = $filename3;
        }
        $skrill->name = $request->skrill_name;
        $skrill->val1 = $request->skrill_email;
        $skrill->val2 = $request->skrill_secret;
        $skrill->status = $request->skrill_status == 'on' ? '1' : '0';
        $skrill->fix = $request->skrill_fix;
        $skrill->rate = $request->skrill_rate;
        $skrill->percent = $request->skrill_percent;
        if($request->hasFile('skrill_image')){
            $image3 = $request->file('skrill_image');
            $filename3 = 'skrill_'.time().'h3'.'.'.$image3->getClientOriginalExtension();
            $location = 'assets/images/payment/' . $filename3;
            Image::make($image3)->resize(290,190)->save($location);
            $path = './assets/images/payment/';
            $link = $path.$skrill->image;
            if (file_exists($link)){
                unlink($link);
            }
            $skrill->image = $filename3;
        }
        $payza->name = $request->payza_name;
        $payza->val1 = $request->payza_email;
        $payza->status = $request->payza_status == 'on' ? '1' : '0';
        $payza->fix = $request->payza_fix;
        $payza->rate = $request->payza_rate;
        $payza->percent = $request->payza_percent;
        if($request->hasFile('payza_image')){
            $image3 = $request->file('payza_image');
            $filename3 = 'payza_'.time().'h3'.'.'.$image3->getClientOriginalExtension();
            $location = 'assets/images/payment/' . $filename3;
            Image::make($image3)->resize(290,190)->save($location);
            $path = './assets/images/payment/';
            $link = $path.$payza->image;
            if (file_exists($link)){
                unlink($link);
            }
            $payza->image = $filename3;
        }

        $paypal->save();
        $perfect->save();
        $btc->save();
        $stripe->save();
        $skrill->save();
        $payza->save();
        session()->flash('message', 'Payment Method Updated Successfully.');
        session()->flash('type', 'success');
        return redirect()->back();
    }

    public function addProduct()
    {
        $data['page_title'] = "Add New Product";
        $data['category'] = Category::all();
        return view('dashboard.product-create',$data);
    }

    public function storeProduct(Request $request)
    {
        $request->validate([
            'category_id' => 'required',
            'subcategory_id' => 'required',
            'name' => 'required|unique:products,name',
            'sku' => 'required',
            'image' => 'required|mimes:png,jpg,jpeg',
            'current_price' => 'required|numeric',
            'old_price' => 'nullable|numeric',
            'stock' => 'required|numeric',
            'description' => 'required',
            'tags' => 'required',
            "gallery_image.*" => 'required|mimes:png,jpg,jpeg,gif',
        ]);

        $in = Input::except('_method','_token','specification','gallery_image');
        $in['slug'] = str_slug($request->name);
        $in['status'] = $request->status == 'on' ? '1' : '0';
        if($request->hasFile('image')){
            $image3 = $request->file('image');
            $filename3 = $in['slug'].'.'.$image3->getClientOriginalExtension();
            $location = 'assets/images/product/' . $filename3;
            Image::make($image3)->resize(780,1000)->save($location);
            $in['image'] = $filename3;
        }
        $product = Product::create($in);
        if($request->hasFile('gallery_image')){
            $image4 = $request->file('gallery_image');
            foreach ($image4 as $key => $i)
            {
                $filename4 = $product->slug.'_'.$key.'.'.$i->getClientOriginalExtension();
                $location = 'assets/images/product/' . $filename4;
                Image::make($i)->resize(780,1000)->save($location);
                $image['name'] = $filename4;
                $image['product_id'] = $product->id;
                ProductImage::create($image);
            }
        }
        if ($request->specification != null){
            foreach ($request->specification as $des){
                if (!empty($des)){
                    $pp['product_id'] = $product->id;
                    $pp['specification'] = trim($des);
                    ProductSpecification::create($pp);
                }
            }
        }
        session()->flash('message','Product Store Successfully Completed.');
        session()->flash('type','success');
        return redirect()->back();
    }

    public function allProduct()
    {
        $data['page_title'] = "All Product";
        $data['products'] = Product::orderBy('id','desc')->get();
        return view('dashboard.product-all',$data);
    }
    public function featuredProduct(Request $request)
    {
        $request->validate([
            'id' => 'required'
        ]);
        $channel = Product::findOrFail($request->id);
        if ($channel->featured == 1){
            $channel->featured = 0;
            $channel->save();
        }else{
            $channel->featured = 1;
            $channel->save();
        }
        session()->flash('message','Product Featured Action Completed.');
        session()->flash('type','success');
        return redirect()->back();
    }
    public function editProduct($id)
    {
        $data['page_title'] = 'Edit Product';
        $data['product'] = Product::findOrFail($id);
        $data['category'] = Category::get();
        $data['subcategory'] = Subcategory::whereCategory_id($data['product']->category_id)->get();
        $data['childcategory'] = ChildCategory::wheresubcategory_id($data['product']->subcategory_id)->get();
        $data['productImage'] = ProductImage::whereProduct_id($id)->get();
        $data['productSpecification'] = ProductSpecification::whereProduct_id($id)->get();
        return view('dashboard.product-edit',$data);
    }

    public function updateProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $request->validate([
            'category_id' => 'required',
            'subcategory_id' => 'required',
            'name' => 'required|unique:products,name,'.$product->id,
            'sku' => 'required',
            'image' => 'mimes:png,jpg,jpeg',
            'current_price' => 'required|numeric',
            'old_price' => 'nullable|numeric',
            'stock' => 'required|numeric',
            'description' => 'required',
            'tags' => 'required',
            "gallery_image.*" => 'mimes:png,jpg,jpeg,gif',
        ]);

        $in = Input::except('_method','_token','specification','gallery_image');
        $in['slug'] = str_slug($request->name);
        $in['status'] = $request->status == 'on' ? '1' : '0';
        if($request->hasFile('image')){
            $image3 = $request->file('image');
            $filename3 = $in['slug'].'.'.$image3->getClientOriginalExtension();
            $location = 'assets/images/product/' . $filename3;
            $path = './assets/images/product/';
            $link = $path.$product->image;
            if (file_exists($link)){
                unlink($link);
            }
            Image::make($image3)->resize(780,1000)->save($location);
            $in['image'] = $filename3;
        }

        if($request->hasFile('gallery_image')){
            $oldGalleryImage = ProductImage::whereProduct_id($id)->get();
            foreach ($oldGalleryImage as $oldImage){
                $path = './assets/images/product/';
                $link = $path.$oldImage->name;
                if (file_exists($link)){
                    unlink($link);
                }
                $oldImage->delete();
            }
            $image4 = $request->file('gallery_image');
            foreach ($image4 as $key => $i)
            {
                $filename4 = $product->slug.'_'.$key.'.'.$i->getClientOriginalExtension();
                $location = 'assets/images/product/' . $filename4;
                Image::make($i)->resize(780,1000)->save($location);
                $image['name'] = $filename4;
                $image['product_id'] = $product->id;
                ProductImage::create($image);
            }
        }
        if ($request->specification != null){
            ProductSpecification::whereProduct_id($id)->delete();
            foreach ($request->specification as $des){
                if (!empty($des)){
                    $pp['product_id'] = $product->id;
                    $pp['specification'] = trim($des);
                    ProductSpecification::create($pp);
                }
            }
        }

        $product->fill($in)->save();
        session()->flash('message','Product Updated Successfully.');
        session()->flash('type','success');
        return redirect()->back();
    }

    public function allOrder()
    {
        $data['page_title'] = "All Order";
        $data['order'] = Order::orderBy('id','desc')->get();
        return view('dashboard.order-all',$data);
    }
    public function pendingOrder()
    {
        $data['page_title'] = "Pending Order";
        $data['order'] = Order::whereStatus(0)->orderBy('id','desc')->get();
        return view('dashboard.order-all',$data);
    }
    public function confirmOrder()
    {
        $data['page_title'] = "Confirm Order";
        $data['order'] = Order::whereStatus(1)->orderBy('id','desc')->get();
        return view('dashboard.order-all',$data);
    }
    public function cancelOrder()
    {
        $data['page_title'] = "Cancel Order";
        $data['order'] = Order::whereStatus(2)->orderBy('id','desc')->get();
        return view('dashboard.order-all',$data);
    }

    public function viewOrder($id)
    {
        $data['page_title'] = $id." - Order Details";
        $data['order'] = Order::whereOrder_number($id)->first();
        $data['orderItem'] = OrderItem::whereOrder_id($data['order']->id)->get();
        $data['userDetails'] = UserDetails::whereUser_id($data['order']->user_id)->first();
        return view('dashboard.order-view',$data);
    }

    public function updateShippingStatus(Request $request)
    {
        $request->validate([
           'status' => 'required',
            'shipping_id' => 'required'
        ]);
        $order = Order::findOrFail($request->shipping_id);
        $order->shipping_status = $request->status;
        $order->save();
        session()->flash('message','Shipping Status Updated.');
        session()->flash('type','success');
        return redirect()->route('order-view',$order->order_number);
    }

    public function updateOrderStatus(Request $request)
    {
        $request->validate([
            'status1' => 'required',
            'status_id' => 'required'
        ]);
        $order = Order::findOrFail($request->status_id);
        $order->status = $request->status1;
        $order->save();
        session()->flash('message','Order Status Updated.');
        session()->flash('type','success');
        return redirect()->route('order-view',$order->order_number);
    }

    public function manageStaff()
    {
        $data['page_title'] = 'Manage Staff';
        $data['staff'] = Staff::all();
        return view('dashboard.staff',$data);
    }
    public function storeStaff(Request $request)
    {
        $request->validate([
            'email' => 'required|unique:staff',
            'name' => 'required',
            'password' => 'required|confirmed|min:5'
        ]);
        $in = Input::except('_method','_token');
        $in['password'] = bcrypt($request->password);
        $product = Staff::create($in);
        return response()->json($product);

    }
    public function editStaff($product_id)
    {
        $product = Staff::find($product_id);
        return response()->json($product);
    }
    public function updateStaff(Request $request,$product_id)
    {
        $product = Staff::find($product_id);
        $request->validate([
            'email' => 'required|unique:staff,email,'.$product->id,
            'name' => 'required',
            'password' => 'nullable|confirmed|min:5'
        ]);
        $product->name = $request->name;
        $product->email = $request->email;
        if ($request->password != null){
            $product->password = bcrypt($request->password);
        }
        $product->save();
        return response()->json($product);
    }

}
