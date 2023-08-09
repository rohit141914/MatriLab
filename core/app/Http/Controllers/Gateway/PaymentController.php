<?php

namespace App\Http\Controllers\Gateway;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Models\AdminNotification;
use App\Models\Deposit;
use App\Models\GatewayCurrency;
use App\Models\Package;
use App\Models\PurchaseHistory;
use App\Models\User;
use App\Models\UserLimitation;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function purchase(Request $request, $id)
    {
        $request->validate([
            'method_code' => 'required',
            'currency' => 'required'
        ]);

        $package = Package::active()->findOrFail($id);

        $user = auth()->user();
        $gate = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE);
        })->where('method_code', $request->method_code)->where('currency', $request->currency)->first();

        if (!$gate) {
            $notify[] = ['error', 'Invalid gateway'];
            return back()->withNotify($notify);
        }

        if ($gate->min_amount > $package->price || $gate->max_amount < $package->price) {
            $notify[] = ['error', 'Please follow payment limit'];
            return back()->withNotify($notify);
        }

        $purchaseHistory                  = new PurchaseHistory();
        $purchaseHistory->user_id         = $user->id;
        $purchaseHistory->package_id      = $package->id;
        $purchaseHistory->package_details = collect($package)->except(['id', 'status', 'created_at', 'updated_at']);
        $purchaseHistory->status          = Status::PAYMENT_INITIATE;
        $purchaseHistory->save();

        $charge    = $gate->fixed_charge + ($package->price * $gate->percent_charge / 100);
        $payable   = $package->price + $charge;
        $final_amo = $payable * $gate->rate;

        $deposit                  = new Deposit();
        $deposit->user_id         = $user->id;
        $deposit->purchase_id     = $purchaseHistory->id;
        $deposit->method_code     = $gate->method_code;
        $deposit->method_currency = strtoupper($gate->currency);
        $deposit->amount          = $package->price;
        $deposit->charge          = $charge;
        $deposit->rate            = $gate->rate;
        $deposit->final_amo       = $final_amo;
        $deposit->btc_amo         = 0;
        $deposit->btc_wallet      = "";
        $deposit->trx             = getTrx();
        $deposit->payment_try     = 0;
        $deposit->status          = 0;
        $deposit->save();


        session()->put('Track', $deposit->trx);
        return to_route('user.payment.confirm');
    }
    public function appDepositConfirm($hash)
    {
        try {
            $id = decrypt($hash);
        } catch (\Exception $ex) {
            return "Sorry, invalid URL.";
        }
        $data = Deposit::where('id', $id)->where('status', Status::PAYMENT_INITIATE)->orderBy('id', 'DESC')->firstOrFail();
        $user = User::findOrFail($data->user_id);
        auth()->login($user);
        session()->put('Track', $data->trx);
        return to_route('user.payment.confirm');
    }

    public function depositConfirm()
    {
        $track = session()->get('Track');
        $deposit = Deposit::where('trx', $track)->where('status', Status::PAYMENT_INITIATE)->orderBy('id', 'DESC')->with('gateway')->firstOrFail();

        if ($deposit->method_code >= 1000) {
            return to_route('user.payment.manual.confirm');
        }


        $dirName = $deposit->gateway->alias;
        $new = __NAMESPACE__ . '\\' . $dirName . '\\ProcessController';

        $data = $new::process($deposit);
        $data = json_decode($data);


        if (isset($data->error)) {
            $notify[] = ['error', $data->message];
            return to_route(gatewayRedirectUrl())->withNotify($notify);
        }
        if (isset($data->redirect)) {
            return redirect($data->redirect_url);
        }

        // for Stripe V3
        if (@$data->session) {
            $deposit->btc_wallet = $data->session->id;
            $deposit->save();
        }

        $pageTitle = 'Payment Confirm';
        return view($this->activeTemplate . $data->view, compact('data', 'pageTitle', 'deposit'));
    }
    public static function userDataUpdate($deposit, $isManual = null)
    {
        if ($deposit->status == Status::PAYMENT_INITIATE || $deposit->status == Status::PAYMENT_PENDING) {
            $deposit->status = Status::PAYMENT_SUCCESS;
            $deposit->save();

            $user = User::find($deposit->user_id);

            $purchaseHistory         = $deposit->purchaseHistory;
            $purchaseHistory->status = Status::PAYMENT_SUCCESS;
            $purchaseHistory->save();

            $limitation = UserLimitation::where('user_id', $user->id)->first();
            if (!$limitation) {
                $limitation = new UserLimitation();
                $limitation->user_id = $user->id;
            }
            $limitation->package_id   = $purchaseHistory->package_id;
            $planInterestExpressLimit = $purchaseHistory->package_details->interest_express_limit;
            $planContactViewLimit     = $purchaseHistory->package_details->contact_view_limit;
            if ($limitation->expire_date >= Carbon::now()->format('Y-m-d')) {
                $interestExpressLimitCarry          = ($limitation->interest_express_limit != -1) ? $limitation->interest_express_limit : 0;
                $limitation->interest_express_limit = ($planInterestExpressLimit != -1) ? $interestExpressLimitCarry + $planInterestExpressLimit : -1;

                $contactViewLimitCarry          = ($limitation->contact_view_limit != -1) ? $limitation->contact_view_limit : 0;
                $limitation->contact_view_limit = ($planContactViewLimit != -1) ? $contactViewLimitCarry + $planContactViewLimit : -1;
            } else {
                $limitation->interest_express_limit = $planInterestExpressLimit;
                $limitation->contact_view_limit     = $planContactViewLimit;
            }

            $limitation->image_upload_limit = $purchaseHistory->package_details->image_upload_limit;
            $limitation->validity_period    = $purchaseHistory->package_details->validity_period;
            $limitation->expire_date        = Carbon::now()->addDays($purchaseHistory->package_details->validity_period);
            $limitation->save();

            if (!$isManual) {
                $adminNotification = new AdminNotification();
                $adminNotification->user_id = $user->id;
                $adminNotification->title = 'Payment successful via ' . $deposit->gatewayCurrency()->name;
                $adminNotification->click_url = urlPath('admin.deposit.successful');
                $adminNotification->save();
            }

            notify($user, $isManual ? 'PAYMENT_APPROVE' : 'PAYMENT_COMPLETE', [
                'method_name' => $deposit->gatewayCurrency()->name,
                'package' => $purchaseHistory->package_details->name,
                'method_currency' => $deposit->method_currency,
                'method_amount' => showAmount($deposit->final_amo),
                'amount' => showAmount($deposit->amount),
                'charge' => showAmount($deposit->charge)
            ]);
        }
    }

    public function manualDepositConfirm()
    {
        $track = session()->get('Track');
        $data = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();
        if (!$data) {
            return to_route(gatewayRedirectUrl());
        }
        if ($data->method_code > 999) {

            $pageTitle = 'Payment Confirm';
            $method = $data->gatewayCurrency();
            $gateway = $method->method;
            return view($this->activeTemplate . 'user.payment.manual', compact('data', 'pageTitle', 'method', 'gateway'));
        }
        abort(404);
    }

    public function manualDepositUpdate(Request $request)
    {
        $track = session()->get('Track');
        $data = Deposit::with('gateway', 'purchaseHistory')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();
        if (!$data) {
            return to_route(gatewayRedirectUrl());
        }
        $gatewayCurrency = $data->gatewayCurrency();
        $gateway = $gatewayCurrency->method;
        $formData = $gateway->form->form_data;

        $formProcessor = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $userData = $formProcessor->processFormData($request, $formData);


        $data->detail = $userData;
        $data->status = Status::PAYMENT_PENDING; // pending
        $data->save();

        $purchaseHistory         = $data->purchaseHistory;
        $purchaseHistory->status = Status::PAYMENT_PENDING;
        $purchaseHistory->save();

        $adminNotification = new AdminNotification();
        $adminNotification->user_id = $data->user->id;
        $adminNotification->title = 'Payment request from ' . $data->user->username;
        $adminNotification->click_url = urlPath('admin.deposit.details', $data->id);
        $adminNotification->save();

        notify($data->user, 'PAYMENT_REQUEST', [
            'method_name' => $data->gatewayCurrency()->name,
            'method_currency' => $data->method_currency,
            'method_amount' => showAmount($data->final_amo),
            'amount' => showAmount($data->amount),
            'charge' => showAmount($data->charge),
            'rate' => showAmount($data->rate),
            'package' => $data->purchaseHistory->package_details->name
        ]);

        $notify[] = ['success', 'Your payment request has been taken'];
        return to_route('user.purchase.history')->withNotify($notify);
    }
}
