<?php

namespace App\Http\Requests\Billing;

use App\Billing\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $purchasable = Plan::all()
            ->reject(fn (Plan $plan) => $plan->isFree())
            ->keys()
            ->all();

        return [
            'plan' => ['required', 'string', Rule::in($purchasable)],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
        ];
    }

    public function plan(): Plan
    {
        return Plan::findOrFail($this->string('plan')->toString());
    }

    public function stripePrice(): ?string
    {
        return $this->plan()->stripePrice($this->string('interval')->toString());
    }
}
