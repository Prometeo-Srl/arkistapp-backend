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
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

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
    }
}
