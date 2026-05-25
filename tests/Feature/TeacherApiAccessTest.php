<?php

use App\Models\ApiAccessLog;
use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Models\TeacherApiKey;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

function createTeacherApiKey(User $teacher, string $plainKey = 'pibg_live_testkey12345678901234567890ABCD'): TeacherApiKey
{
    return TeacherApiKey::query()->create([
        'teacher_id' => $teacher->id,
        'key_hash' => TeacherApiKey::hashPlainKey($plainKey),
        'key_prefix' => 'pibg_live',
        'last_four' => substr($plainKey, -4),
        'status' => TeacherApiKey::STATUS_ACTIVE,
    ]);
}

function createPaidFamilyRecord(): FamilyBilling
{
    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-0022',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'T-API-001',
        'family_code' => 'SSP-0022',
        'full_name' => 'PUTRI AUNI MEDINA BINTI MAS ALIF',
        'class_name' => '5 AZALEA',
        'parent_name' => 'MAS ALIF',
        'parent_phone' => '60123456789',
        'total_fee' => 100,
        'paid_amount' => 100,
        'status' => Student::STATUS_ACTIVE,
        'billing_year' => 2026,
        'annual_fee' => 100,
    ]);

    FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'API-SEARCH-001',
        'amount' => 100,
        'fee_amount_paid' => 100,
        'donation_amount' => 0,
        'status' => 'success',
        'paid_at' => '2026-05-21 08:00:00',
    ]);

    return $billing;
}

it('lets a teacher generate an API key and stores only the hash', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);

    $response = $this->actingAs($teacher)->post(route('teacher.api-access.generate'));

    $response->assertRedirect();
    $response->assertSessionHas('plain_api_key');

    $plainKey = $response->getSession()->get('plain_api_key');

    expect($plainKey)->toStartWith('pibg_live_');
    expect(TeacherApiKey::query()->where('teacher_id', $teacher->id)->count())->toBe(1);
    expect(TeacherApiKey::query()->where('key_hash', $plainKey)->exists())->toBeFalse();
    expect(TeacherApiKey::query()->where('key_hash', TeacherApiKey::hashPlainKey($plainKey))->exists())->toBeTrue();
});

it('returns payment status for a valid teacher API key and logs the call', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $plainKey = 'pibg_live_validsearchkey123456789012345678';
    createTeacherApiKey($teacher, $plainKey);
    createPaidFamilyRecord();

    $response = $this
        ->withHeaders([
            'Authorization' => 'Bearer '.$plainKey,
            'Accept' => 'application/json',
        ])
        ->getJson('/api/v1/payment-status/search?q=putri%20auni&year=2026');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('count', 1)
        ->assertJsonPath('data.0.family_code', 'SSP-0022')
        ->assertJsonPath('data.0.payment.status', 'Paid')
        ->assertJsonPath('data.0.payment.status_label', 'Telah Bayar')
        ->assertJsonPath('data.0.payment.total_due', '100.00')
        ->assertJsonPath('data.0.payment.outstanding', '0.00');

    expect(ApiAccessLog::query()->where('teacher_id', $teacher->id)->where('response_status', 200)->where('result_count', 1)->exists())->toBeTrue();
});

it('rejects invalid, revoked, and missing-query API requests with logs', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $plainKey = 'pibg_live_revokedkey12345678901234567890';
    $apiKey = createTeacherApiKey($teacher, $plainKey);
    $apiKey->revoke($teacher);

    $this->withHeaders(['Authorization' => 'Bearer pibg_live_wrongkey'])
        ->getJson('/api/v1/payment-status/search?q=auni&year=2026')
        ->assertStatus(401);

    $this->withHeaders(['Authorization' => 'Bearer '.$plainKey])
        ->getJson('/api/v1/payment-status/search?q=auni&year=2026')
        ->assertStatus(403);

    $activePlainKey = 'pibg_live_missingquery1234567890123456789';
    createTeacherApiKey($teacher, $activePlainKey);

    $this->withHeaders(['Authorization' => 'Bearer '.$activePlainKey])
        ->getJson('/api/v1/payment-status/search?year=2026')
        ->assertStatus(422);

    expect(ApiAccessLog::query()->whereIn('response_status', [401, 403, 422])->count())->toBe(3);
});

it('rate limits API keys to sixty requests per minute', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $plainKey = 'pibg_live_ratelimitkey123456789012345678';
    $apiKey = createTeacherApiKey($teacher, $plainKey);
    RateLimiter::clear('teacher-api-key:'.$apiKey->id);

    for ($i = 0; $i < 60; $i++) {
        $this->withHeaders(['Authorization' => 'Bearer '.$plainKey])
            ->getJson('/api/v1/payment-status/search?q=none&year=2026')
            ->assertOk();
    }

    $this->withHeaders(['Authorization' => 'Bearer '.$plainKey])
        ->getJson('/api/v1/payment-status/search?q=none&year=2026')
        ->assertStatus(429)
        ->assertJsonPath('message', 'Too many requests. Please try again later.');
});

it('lets admin monitor and revoke keys while teachers see only their own API logs', function () {
    $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Teacher One']);
    $otherTeacher = User::factory()->create(['role' => 'teacher', 'name' => 'Teacher Two']);
    $admin = User::factory()->create(['role' => 'system_admin']);

    $apiKey = createTeacherApiKey($teacher, 'pibg_live_teacherone12345678901234567890');
    $otherApiKey = createTeacherApiKey($otherTeacher, 'pibg_live_teachertwo12345678901234567890');

    ApiAccessLog::query()->create([
        'teacher_id' => $teacher->id,
        'api_key_id' => $apiKey->id,
        'endpoint' => '/api/v1/payment-status/search',
        'method' => 'GET',
        'query_text' => 'own query',
        'response_status' => 200,
        'result_count' => 1,
    ]);

    ApiAccessLog::query()->create([
        'teacher_id' => $otherTeacher->id,
        'api_key_id' => $otherApiKey->id,
        'endpoint' => '/api/v1/payment-status/search',
        'method' => 'GET',
        'query_text' => 'other query',
        'response_status' => 200,
        'result_count' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.api-monitor.index'))
        ->assertOk()
        ->assertSee('own query')
        ->assertSee('other query');

    $this->actingAs($teacher)
        ->get(route('teacher.api-access.stats'))
        ->assertOk()
        ->assertSee('own query')
        ->assertDontSee('other query');

    $this->actingAs($admin)
        ->post(route('admin.api-monitor.keys.revoke', $apiKey))
        ->assertRedirect();

    expect($apiKey->fresh()->isActive())->toBeFalse();
});

it('shows the API sidebar destinations for teachers and admin key registry for system admin', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $admin = User::factory()->create(['role' => 'system_admin']);

    $this->actingAs($teacher)
        ->get(route('teacher.api-access.docs'))
        ->assertOk()
        ->assertSee('API Access')
        ->assertSee('API Documentation')
        ->assertSee('API Key Management')
        ->assertSee('API Usage Stats')
        ->assertDontSee('API Monitor')
        ->assertDontSee('API Key Registry');

    $this->actingAs($admin)
        ->get(route('admin.api-keys.index'))
        ->assertOk()
        ->assertSee('API Key Registry')
        ->assertSee('API Monitor');

    $this->actingAs($teacher)
        ->get(route('teacher.api-access'))
        ->assertRedirect(route('teacher.api-access.docs'));
});
