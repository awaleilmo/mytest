<?php

namespace App\Http\Controllers;

use App\Models\balance;
use App\Models\order;
use App\Models\product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Info(
 *    title="API For MyTest Application",
 *    version="1.0.0",
 * ),
 * @OAS\SecurityScheme(
    securityScheme="bearer_token",
    type="http",
    scheme="bearer"
    )
 */
class Controller extends BaseController
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function login(Request $request){
        $credentials = $request->only('email','password');

        if(Auth::attempt($credentials)){

            User::where("email", $request->email)->
            update(['remember_token' => csrf_token()]);

            $user = User::select("id","name","remember_token")->where("email",$request->email)->get();
            return response()->json(["message"=>"Success", "return"=>$user],201);
        }
        return response()->json(["message"=>"Failed"],401);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'nohp' =>  'required|numeric|unique:users',
            'password' => 'required',
            'c_password' => 'required|same:password',
        ]);
        if ($validator->fails()) {
            return response()->json(['message'=>$validator->errors()], 401);
        }
        $input = $request->all();
        $input['remember_token'] = csrf_token();
        $input['password'] = bcrypt($input['password']);
        $user = User::create($input)->get();
        $success['token'] =  csrf_token();
        $success['name'] =  $user->name;
        return response()->json(["message"=>"Success","return"=>$user],201);
    }

    public function singout(){
        $user = Auth::logout();
        return response()->json(['massage'=>'Success','data'=>$user],201);
    }

    public function postbalance(Request $request){
        $input['userid'] = Auth::id();
        $input['phone'] = $request->phone;
        $exp = balance::where('userid','=',$input['userid'])->count();
        if($exp <= 0){
            $exp = balance::create($input)->get();
        }
        if($exp){
            $add = $request->value*(5/100);
            $inputs['noorder'] = md5(uniqid(10,true));
            $inputs['value'] = $request->value;
            $inputs['userid'] = Auth::id();
            $inputs['total'] = ($request->value + $add );
            $inputs['payfor'] = '1';
            $inputs['status'] = 1;
            order::create($inputs);
            $exp = balance::where('userid','=',$input['userid'])->get();
            return response()->json(['massage'=>'Success','data'=>$exp],201);
        }
        return response()->json(['message'=>'Failed'], 401);
    }

    public function postProduct(Request $request){
        $generate = md5(uniqid(10,true));
        $input['userid'] = Auth::id();
        $input['name'] = $request->name;
        $input['price'] = $request->price;
        $input['address'] = $request->address;
        $input['noorder'] = $generate;
        $exp = product::create($input)->get();
        if($exp){
            $inputs['userid'] = Auth::id();
            $inputs['value'] = $request->price;
            $inputs['total'] = ($request->price + 10000 );
            $inputs['noorder'] = $input['noorder'];
            $inputs['payfor'] = '2';
            $inputs['status'] = 1;
            order::create($inputs);
            return response()->json(['massage'=>'Success','data'=>$exp],201);
        }
        return response()->json(['message'=>'Failed'], 401);
    }

    public function detailorder(Request $request){
        $input['noorder'] = $request->noorder;
        $input['payfor'] = $request->payfor;
        if($input['payfor'] == '1'){
            $order = DB::table('orders')
                ->leftJoin('balances','orders.userid','=','balances.userid')
                ->select('orders.total', 'orders.value', 'balances.phone', 'orders.noorder')
                ->where('orders.noorder','=',$input['noorder'])->get();
            if($order){
                return response()->json(['massage'=>'Your mobile phone number '.$order[0]->phone.' will receive Rp '.$order[0]->value.'',
                    'data'=>$order[0]],201);
            }
            return response()->json(['message'=>'Failed'], 401);
        }else{
            $order = DB::table('orders')
                ->leftJoin('products','orders.noorder','=','products.noorder')
                ->select('products.name', 'products.address', 'products.price', 'orders.total')
                ->where('orders.noorder','=',$input['noorder'])->get();
            if($order){
                return response()->json([
                    'massage'=>$order[0]->name.' that costs '.$order[0]->price.' will be shipped to : \n '.$order[0]->address.' \n only after you pay',
                    'data'=>$order[0]],201);
            }
            return response()->json(['message'=>'Failed'], 401);
        }
    }

    public function payorder(Request $request){
        $input['noorder'] = $request->noorder;
        $input['payfor'] = $request->payfor;
        $jam = Carbon::now()->isoFormat("HHmm");

        // status 1 = pay , 2 = failed, 3 = shipping/delivery, 4 = success, 5 = canceled
        $input['status'] = 2;
        if($jam >= '0900' && $jam <= '1500'){
            $input['status'] = 4;
            if($input['payfor'] == '2'){
                $input['shipping'] = md5(uniqid(5,true));
            }
        }
        $exp = order::where('noorder','=',$input['noorder'])->update($input);
        if($exp){
            $expox = order::where('noorder','=',$input['noorder'])->get();
            if($input['payfor'] == '1'){
                $mak = balance::select('value')->where('userid','=',$expox[0]->userid)->get();
                balance::select('value')->where('userid','=',$expox[0]->userid)->update(['value' => $mak[0]->value + $expox[0]->value]);
            }

            return response()->json(['massage'=>'Success','data'=>$expox],201);
        }
        return response()->json(['message'=>'Failed'], 401);
    }

    public function historyorder(Request $request)
    {
        $noorder = $request->noorder;
        $exp = DB::table('orders')
            ->leftJoin('balances','orders.userid','=','balances.userid')
            ->leftJoin('products','orders.noorder','=','products.noorder')
            ->select('orders.shipping','orders.payfor','orders.noorder','balances.phone','orders.value','orders.total','products.name','products.price')
            ->where('orders.noorder','like','%'.$noorder.'%')
            ->orderBy('orders.created_at','desc')->paginate(20);
        // status 1 = pay , 2 = failed, 3 = shipping/delivery, 4 = success, 5 = canceled
        if($exp){
            //$product = product::where('noorder','=',$input['noorder'])->get();
            //$balanced = balance::where('userid','=',$userid)->get()
            return response()->json(['massage'=>'Success','data'=>$exp],201);
        }
        return response()->json(['message'=>'Failed'], 401);
    }


}
