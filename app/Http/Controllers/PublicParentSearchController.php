<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicParentSearchController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Collection<int, Student> $students */
        $students = collect();
        $hasSearched = false;

        if ($request->filled('student_keyword') || $request->filled('contact')) {
            $validated = $request->validate([
                'student_keyword' => ['nullable', 'string', 'max:100', 'required_without:contact'],
                'contact' => ['nullable', 'string', 'max:30', 'required_without:student_keyword'],
            ]);

            $hasSearched = true;

            $students = Student::query()
                ->when($validated['student_keyword'] ?? null, function ($query, $keyword) {
                    $query->where(function ($nested) use ($keyword) {
                        $nested->where('full_name', 'like', "%{$keyword}%")
                            ->orWhere('student_no', 'like', "%{$keyword}%")
                            ->orWhere('class_name', 'like', "%{$keyword}%");
                    });
                })
                ->when($validated['contact'] ?? null, fn ($query, $contact) => $query->where('parent_phone', 'like', "%{$contact}%"))
                ->orderBy('full_name')
                ->take(50)
                ->get();
        }

        return view('parent.search', [
            'students' => $students,
            'hasSearched' => $hasSearched,
        ]);
    }
}