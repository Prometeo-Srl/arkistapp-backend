<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Checklist;
use App\Models\ChecklistAssignment;
use App\Models\Company;
use App\Models\File;
use App\Models\Folder;
use App\Models\IncidentReport;
use App\Models\OrgRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stable aliases for the polymorphic columns (access_grants, activities, audit_logs):
        // the database must not store FQCNs, or renaming a class breaks the stored data.
        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'org_role' => OrgRole::class,
            'category' => Category::class,
            'folder' => Folder::class,
            'file' => File::class,
            'checklist' => Checklist::class,
            'checklist_assignment' => ChecklistAssignment::class,
            'incident_report' => IncidentReport::class,
        ]);

        $this->configureRateLimiting();
    }

    /**
     * Laravel 11+ no longer throttles API routes by default, so the endpoints that
     * hand out or redeem credentials need an explicit limit: without one, both the
     * login form and the invitation token are open to unlimited guessing.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('invitations', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }
}
