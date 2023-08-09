<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\PaymentController;
use App\Models\Deposit;
use App\Models\Gateway;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Models\UserLimitation;
use App\Models\Package;
use App\Models\PurchaseHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function pending()
    {
        $pageTitle = 'Pending Payments';
        $deposits = $this->paymentData('pending');
        return view('admin.deposit.log', compact('pageTitle', 'deposits'));
    }


    public function approved()
    {
        $pageTitle = 'Approved Payments';
        $deposits = $this->paymentData('approved');
        return view('admin.deposit.log', compact('pageTitle', 'deposits'));
    }
    public function initiated()
    {
        $pageTitle = 'Initiated Payments';
        $deposits = $this->paymentData('initiated');
        return view('admin.deposit.log', compact('pageTitle', 'deposits'));
    }

    public function successful()
    {
        $pageTitle = 'Successful Payments';
        $deposits = $this->paymentData('successful');
        return view('admin.deposit.log', compact('pageTitle', 'deposits'));
    }

    public function rejected()
    {
        $pageTitle = 'Rejected Payments';
        $deposits = $this->paymentData('rejected');
        return view('admin.deposit.log', compact('pageTitle', 'deposits'));
    }

    public function deposit()
    {
        $pageTitle = 'Payment History';
        $paymentData = $this->paymentData($scope = null, $summery = true);
        $deposits = $paymentData['data'];
        $summery = $paymentData['summery'];
        $initiated = $summery['initiated'];
        $successful = $summery['successful'];
        $pending = $summery['pending'];
        $rejected = $summery['rejected'];
        return view('admin.deposit.log', compact('pageTitle', 'deposits', 'successful', 'pending', 'rejected', 'initiated'));
    }

    protected function paymentData($scope = null, $summery = false)
    {
        if ($scope) {
            $deposits = Deposit::$scope()->with(['user', 'gateway', 'purchaseHistory']);
        } else {
            $deposits = Deposit::with(['user', 'gateway', 'purchaseHistory']);
        }

        $request = request();
        //search
        if ($request->search) {
            $search = request()->search;
            $deposits = $deposits->where(function ($q) use ($search) {
                $q->where('trx', 'like', "%$search%")->orWhereHas('user', function ($user) use ($search) {
                    $user->where('username', 'like', "%$search%");
                });
            });
        }

        //date search
        if ($request->date) {
            $date = explode('-', $request->date);
            $request->merge([
                'start_date' => trim(@$date[0]),
                'end_date'  => trim(@$date[1])
            ]);
            $request->validate([
                'start_date'    => 'required|date_format:m/d/Y',
                'end_date'      => 'nullable|date_format:m/d/Y'
            ]);
            if ($request->end_date) {
                $endDate = Carbon::parse($request->end_date)->addHours(23)->addMinutes(59)->addSecond(59);
                $deposits   = $deposits->whereBetween('created_at', [Carbon::parse($request->start_date), $endDate]);
            } else {
                $deposits   = $deposits->whereDate('created_at', Carbon::parse($request->start_date));
            }
        }

        //vai method
        if ($request->method) {
            $method = Gateway::where('alias', $request->method)->firstOrFail();
            $deposits = $deposits->where('method_code', $method->code);
        }

        if (!$summery) {
            return $deposits->orderBy('id', 'desc')->paginate(getPaginate());
        } else {
            $successful = clone $deposits;
            $pending = clone $deposits;
            $rejected = clone $deposits;
            $initiated = clone $deposits;

            $successfulSummery = $successful->where('status', 1)->sum('amount');
            $pendingSummery = $pending->where('status', 2)->sum('amount');
            $rejectedSummery = $rejected->where('status', 3)->sum('amount');
            $initiatedSummery = $initiated->where('status', 0)->sum('amount');

            return [
                'data' => $deposits->orderBy('id', 'desc')->paginate(getPaginate()),
                'summery' => [
                    'successful' => $successfulSummery,
                    'pending' => $pendingSummery,
                    'rejected' => $rejectedSummery,
                    'initiated' => $initiatedSummery,
                ]
            ];
        }
    }

    public function details($id)
    {
        $general = GeneralSetting::first();
        $deposit = Deposit::where('id', $id)->with(['user', 'gateway', 'purchaseHistory'])->firstOrFail();
        $pageTitle = $deposit->user->username . ' requested ' . showAmount($deposit->amount) . ' ' . $general->cur_text;
        $details = ($deposit->detail != null) ? json_encode($deposit->detail) : null;
        return view('admin.deposit.detail', compact('pageTitle', 'deposit', 'details'));
    }


    public function approve($id)
    {
        $deposit = Deposit::where('id', $id)->where('status', Status::PAYMENT_PENDING)->firstOrFail();

        PaymentController::userDataUpdate($deposit, true);

        $notify[] = ['success', 'Payment approved successfully'];

        return to_route('admin.deposit.pending')->withNotify($notify);
    }

    public function reject(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'message' => 'required|string|max:255'
        ]);
        $deposit = Deposit::where('id', $request->id)->where('status', Status::PAYMENT_PENDING)->with('purchaseHistory')->firstOrFail();

        $deposit->admin_feedback = $request->message;
        $deposit->status = Status::PAYMENT_REJECT;
        $deposit->save();

        $purchaseHistory = $deposit->purchaseHistory;
        $purchaseHistory->status = Status::PAYMENT_REJECT;
        $purchaseHistory->save();

        notify($deposit->user, 'PAYMENT_REJECT', [
            'method_name' => $deposit->gatewayCurrency()->name,
            'method_currency' => $deposit->method_currency,
            'method_amount' => showAmount($deposit->final_amo),
            'amount' => showAmount($deposit->amount),
            'charge' => showAmount($deposit->charge),
            'rate' => showAmount($deposit->rate),
            'package' => $purchaseHistory->package_details->name,
            'rejection_message' => $request->message
        ]);

        $notify[] = ['success', 'Payment request rejected successfully'];
        return  to_route('admin.deposit.pending')->withNotify($notify);
    }
}
