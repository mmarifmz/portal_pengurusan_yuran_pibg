<?php

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('imports teacher csv rows and creates teacher users', function () {
    $admin = makeTeacherManager();
    seedTeacherClass('4 ANGSANA');

    $response = $this->actingAs($admin)->post(route('super-teacher.teachers.import'), [
        'teachers_csv' => teacherCsvUpload(implode("\n", [
            'Name,Phone,Email,Group,Class',
            'SSP 4 Angsana - Cikgu Farahani,0139906160,cikgu.farahani@sripetaling.edu.my,TAHAP 2,4 ANGSANA',
        ])),
        'auto_assign_class' => '1',
    ]);

    $response
        ->assertRedirect(route('super-teacher.teachers.index'))
        ->assertSessionHas('teacher_import_summary');

    $teacher = User::query()->where('email', 'cikgu.farahani@sripetaling.edu.my')->first();

    expect($teacher)->not->toBeNull();
    expect($teacher->role)->toBe('teacher');
    expect($teacher->is_active)->toBeTrue();
});

it('updates an existing teacher instead of creating a duplicate', function () {
    $admin = makeTeacherManager();

    $teacher = User::factory()->create([
        'role' => 'teacher',
        'name' => 'Old Name',
        'email' => 'teacher.update@example.test',
        'phone' => '+60111111111',
    ]);

    $response = $this->actingAs($admin)->post(route('super-teacher.teachers.import'), [
        'teachers_csv' => teacherCsvUpload(implode("\n", [
            'Name,Phone,Email,Group,Class',
            'New Imported Name,+60 12-850 9436,teacher.update@example.test,TAHAP 2,4 ALAMANDA',
        ])),
        'auto_assign_class' => '0',
    ]);

    $response->assertRedirect(route('super-teacher.teachers.index'));

    expect(User::query()->where('email', 'teacher.update@example.test')->count())->toBe(1);
    expect($teacher->fresh()->name)->toBe('NEW IMPORTED NAME');
    expect($teacher->fresh()->phone)->toBe('+60128509436');
});

it('assigns class names during import when a matching class exists', function () {
    $admin = makeTeacherManager();
    seedTeacherClass('4 ANGSANA');

    $this->actingAs($admin)->post(route('super-teacher.teachers.import'), [
        'teachers_csv' => teacherCsvUpload(implode("\n", [
            'Name,Phone,Email,Group,Class',
            'Class Teacher,+60139906160,class.teacher@example.test,TAHAP 2,  4 angsana  ',
        ])),
        'auto_assign_class' => '1',
    ])->assertRedirect(route('super-teacher.teachers.index'));

    $teacher = User::query()->where('email', 'class.teacher@example.test')->first();

    expect($teacher)->not->toBeNull();
    expect($teacher->class_name)->toBe('4 ANGSANA');
});

it('keeps importing teachers when a class cannot be matched', function () {
    $admin = makeTeacherManager();

    $response = $this->actingAs($admin)->post(route('super-teacher.teachers.import'), [
        'teachers_csv' => teacherCsvUpload(implode("\n", [
            'Name,Phone,Email,Group,Class',
            'Unmatched Class Teacher,+60129772410,unmatched.class@example.test,TAHAP 2,9 CEMPAKA',
        ])),
        'auto_assign_class' => '1',
    ]);

    $response
        ->assertRedirect(route('super-teacher.teachers.index'))
        ->assertSessionHas('teacher_import_summary', function (array $summary): bool {
            return ($summary['created'] ?? 0) === 1
                && ($summary['no_class_matched'] ?? 0) === 1
                && ($summary['failed_rows_count'] ?? 0) === 0;
        });

    $teacher = User::query()->where('email', 'unmatched.class@example.test')->first();

    expect($teacher)->not->toBeNull();
    expect($teacher->class_name)->toBeNull();
});

