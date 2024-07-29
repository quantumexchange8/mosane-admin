<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddMemberRequest;
use App\Models\Country;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\DropdownOptionService;
use App\Services\RunningNumberService;
use Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function listing()
    {
        return Inertia::render('Member/Listing/MemberListing');
    }

    public function getMemberListingData()
    {
        $query = User::with(['groupHasUser'])
            ->whereNot('role', 'super-admin')
            ->latest()
            ->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'upline_id' => $user->upline_id,
                    'role' => $user->role,
                    'id_number' => $user->id_number,
                    'group_id' => $user->groupHasUser->group_id ?? null,
                    'group_name' => $user->groupHasUser->group->name ?? null,
                    'group_color' => $user->groupHasUser->group->color ?? null,
                    'status' => $user->status,
                ];
            });

        return response()->json([
            'users' => $query
        ]);
    }

    public function getFilterData()
    {
        return response()->json([
            'countries' => (new DropdownOptionService())->getCountries(),
            'uplines' => (new DropdownOptionService())->getUplines(),
            'groups' => (new DropdownOptionService())->getGroups(),
        ]);
    }

    public function addNewMember(AddMemberRequest $request)
    {
        $upline_id = $request->upline['value'];
        $upline = User::find($upline_id);

        if(empty($upline->hierarchyList)) {
            $hierarchyList = "-" . $upline_id . "-";
        } else {
            $hierarchyList = $upline->hierarchyList . $upline_id . "-";
        }

        $dial_code = $request->dial_code;
        $country = Country::find($dial_code['id']);
        $default_agent_id = User::where('id_number', 'AID00000')->first()->id;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'country' => $request->country,
            'dial_code' => $dial_code['phone_code'],
            'phone' => $request->phone,
            'phone_number' => $request->phone_number,
            'upline_id' => $upline_id,
            'country_id' => $country->id,
            'nationality' => $country->nationality,
            'hierarchyList' => $hierarchyList,
            'password' => Hash::make($request->password),
            'role' => $upline_id == $default_agent_id ? 'agent' : 'member',
            'kyc_approval' => 'verified',
        ]);

        $user->setReferralId();

        $id_no = ($user->role == 'agent' ? 'AID' : 'MID') . Str::padLeft($user->id - 2, 5, "0");
        $user->id_number = $id_no;
        $user->save();

        return back()->with('toast', [
            'title' => trans("public.toast_create_member_success"),
            'type' => 'success',
        ]);
    }

    public function updateMemberStatus(Request $request)
    {
        $user = User::find($request->id);

        $user->status = $user->status == 'active' ? 'inactive' : 'active';
        $user->save();

        return back()->with('toast', [
            'title' => $user->status == 'active' ? trans("public.toast_member_has_activated") : trans("public.toast_member_has_deactivated"),
            'type' => 'success',
        ]);
    }


    public function detail($id_number)
    {
        $user = User::where('id_number', $id_number)->select('id', 'name')->first();

        return Inertia::render('Member/Listing/Partials/MemberListingDetail', [
            'user' => $user
        ]);
    }

    public function getUserData(Request $request)
    {
        $user = User::with(['groupHasUser', 'upline:id,name'])->find($request->id);

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'dial_code' => $user->dial_code,
            'phone' => $user->phone,
            'upline_id' => $user->upline_id,
            'role' => $user->role,
            'id_number' => $user->id_number,
            'status' => $user->status,
            'profile_photo' => $user->getFirstMediaUrl('profile_photo'),
            'group_id' => $user->groupHasUser->group_id ?? null,
            'group_name' => $user->groupHasUser->group->name ?? null,
            'group_color' => $user->groupHasUser->group->color ?? null,
            'upline_name' => $user->upline->name ?? null,
            'upline_profile_photo' => $user->upline ? $user->upline->getFirstMediaUrl('profile_photo') : null,
            'total_direct_member' => $user->directChildren->where('role', 'member')->count(),
            'total_direct_agent' => $user->directChildren->where('role', 'agent')->count(),
            'kyc_verification' => $user->getMedia('kyc_verification'),
        ];

        $paymentAccounts = $user->paymentAccounts()
            ->latest()
            ->limit(3)
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'payment_account_name' => $account->payment_account_name,
                    'account_no' => $account->account_no,
                ];
            });

        return response()->json([
            'userDetail' => $userData,
            'paymentAccounts' => $paymentAccounts
        ]);
    }

    public function updateContactInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($request->user_id)],
            'name' => ['required', 'regex:/^[a-zA-Z0-9\p{Han}. ]+$/u', 'max:255'],
            'dial_code' => ['required'],
            'phone' => ['required', 'max:255'],
            'phone_number' => ['required', 'max:255', Rule::unique(User::class)->ignore($request->user_id)],
        ])->setAttributeNames([
            'email' => trans('public.email'),
            'name' => trans('public.name'),
            'dial_code' => trans('public.dial_code'),
            'phone' => trans('public.phone'),
            'phone_number' => trans('public.phone_number'),
        ]);
        $validator->validate();

        return redirect()->back()->with('toast', [
            'title' => trans('public.update_contact_info_alert'),
            'type' => 'success'
        ]);
    }

    public function updateCryptoWalletInfo(Request $request)
    {
        $wallet_names = $request->wallet_name;
        $token_addresses = $request->token_address;

        $errors = [];

        // Validate wallets and addresses
        foreach ($wallet_names as $index => $wallet_name) {
            $token_address = $token_addresses[$index] ?? '';

            if (empty($wallet_name) && !empty($token_address)) {
                $errors["wallet_name.$index"] = trans('validation.required', ['attribute' => trans('public.wallet_name') . ' #' . ($index + 1)]);
            }

            if (!empty($wallet_name) && empty($token_address)) {
                $errors["token_address.$index"] = trans('validation.required', ['attribute' => trans('public.token_address') . ' #' . ($index + 1)]);
            }
        }

        foreach ($token_addresses as $index => $token_address) {
            $wallet_name = $wallet_names[$index] ?? '';

            if (empty($token_address) && !empty($wallet_name)) {
                $errors["token_address.$index"] = trans('validation.required', ['attribute' => trans('public.token_address') . ' #' . ($index + 1)]);
            }

            if (!empty($token_address) && empty($wallet_name)) {
                $errors["wallet_name.$index"] = trans('validation.required', ['attribute' => trans('public.wallet_name') . ' #' . ($index + 1)]);
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        if ($wallet_names && $token_addresses) {
            foreach ($wallet_names as $index => $wallet_name) {
                // Skip iteration if id or token_address is null
                if (is_null($token_addresses[$index])) {
                    continue;
                }

                $conditions = [
                    'user_id' => $request->user_id,
                ];

                // Check if 'id' is set and valid
                if (!empty($request->id[$index])) {
                    $conditions['id'] = $request->id[$index];
                } else {
                    $conditions['id'] = 0;
                }

                PaymentAccount::updateOrCreate(
                    $conditions,
                    [
                        'status' => 'active',
                        'payment_account_name' => $wallet_name,
                        'payment_platform' => 'crypto',
                        'payment_platform_name' => 'USDT (TRC20)',
                        'account_no' => $token_addresses[$index],
                        'currency' => 'USDT'
                    ]
                );
            }
        }

        return redirect()->back()->with('toast', [
            'title' => trans('public.update_contact_info_alert'),
            'type' => 'success'
        ]);
    }

    public function updateKYCStatus(Request $request)
    {
        dd($request->all());
    }

    public function getFinancialInfoData(Request $request)
    {
        $query = Transaction::query()
            ->where('user_id', $request->id)
            ->where('status', 'successful')
            ->select('id', 'from_meta_login', 'to_meta_login', 'transaction_type', 'amount', 'transaction_amount', 'status', 'created_at');

        $total_deposit = (clone $query)->where('transaction_type', 'deposit')->sum('transaction_amount');
        $total_withdrawal = (clone $query)->where('transaction_type', 'withdrawal')->sum('amount');
        $transaction_history = $query->whereIn('transaction_type', ['deposit', 'withdrawal'])
            ->latest()
            ->get();

        $rebate_wallet = Wallet::where('user_id', $request->id)
            ->where('type', 'rebate_wallet')
            ->first();

        return response()->json([
            'totalDeposit' => $total_deposit,
            'totalWithdrawal' => $total_withdrawal,
            'transactionHistory' => $transaction_history,
            'rebateWallet' => $rebate_wallet,
        ]);
    }

    public function walletAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => ['required'],
            'amount' => ['required', 'numeric', 'gt:1'],
            'remarks' => ['nullable'],
        ])->setAttributeNames([
            'action' => trans('public.action'),
            'amount' => trans('public.amount'),
            'remarks' => trans('public.remarks'),
        ]);
        $validator->validate();

        $action = $request->action;
        $amount = $request->amount;
        $wallet = Wallet::find($request->id);

        if ($action == 'rebate_out' && $wallet->balance < $amount) {
            throw ValidationException::withMessages(['amount' => trans('public.insufficient_balance')]);
        }

        Transaction::create([
            'user_id' => $wallet->user_id,
            'category' => 'wallet',
            'transaction_type' => $action,
            'from_wallet_id' => $action == 'rebate_out' ? $wallet->id : null,
            'to_wallet_id' => $action == 'rebate_in' ? $wallet->id : null,
            'transaction_number' => RunningNumberService::getID('transaction'),
            'amount' => $amount,
            'transaction_charges' => 0,
            'transaction_amount' => $amount,
            'old_wallet_amount' => $wallet->balance,
            'new_wallet_amount' => $action == 'rebate_out' ? $wallet->balance - $amount : $wallet->balance + $amount,
            'status' => 'successful',
            'remarks' => $request->remarks,
            'approved_at' => now(),
            'handle_by' => Auth::id(),
        ]);

        $wallet->balance = $action === 'rebate_out' ? $wallet->balance - $amount : $wallet->balance + $amount;
        $wallet->save();

        return redirect()->back()->with('toast', [
            'title' => trans('public.rebate_adjustment_success'),
            'type' => 'success'
        ]);
    }

    public function accountAdjustment(Request $request)
    {
        dd($request->all());
    }

    public function getAdjustmentHistoryData(Request $request)
    {
        $adjustment_history = Transaction::where('user_id', $request->id)
            ->whereIn('transaction_type', ['rebate_in', 'rebate_out'])
            ->where('status', 'successful')
            ->latest()
            ->get();

        return response()->json($adjustment_history);
    }

    public function accountDelete(Request $request)
    {
        // dd($request->all());
        return redirect()->back()->with('toast', [
            'title' => trans('public.toast_delete_trading_account_success'),
            'type' => 'success'
        ]);

    }

    public function uploadKyc(Request $request)
    {
        dd($request->all());
    }
}
