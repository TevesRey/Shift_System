<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use App\Models\User;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => preg_replace('/\\s+/', ' ', trim((string) $this->input('name'))),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * A previous sign-up that was never verified and is still pending can be redone
     * with the same email (for example when the verification email never arrived).
     */
    public function reusableAccount(): ?User
    {
        $user = User::where('email', strtolower(trim((string) $this->input('email'))))->first();

        return $user
            && strtolower((string) $user->role) === 'employee'
            && ($user->registration_status ?? 'approved') === 'pending'
            && ! $user->hasVerifiedEmail()
            ? $user
            : null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->reusableAccount()?->id)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'role' => ['sometimes', 'in:employee'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = strtolower(preg_replace('/\\s+/', ' ', trim((string) $this->input('name'))));
            if ($name !== '' && User::whereRaw('LOWER(TRIM(full_name)) = ?', [$name])->when($this->reusableAccount(), fn ($q, $u) => $q->where('id', '!=', $u->id))->exists()) {
                $validator->errors()->add('name', 'An account with this full name already exists. Please contact the administrator if this is your account.');
            }
        });
    }
}
