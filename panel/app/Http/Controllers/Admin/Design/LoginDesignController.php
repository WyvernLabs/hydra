<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Services\Hydra\LoginDesignService;
use Pterodactyl\Traits\Helpers\AvailableLanguages;

/**
 * Hydra: style, picture and texts of the login page (Designer > Login).
 */
class LoginDesignController extends Controller
{
    use AvailableLanguages;

    public function __construct(
        private AlertsMessageBag $alert,
        private LoginDesignService $login,
        private BackgroundService $backgrounds,
    ) {
    }

    public function index(): View
    {
        $languages = $this->getAvailableLanguages(true);

        return view('admin.design.login', [
            'values' => $this->login->values(),
            'background' => $this->backgrounds->forSlot('login'),
            'languages' => $languages,
            'defaults' => collect(array_keys($languages))->mapWithKeys(fn ($code) => [$code => [
                'title' => trans('client/auth.login.title', [], $code),
                'subtitle' => trans('client/auth.login.subtitle', [], $code),
            ]])->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'login_style' => ['required', Rule::in(LoginDesignService::STYLES)],
            'login_mirror' => 'nullable|boolean',
            'reset_login_image' => 'nullable|boolean',
            'reset_background' => 'nullable|in:login',
            'texts' => 'nullable|array',
        ];
        foreach (LoginDesignService::TEXTS as $key => $max) {
            $rules["texts.$key"] = 'nullable|array';
            $rules["texts.$key.*"] = "nullable|string|max:$max";
        }
        $image = BackgroundService::rules('login')['login_image'];
        $request->validate($rules + ['login_image' => $image] + BackgroundService::rules('bg_login'), [], [
            'login_image' => __('admin/design.login.image'),
            'texts.title.*' => __('admin/design.login.heading'),
            'texts.subtitle.*' => __('admin/design.login.subtitle'),
            'texts.info.*' => __('admin/design.login.info'),
        ]);

        $this->login->save([
            'style' => $request->input('login_style'),
            'mirror' => $request->boolean('login_mirror'),
            'texts' => (array) $request->input('texts', []),
        ]);

        if ($request->hasFile('login_image')) {
            $this->login->storeImage($request->file('login_image'));
        } elseif ($request->boolean('reset_login_image')) {
            $this->login->resetImage();
        }

        // The background of the login page (formerly under Backgrounds).
        if ($request->input('reset_background') === 'login') {
            $this->backgrounds->resetSlot('login');
        } elseif ($request->has('bg_login_blur')) {
            $this->backgrounds->saveSlot(
                'login',
                $request->file('bg_login_image'),
                (int) $request->input('bg_login_blur', 0),
                (int) $request->input('bg_login_dim', 0),
            );
        }

        $this->alert->success(__('admin/design.login.saved'))->flash();

        return redirect()->route('admin.design.login');
    }
}
