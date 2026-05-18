<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportTeacherUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageTeacherUsers();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'teachers_csv' => [
                'required',
                'file',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->file('teachers_csv')?->isValid()) {
                        $fail('Please upload a valid CSV file.');

                        return;
                    }

                    $extension = mb_strtolower((string) $this->file('teachers_csv')?->getClientOriginalExtension());

                    if ($extension !== 'csv') {
                        $fail('The teacher import file must be a CSV.');
                    }
                },
            ],
            'auto_assign_class' => ['nullable', 'boolean'],
            'enable_whatsapp_notifications' => ['nullable', 'boolean'],
            'send_teacher_invites' => ['nullable', 'boolean'],
        ];
    }
}
