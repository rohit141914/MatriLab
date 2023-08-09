<?php

namespace App\Lib;

use App\Constants\Status;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use App\Models\AdminNotification;
use App\Models\UserLimitation;
use App\Models\UserLogin;
use App\Models\Package;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Socialite;

class SocialLogin
{
    private $provider;

    public function __construct($provider)
    {
        $this->provider = $provider;
        $this->configuration();
    }

    public function redirectDriver()
    {
        return Socialite::driver($this->provider)->redirect();
    }

    private function configuration()
    {
        $provider = $this->provider;
        $configuration = gs()->socialite_credentials->$provider;
        Config::set('services.' . $provider, [
            'client_id'     => $configuration->client_id,
            'client_secret' => $configuration->client_secret,
            'redirect'      => route('user.social.login.callback', $provider),
        ]);
    }

    public function login()
    {
        $user = Socialite::driver($this->provider)->user();
        $userData = User::where('username', $user->id)->first();
        if (!$userData) {
            $emailExists = User::where('email', @$user->email)->exists();
            if ($emailExists) {
                throw new Exception('Email already exists');
            }
            $mobileExists = User::where('mobile', @$user->mobile)->exists();
            if ($mobileExists) {
                throw new Exception('Mobile already exists');
            }
            $userData = $this->createUser($user, $this->provider);
        }
        Auth::login($userData);
        $this->loginLog($userData);
        return to_route('user.home');
    }

    private function createUser($user, $provider)
    {
        $general = gs();
        $password = getTrx(8);

        $firstName = preg_replace('/\W\w+\s*(\W*)$/', '$1', $user->name);

        $pieces = explode(' ', $user->name);
        $lastName = array_pop($pieces);
        if (@$user->first_name) {
            $firstName = $user->first_name;
        }
        if (@$user->last_name) {
            $lastName = $user->last_name;
        }

        $newUser = new User();
        $newUser->username = $user->id;
        $newUser->profile_id = getNumber(8);

        $newUser->email = $user->email;

        $newUser->password = Hash::make($password);
        $newUser->firstname = $firstName;
        $newUser->lastname = $lastName;

        $newUser->address = [
            'address' => '',
            'state' => '',
            'zip' => '',
            'country' => '',
            'city' => ''
        ];

        $newUser->status = Status::VERIFIED;
        $newUser->kv = $general->kv ? Status::NO : Status::YES;
        $newUser->ev = 1;
        $newUser->sv = 1;
        $newUser->login_by = $provider;

        if (@$user->avatar) {
            $fileName = uniqid() . time() . '.jpg';
            file_put_contents(getFilePath('userProfile') . '/' . $fileName, file_get_contents($this->provider != 'facebook' ? $user->avatar : $user->avatar . '&access_token=' . $user->token));
            $newUser->image = $fileName;
        }

        $newUser->save();
        $this->userLimitation($newUser);

        $adminNotification = new AdminNotification();
        $adminNotification->user_id = $newUser->id;
        $adminNotification->title = 'New member registered';
        $adminNotification->click_url = urlPath('admin.users.detail', $newUser->id);
        $adminNotification->save();

        return $newUser;
    }

    private function loginLog($user)
    {
        //Login Log Create
        $ip = getRealIP();
        $exist = UserLogin::where('user_ip', $ip)->first();
        $userLogin = new UserLogin();

        //Check exist or not
        if ($exist) {
            $userLogin->longitude =  $exist->longitude;
            $userLogin->latitude =  $exist->latitude;
            $userLogin->city =  $exist->city;
            $userLogin->country_code = $exist->country_code;
            $userLogin->country =  $exist->country;
        } else {
            $info = json_decode(json_encode(getIpInfo()), true);
            $userLogin->longitude =  @implode(',', $info['long']);
            $userLogin->latitude =  @implode(',', $info['lat']);
            $userLogin->city =  @implode(',', $info['city']);
            $userLogin->country_code = @implode(',', $info['code']);
            $userLogin->country =  @implode(',', $info['country']);
        }

        $userAgent = osBrowser();
        $userLogin->user_id = $user->id;
        $userLogin->user_ip =  $ip;

        $userLogin->browser = @$userAgent['browser'];
        $userLogin->os = @$userAgent['os_platform'];
        $userLogin->save();
    }

    private function userLimitation($user)
    {
        $limitation     = new UserLimitation();
        $general        = gs();
        $package        = Package::find($general->default_package_id);
        $validityPeriod = @$package->validity_period ?? 0;
        $expireDate     = Carbon::now()->addDays($validityPeriod);

        $limitation->user_id                = $user->id;
        $limitation->package_id             = @$package->id ?? 0;
        $limitation->interest_express_limit = @$package->interest_express_limit ?? 0;
        $limitation->contact_view_limit     = @$package->contact_view_limit ?? 0;
        $limitation->image_upload_limit     = @$package->image_upload_limit ?? 0;
        $limitation->validity_period        = $validityPeriod;
        $limitation->expire_date            = $expireDate ?? Carbon::now();
        $limitation->save();
    }
}
