<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreAbsenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isEmployee = auth()->user() && strtolower((string) auth()->user()->role) === 'employee';
        $absenceDateRules = ['required', 'date'];
        if ($isEmployee) {
            // Employees can only report an absence for today or a later date (Philippine time).
            $absenceDateRules[] = 'after_or_equal:' . Carbon::now('Asia/Manila')->toDateString();
        }

        return [
            'employee_id' => [$isEmployee ? 'nullable' : 'required', 'integer', 'exists:employees,id'],
            'absence_date' => $absenceDateRules,
            'description' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:pending,approved,rejected'],
            'reviewed_by' => ['nullable', 'integer', 'exists:users,id'],
            'reviewed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'absence_date.after_or_equal' => 'You cannot report an absence for a past date. Please choose today or a later date.',
        ];
    }
}
