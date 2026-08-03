<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Checklist;
use App\Models\Company;
use App\Models\ChecklistAssignment;
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
        // Alias stabili per le colonne polimorfe (access_grants, activities, audit_logs):
        // il DB non deve contenere FQCN, altrimenti un rename di classe rompe i dati.
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
