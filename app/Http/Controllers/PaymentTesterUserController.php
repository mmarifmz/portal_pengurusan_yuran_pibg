<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentTesterUserController extends Controller
{
    public function index(Request $request): View
    {
        $keyword = trim((string) $request->string('q')->toString());

        $parentUsers = User::query()
            ->where('role', 'parent')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($nested) use ($keyword): void {
                    $nested->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('is_payment_tester')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('system.payment-testers', [
            'parentUsers' => $parentUsers,
            'keyword' => $keyword,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'parent', 404);

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