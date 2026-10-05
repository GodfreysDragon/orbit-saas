<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationRole;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InviteMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(OrganizationRole::class)->only(OrganizationRole::assignable())],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $organization = app(CurrentOrganization::class)->getOrFail();
                $email = Str::lower($this->string('email')->toString());

                if ($organization->users()->where('email', $email)->exists()) {
                    $validator->errors()->add('email', 'This person is already a member of the organization.');

                    return;
                }

                $alreadyInvited = $organization->invitations()->where('email', $email)->exists();

                if (! $alreadyInvited && ! $organization->canAddMembers()) {
                    $validator->errors()->add(
                        'email',
                        "You've used all seats on the {$organization->plan()->name} plan. Upgrade to invite more members.",
                    );
                }
            },
        ];
    }
}
