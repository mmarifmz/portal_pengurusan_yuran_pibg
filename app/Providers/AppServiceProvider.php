<?php

namespace App\Providers;

use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\View;

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
        $this->configureDefaults();
        $this->registerGlobalToasterData();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function registerGlobalToasterData(): void
    {
        View::composer('*', function ($view): void {
            if (! Auth::check()) {
                return;
            }

            $recentTransactions = FamilyPaymentTransaction::query()
                ->with('familyBilling:id,family_code,billing_year')
                ->where('status', 'success')
                ->whereNotNull('paid_at')
                ->orderByDesc('paid_at')
                ->limit(20)
                ->get();

            $familyCodes = $recentTransactions
                ->pluck('familyBilling.family_code')
                ->filter()
                ->unique()
                ->values();

            $dominantClassByFamily = Student::query()
                ->whereIn('family_code', $familyCodes)
                ->select(['family_code', 'class_name'])
                ->get()
                ->groupBy('family_code')
                ->map(function ($familyStudents): string {
                    return (string) ($familyStudents
                        ->pluck('class_name')
                        ->map(fn ($className) => trim((string) $className))
                        ->filter()
                        ->countBy()
                        ->sortDesc()
                        ->keys()
                        ->first() ?? 'Unknown Class');
                });

            $recentPaymentToasts = $recentTransactions
                ->map(function (FamilyPaymentTransaction $transaction) use ($dominantClassByFamily): ?string {
                    $familyCode = (string) ($transaction->familyBilling?->family_code ?? '');
                    if ($familyCode === '') {
                        return null;
                    }

                    $className = (string) ($dominantClassByFamily->get($familyCode) ?: 'Unknown Class');
                    $donation = (float) ($transaction->donation_amount ?? 0);

                    if ($donation <= 0) {
                        $donation = max(0, (float) $transaction->amount - (float) ($transaction->fee_amount_paid ?? 0));
                    }

                    if ($donation > 0) {
                        return "Parent in {$className} just paid Yuran + Sumbangan Tambahan";
                    }

                    return "Parent in {$className} just paid Yuran";
                })
                ->filter()
                ->unique()
                ->take(10)
                ->values();

            $view->with('globalRecentPaymentToasts', $recentPaymentToasts);
        });
    }
}
