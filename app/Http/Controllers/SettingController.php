<?php

namespace BookStack\Http\Controllers;

use BookStack\Actions\ActivityType;
use BookStack\Auth\User;
use BookStack\Settings\AppSettingsStore;
use BookStack\Uploads\ImageRepo;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    protected array $settingCategories = ['features', 'customization', 'registration'];

    /**
     * Handle requests to the settings index path.
     */
    public function index()
    {
        return redirect('/settings/features');
    }

    /**
     * Display the settings for the given category.
     */
    public function category(string $category)
    {
        $this->ensureCategoryExists($category);
        $this->checkPermission('settings-manage');
        $this->setPageTitle(trans('settings.settings'));

        // Get application version
        $version = trim(file_get_contents(base_path('version')));

        return view('settings.' . $category, [
            'category'  => $category,
            'version'   => $version,
            'guestUser' => User::getDefault(),
        ]);
    }

    /**
     * Update the specified settings in storage.
     */
    public function update(Request $request, AppSettingsStore $store, string $category)
    {
        $this->ensureCategoryExists($category);
        $this->preventAccessInDemoMode();
        $this->checkPermission('settings-manage');
        $this->validate($request, [
            'app_logo' => ['nullable', ...$this->getImageValidationRules()],
            'app_icon' => ['nullable', ...$this->getImageValidationRules()],
        ]);

        $store->storeFromUpdateRequest($request, $category);

        $this->logActivity(ActivityType::SETTINGS_UPDATE, $category);
        $this->showSuccessNotification(trans('settings.settings_save_success'));

        return redirect("/settings/{$category}");
    }

    protected function ensureCategoryExists(string $category): void
    {
        if (!in_array($category, $this->settingCategories)) {
            abort(404);
        }
    }

    public function getNotifications()
    {
        $user = auth()->user();
        $notifications = $user->notifications()->paginate(10);
        $user->unreadNotifications->markAsRead();
        return view('common.list-notifications',['notifications' => $notifications]);
    }

    public function clearNotifications()
    {
        $notifications = auth()->user()->notifications;
        $notifications->each(function ($notification) {
            $notification->delete();
        });
        $this->showSuccessNotification(trans('settings.notifications_clear_success'));
        return redirect()->back();
    }
}