it('normalizes imported teacher phones into malaysia whatsapp format', function () {
    $admin = makeTeacherManager();

    $this->actingAs($admin)->post(route('super-teacher.teachers.import'), [
        'teachers_csv' => teacherCsvUpload(implode("\n", [
            'Name,Phone,Email,Group,Class',
            'Normalized Phone,+60 13-990 6160,normalized.phone@example.test,TAHAP 2,4 ANGSANA',
        ])),
        'auto_assign_class' => '0',
    ])->assertRedirect(route('super-teacher.teachers.index'));

    $teacher = User::query()->where('email', 'normalized.phone@example.test')->first();

    expect($teacher)->not->toBeNull();
    expect($teacher->phone)->toBe('+60139906160');
});

it('batch enables whatsapp only for eligible assigned active teachers', function () {
    $admin = makeTeacherManager();

    $eligibleTeacher = User::factory()->create([
        'role' => 'teacher',
        'phone' => '+60139906160',
        'class_name' => '4 ANGSANA',
        'is_active' => true,
        'receive_whatsapp_notifications' => false,
    ]);

    $noClassTeacher = User::factory()->create([
        'role' => 'teacher',
        'phone' => '+601128509436',
        'class_name' => null,
        'is_active' => true,
        'receive_whatsapp_notifications' => false,
    ]);

    $inactiveTeacher = User::factory()->create([
        'role' => 'teacher',
        'phone' => '+60129772410',
        'class_name' => '4 AKASIA',
        'is_active' => false,
        'receive_whatsapp_notifications' => false,
    ]);

    $response = $this->actingAs($admin)->post(route('super-teacher.teachers.enable-whatsapp-all'));

    $response->assertRedirect(route('super-teacher.teachers.index'));

    expect($eligibleTeacher->fresh()->receive_whatsapp_notifications)->toBeTrue();
    expect($noClassTeacher->fresh()->receive_whatsapp_notifications)->toBeFalse();
    expect($inactiveTeacher->fresh()->receive_whatsapp_notifications)->toBeFalse();
});

it('updates invite status when a teacher invite is sent', function () {
    config()->set('services.whatsapp.enabled', true);

    $admin = makeTeacherManager();

    $teacher = User::factory()->create([
        'role' => 'teacher',
        'phone' => '+60139906160',
        'class_name' => '4 ANGSANA',
        'is_active' => true,
        'invite_status' => 'pending',
    ]);

    $response = $this->actingAs($admin)->post(route('super-teacher.teachers.send-invite', $teacher));

    $response
        ->assertRedirect(route('super-teacher.teachers.index'))
        ->assertSessionHasNoErrors();

    $teacher->refresh();

    expect($teacher->invite_status)->toBe('sent');
    expect($teacher->teacher_invite_sent_at)->not->toBeNull();
});

it('converts an existing parent user into a teacher during import without creating a duplicate', function () {
    $admin = makeTeacherManager();
    seedTeacherClass('5 CEMPAKA');

    $parent = User::factory()->create([
        'role' => 'parent',
        'name' => 'Parent Teacher',
        'email' => 'parent.teacher@example.test',
        'phone' => '+60139906160',
    ]);

    $response = $this->actingAs($admin)->post(route('super-teacher.teachers.import'), [
        'teachers_csv' => teacherCsvUpload(implode("\n", [
            'Name,Phone,Email,Group,Class',
            'Cikgu Parent Teacher,0139906160,parent.teacher@example.test,TAHAP 2,5 CEMPAKA',
        ])),
        'auto_assign_class' => '1',
        'enable_whatsapp_notifications' => '1',
    ]);

    $response
        ->assertRedirect(route('super-teacher.teachers.index'))
        ->assertSessionHas('teacher_import_summary', function (array $summary): bool {
            return ($summary['converted_existing_users'] ?? 0) === 1;
        });

    expect(User::query()->where('email', 'parent.teacher@example.test')->count())->toBe(1);
    expect($parent->fresh()->hasRole('parent'))->toBeTrue();
    expect($parent->fresh()->hasRole('teacher'))->toBeTrue();
    expect($parent->fresh()->class_name)->toBe('5 CEMPAKA');
    expect($parent->fresh()->receive_whatsapp_notifications)->toBeTrue();
});

