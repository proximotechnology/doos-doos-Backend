<?php

namespace App\Http\Requests\registartion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'email' => 'nullable|string|email|max:255',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'email.email' => 'يجب أن يكون البريد الإلكتروني صالحاً.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'يجب أن تحتوي كلمة المرور على الأقل على 8 أحرف.',
            'email_or_phone.required' => 'يجب تقديم إما البريد الإلكتروني أو رقم الهاتف.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422));
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $email = $this->input('email');
            $phone = $this->input('phone');
            
            // إذا لم يتم تقديم أي من الإيميل أو الهاتف
            if (empty($email) && empty($phone)) {
                $validator->errors()->add('email_or_phone', 'يجب تقديم إما البريد الإلكتروني أو رقم الهاتف.');
            }
            
            // إذا تم تقديم الإيميل ولكن بتنسيق غير صحيح
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validator->errors()->add('email', 'صيغة البريد الإلكتروني غير صالحة.');
            }
        });
    }
}