<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PaymentTesterUserController extends Controller
{
    public function index(Request $request): View
    {
        $keyword = trim((string) $request->string('q')->toString());
        $hasPaymentTesterColumn = Schema::hasColumn('users', 'is_payment_tester');

        $parentUsersQuery = User::query()
            ->where('role', 'parent')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($nested) use ($keyword): void {
                    $nested->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%");
                });
            });

        if ($hasPaymentTesterColumn) {
            $parentUsersQuery->orderByDesc('is_payment_tester');
        } else {
            $parentUsersQuery->select('users.*')->selectRaw('false as is_payment_tester');
        }

        $parentUsers = $parentUsersQuery
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('system.payment-testers', [
            'parentUsers' => $parentUsers,
            'keyword' => $keyword,
            'hasPaymentTesterColumn' => $hasPaymentTesterColumn,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'parent', 404);

        if (! Schema::hasColumn('users', 'is_payment_tester')) {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'Payment tester column not found. Please run migration: php artisan migrate --path=database/migrations/2026_04_17_000006_add_is_payment_tester_to_users_table.php');
        }

        $validated = $request->validate([
            'is_payment_tester' => ['required', 'boolean'],
        ]);

        $user->forceFill([
            'is_payment_tester' => (bool) $validated['is_payment_tester'],
        ])->save();

        return redirect()
            ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
            ->with('status', $user->is_payment_tester
                ? 'Payment tester enabled for this parent user.'
                : 'Payment tester disabled for this parent user.');
    }
}