it('allows assigning an existing parent user as teacher while keeping parent access', function () {
    $admin = makeTeacherManager();
    seedTeacherClass('6 DELIMA');

    $parent = User::factory()->create([
        'role' => 'parent',
        'name' => 'Pn Laila',
        'email' => 'laila.parent@example.test',
        'phone' => '+60128887766',
    ]);

    $response = $this->actingAs($admin)->post(route('super-teacher.teachers.assign-existing'), [
        'user_id' => $parent->id,
        'class_name' => '6 DELIMA',
        'enable_whatsapp_notifications' => '1',
        'send_teacher_invite' => '0',
    ]);

    $response->assertRedirect(route('super-teacher.teachers.index', ['existing_user_search' => '']));

    expect(User::query()->where('email', 'laila.parent@example.test')->count())->toBe(1);
    expect($parent->fresh()->hasRole('parent'))->toBeTrue();
    expect($parent->fresh()->hasRole('teacher'))->toBeTrue();
    expect($parent->fresh()->class_name)->toBe('6 DELIMA');
});

it('existing parent teacher invite uses existing-account wording without reset password', function () {
    config()->set('services.whatsapp.enabled', false);

    $admin = makeTeacherManager();
    $user = User::factory()->create([
        'role' => 'parent',
        'name' => 'Teacher Parent',
        'email' => 'teacher.parent@example.test',
        'phone' => '+60135557777',
        'class_name' => '4 ANGSANA',
        'is_active' => true,
    ]);
    $user->assignRole('teacher');

    $response = $this->actingAs($admin)->post(route('super-teacher.teachers.send-invite', $user));

    $response
        ->assertRedirect(route('super-teacher.teachers.index'))
        ->assertSessionHas('teacher_manual_invites', function (array $manualInvites): bool {
            $message = (string) ($manualInvites[0]['message'] ?? '');

            return str_contains($message, 'akaun sedia ada')
                && str_contains($message, 'Teacher Dashboard')
                && ! str_contains($message, 'Tetapkan kata laluan');
        });
});

it('keeps failed-row csv available after the import summary page loads', function () {
    $admin = makeTeacherManager();
    seedTeacherClass('2 AKASIA');

    User::factory()->create([
        'role' => 'teacher',
        'name' => 'Existing Teacher',
        'email' => 'existing.teacher@example.test',
        'phone' => '60148020181',
    ]);

    $this->actingAs($admin)->post(route('super-teacher.teachers.import'), [
        'teachers_csv' => teacherCsvUpload(implode("\n", [
            'Name,Phone,Email,Group,Class',
            'SSP 2 Akasia - Cikgu Aina Syhaqirien,+60148020181,cikgu.aina.syhaqirien@sripetaling.edu.my,TAHAP 1,2 AKASIA',
        ])),
        'auto_assign_class' => '1',
    ])->assertRedirect(route('super-teacher.teachers.index'));

    $this->actingAs($admin)->get(route('super-teacher.teachers.index'))->assertOk();

    $downloadResponse = $this->actingAs($admin)->get(route('super-teacher.teachers.import.failed-rows'));

    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

function makeTeacherManager(): User
{
    return User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);
}

function seedTeacherClass(string $className): Student
{
    return Student::query()->create([
        'student_no' => 'STU-'.str_replace(' ', '-', $className),
        'family_code' => 'FAM-'.str_replace(' ', '-', $className),
        'full_name' => 'Student '.$className,
        'class_name' => $className,
        'parent_name' => 'Parent '.$className,
        'parent_phone' => '0123456789',
        'parent_email' => 'parent+'.strtolower(str_replace(' ', '.', $className)).'@example.test',
        'status' => 'active',
        'billing_year' => now()->year,
        'annual_fee' => 100,
        'total_fee' => 100,
        'paid_amount' => 0,
    ]);
}

function teacherCsvUpload(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('teachers.csv', $content);
}
