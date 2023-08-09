<?php

namespace App\Http\Controllers;

use App\Constants\Status;
use App\Models\AdminNotification;
use App\Models\Frontend;
use App\Models\Language;
use App\Models\Package;
use App\Models\Page;
use App\Models\MaritalStatus;
use App\Models\PhysicalAttribute;
use App\Models\ReligionInfo;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class SiteController extends Controller
{
    public function index()
    {
        $pageTitle = 'Home';
        $sections = Page::where('tempname', $this->activeTemplate)->where('slug', '/')->first();

        $info = json_decode(json_encode(getIpInfo()), true);
        $mobileCode = @implode(',', $info['code']);
        $countries = json_decode(file_get_contents(resource_path('views/partials/country.json')));

        return view($this->activeTemplate . 'home', compact('pageTitle', 'sections', 'mobileCode', 'countries'));
    }

    public function pages($slug)
    {
        $page = Page::where('tempname', $this->activeTemplate)->where('slug', $slug)->firstOrFail();
        $pageTitle = $page->name;
        $sections = $page->secs;
        return view($this->activeTemplate . 'pages', compact('pageTitle', 'sections'));
    }

    public function packages()
    {
        $pageTitle = 'Packages';
        $page = Page::where('tempname', $this->activeTemplate)->where('slug', 'packages')->firstOrFail();
        $packages = Package::active()->orderBy('price', 'ASC')->get();
        $sections = $page->secs;

        return view($this->activeTemplate . 'packages', compact('pageTitle', 'sections', 'packages'));
    }

    public function contact()
    {
        $pageTitle = "Contact Us";
        $contactContent = getContent('contact_us.content', true);
        $page = Page::where('tempname', $this->activeTemplate)->where('slug', 'contact')->firstOrFail();
        $sections = $page->secs;
        return view($this->activeTemplate . 'contact', compact('pageTitle', 'contactContent', 'sections'));
    }


    public function contactSubmit(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required',
            'subject' => 'required|string|max:255',
            'message' => 'required',
        ]);

        if (!verifyCaptcha()) {
            $notify[] = ['error', 'Invalid captcha provided'];
            return back()->withNotify($notify);
        }

        $request->session()->regenerateToken();

        $random = getNumber();

        $ticket = new SupportTicket();
        $ticket->user_id = auth()->id() ?? 0;
        $ticket->name = $request->name;
        $ticket->email = $request->email;
        $ticket->priority = Status::PRIORITY_MEDIUM;


        $ticket->ticket = $random;
        $ticket->subject = $request->subject;
        $ticket->last_reply = Carbon::now();
        $ticket->status = Status::TICKET_OPEN;
        $ticket->save();

        $adminNotification = new AdminNotification();
        $adminNotification->user_id = auth()->user() ? auth()->user()->id : 0;
        $adminNotification->title = 'A new support ticket has opened ';
        $adminNotification->click_url = urlPath('admin.ticket.view', $ticket->id);
        $adminNotification->save();

        $message = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->message = $request->message;
        $message->save();

        $notify[] = ['success', 'Ticket created successfully!'];

        return to_route('ticket.view', [$ticket->ticket])->withNotify($notify);
    }

    public function policyPages($slug, $id)
    {
        $policy = Frontend::where('id', $id)->where('data_keys', 'policy_pages.element')->firstOrFail();
        $pageTitle = $policy->data_values->title;
        return view($this->activeTemplate . 'policy', compact('policy', 'pageTitle'));
    }

    public function changeLanguage($lang = null)
    {
        $language = Language::where('code', $lang)->first();
        if (!$language) $lang = 'en';
        session()->put('lang', $lang);
        $notify[] = ['info', 'Admin can translate every word from the admin panel.'];
        $notify[] = ['warning', 'All Language keywords are not implemented in the demo version.'];
        return back()->withNotify($notify);
    }

    public function stories()
    {
        $pageTitle = 'Stories';
        $stories = Frontend::where('data_keys', 'stories.element')->orderBy('id', 'desc')->paginate(getPaginate(28));
        $page = Page::where('tempname', $this->activeTemplate)->where('slug', 'stories')->firstOrFail();
        $sections = $page->secs;

        return view($this->activeTemplate . 'stories', compact('pageTitle', 'stories', 'sections'));
    }

    public function storyDetails($slug, $id)
    {
        $story = Frontend::where('id', $id)->where('data_keys', 'stories.element')->firstOrFail();
        $dataValues = $story->data_values;
        $dataValues->total_view += 1;
        $story->data_values = $dataValues;
        $story->save();

        $pageTitle = $story->data_values->title;

        $popularStories = Frontend::where('data_keys', 'stories.element')->where('id', '!=', $id)->orderBy('data_values->total_view', 'desc')->limit(5)->get();
        $latestStories = Frontend::where('data_keys', 'stories.element')->where('id', '!=', $id)->orderBy('id', 'desc')->limit(5)->get();
        return view($this->activeTemplate . 'story_details', compact('story', 'pageTitle', 'popularStories', 'latestStories'));
    }


    public function members()
    {
        $userData = $this->userData();
        $members = $userData['members'];
        $user    = auth()->user();

        if (request()->ajax()) {
            return response()->json([
                'html' => view($this->activeTemplate . 'partials.members', compact('members', 'user'))->render()
            ]);
        }

        $pageTitle       = 'Searched Members';
        $maritalStatuses = MaritalStatus::all();
        $religions       = ReligionInfo::get();
        $countryData     = (array)json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countries       = array_column($countryData, 'country');

        $height['max'] = $userData['maxHeight'];
        $height['min'] = $userData['minHeight'];

        if ($height['min'] == $height['max']) {
            $height['min'] = 0;
        }
        return view($this->activeTemplate . 'user.members.list', compact('pageTitle', 'user', 'members', 'maritalStatuses', 'religions', 'countries', 'height'));
    }

    protected function userData()
    {
        $request = request();
        $userId    = auth()->id();
        $query = User::active();
        $maxHeight = round(PhysicalAttribute::max('height')) ?? 0;
        $minHeight = round(PhysicalAttribute::min('height')) ?? 0;
        if ($userId) {
            $query = $query->whereDoesNtHave('ignoredProfile', function ($q) use ($userId) {
                $q->where('ignored_id', $userId);
            })->whereDoesNtHave('ignoredBy', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('id', '!=', $userId);
        }

        if ($request->member_id) {
            $query = $query->where('profile_id', $request->member_id);
        }

        if ($request->looking_for) {
            $query = $query->where('looking_for', $request->looking_for);
        }

        if ($request->height) {
            if ($minHeight == $maxHeight) {
                $minHeight = 0;
            }
            $requestedHeight = explode('-', $request->height);
            $min = trim($requestedHeight[0]);
            $max = trim(rtrim($requestedHeight[1], 'Ft'));

            if ($min != $minHeight || $max != $maxHeight) {
                $query = $query->whereHas('physicalAttributes', function ($q) use ($min, $max) {
                    $q->whereBetween('height', [$min, $max]);
                });
            }
        }

        if ($request->marital_status) {
            $query = $query->whereHas('basicInfo', function ($q) use ($request) {
                $q->where('marital_status', $request->marital_status);
            });
        }

        if ($request->religion) {
            $query = $query->whereHas('basicInfo', function ($q) use ($request) {
                $q->where('religion', $request->religion);
            });
        }

        if ($request->country) {
            $query = $query->whereHas('basicInfo', function ($q) use ($request) {
                $q->where('present_address->country', $request->country);
            });
        }

        if ($request->profession) {
            $query = $query->whereHas('basicInfo', function ($q) use ($request) {
                $q->where('profession', 'like', "%$request->profession%");
            });
        }

        if ($request->city) {
            $query = $query->whereHas('basicInfo', function ($q) use ($request) {
                $q->where('present_address->city', 'like', "%$request->city%");
            });
        }

        if ($request->smoking_status) {
            $query = $query->whereHas('basicInfo', function ($q) use ($request) {
                $q->where('smoking_status', $request->smoking_status);
            });
        }

        if ($request->drinking_status) {
            $query = $query->whereHas('basicInfo', function ($q) use ($request) {
                $q->where('drinking_status', $request->drinking_status);
            });
        }
        $members = $query->with('physicalAttributes', 'limitation.package', 'basicInfo', 'interests')->orderBy('id', 'desc')->paginate(getPaginate(8));
        return ['members' => $members, 'minHeight' => $minHeight, 'maxHeight' => $maxHeight];
    }


    public function cookieAccept()
    {
        $general = gs();
        Cookie::queue('gdpr_cookie', $general->site_name, 43200);
        return back();
    }

    public function cookiePolicy()
    {
        $pageTitle = 'Cookie Policy';
        $cookie = Frontend::where('data_keys', 'cookie.data')->first();
        return view($this->activeTemplate . 'cookie', compact('pageTitle', 'cookie'));
    }

    public function placeholderImage($size = null)
    {
        $imgWidth = explode('x', $size)[0];
        $imgHeight = explode('x', $size)[1];
        $text = $imgWidth . '×' . $imgHeight;
        $fontFile = realpath('assets/font/RobotoMono-Regular.ttf');
        $fontSize = round(($imgWidth - 50) / 8);
        if ($fontSize <= 9) {
            $fontSize = 9;
        }
        if ($imgHeight < 100 && $fontSize > 30) {
            $fontSize = 30;
        }

        $image     = imagecreatetruecolor($imgWidth, $imgHeight);
        $colorFill = imagecolorallocate($image, 100, 100, 100);
        $bgFill    = imagecolorallocate($image, 175, 175, 175);
        imagefill($image, 0, 0, $bgFill);
        $textBox = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth  = abs($textBox[4] - $textBox[0]);
        $textHeight = abs($textBox[5] - $textBox[1]);
        $textX      = ($imgWidth - $textWidth) / 2;
        $textY      = ($imgHeight + $textHeight) / 2;
        header('Content-Type: image/jpeg');
        imagettftext($image, $fontSize, 0, $textX, $textY, $colorFill, $fontFile, $text);
        imagejpeg($image);
        imagedestroy($image);
    }

    public function maintenance()
    {
        $pageTitle = 'Maintenance Mode';
        $general = gs();
        if ($general->maintenance_mode == 0) {
            return to_route('home');
        }
        $maintenance = Frontend::where('data_keys', 'maintenance.data')->first();
        return view($this->activeTemplate . 'maintenance', compact('pageTitle', 'maintenance'));
    }
}
