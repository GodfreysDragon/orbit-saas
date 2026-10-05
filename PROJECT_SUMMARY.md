# Project Summary — Multi-tenant SaaS Boilerplate

_Last updated: 2026-10-04_

A Laravel 13 + Vue 3 (Inertia) starting point for B2B SaaS apps. It has organizations
(multi-tenancy), role-based permissions (Spatie, scoped per organization), Stripe subscription
billing (Laravel Cashier, billed per organization) and a polished dashboard. It is meant as a
portfolio piece that shows real business-app architecture: migrations, policies, middleware,
queues, observers and tests.

See [README.md](README.md) for setup, architecture and Stripe configuration.

## Status

| Check | Result |
|---|---|
| Pest tests | 96 passing (395 assertions) |
| Larastan (level 7) | 0 errors |
| vue-tsc / Vite+ lint and format | Clean |
| Production build | Succeeds |
| Manual check | Logged in as each demo role in headless Chrome and screenshotted the dashboard, projects, members, billing, activity and error pages (light and dark) |

## What's built

- **Organizations as tenants**
  - Every user gets a personal workspace on sign-up and can create or join more.
  - A sidebar switcher changes the active organization.
- **Data isolation**
  - The `BelongsToOrganization` trait adds a global scope and auto-fills `organization_id`.
  - The tenant is resolved before route model binding, so other tenants' records return 404.
- **Roles and permissions**
  - Roles are Owner, Admin, Member and Viewer. They are defined in PHP enums and synced to the
    database by `php artisan permissions:sync`.
  - Spatie's teams mode scopes each role assignment to one organization.
- **Invitations**
  - Emails are queued, and invitations expire after 7 days.
  - Pending invitations count toward the plan's seat limit.
  - Only the verified email address that was invited can accept.
  - Expired invitations are pruned nightly.
- **Billing**
  - `Organization` is the Cashier customer.
  - Free, Pro and Business plans are defined in `config/billing.php`.
  - New organizations get a card-less 14-day trial.
  - Supports Stripe Checkout, plan swaps, cancel and resume, the customer portal and invoices.
- **Plan limits**
  - Seat and project limits are enforced in policies and validation.
  - The UI shows upgrade prompts when a limit is hit.
- **Audit log**
  - Written by model observers, action classes and a queued Cashier webhook listener.
- **UI pages**
  - Dashboard: KPI tiles, 14-day activity chart, usage meters, onboarding checklist and trial
    banner.
  - Projects CRUD, members, billing, activity log, organization settings, invitation acceptance
    and a branded error page.
  - Navigation and controls change with the user's role. Dark mode is supported.

### Key locations

| Concern | Where |
|---|---|
| Tenant context | `app/Support/CurrentOrganization.php`, `app/Http/Middleware/EnsureOrganizationContext.php` |
| Tenant scoping | `app/Models/Concerns/BelongsToOrganization.php`, `app/Models/Scopes/OrganizationScope.php` |
| Roles / permissions | `app/Enums/OrganizationRole.php`, `app/Enums/Permission.php`, `app/Console/Commands/SyncPermissions.php` |
| Business logic | `app/Actions/Organizations/*` |
| Authorization | `app/Policies/*` |
| Billing | `config/billing.php`, `app/Billing/*`, `app/Http/Controllers/BillingController.php` |
| Reference tenant resource | `Project` (model, observer, policy, controller, `resources/js/pages/projects/Index.vue`, tests) |
| Tests | `tests/Feature/{Tenancy,Organizations,Billing}` |

### Demo accounts (`php artisan migrate:fresh --seed`)

All passwords are `password`. Everyone belongs to **Acme Inc**, which is on a Pro trial.

| Email | Role in Acme Inc |
|---|---|
| test@example.com | Owner |
| admin@example.com | Admin |
| member@example.com | Member |
| viewer@example.com | Viewer |

## Known limitations

- **Stripe is unverified end to end.** No keys are configured, so Checkout, swap,
  cancel/resume, the billing portal and invoices have never run against Stripe. Plan
  resolution, trials, limits and webhook logging are covered by tests that use simulated
  subscription rows.
- **Mail goes to the log.** `MAIL_MAILER=log`. Invitation emails need a queue worker; it runs
  automatically with `composer run dev`.
- **The landing page is the starter kit default.** `resources/js/pages/Welcome.vue` is unchanged.
- **Jobs have no implicit tenant.** Queued jobs and console commands must call
  `CurrentOrganization::runAs()` or filter by `organization_id` themselves.
- **Ownership cannot be transferred.** The owner can't leave or be removed, and there is no
  ownership transfer flow yet.

## To-dos / next steps

### High priority

- [ ] Create Stripe test products and prices, fill in `.env`, and test the full flow with
      `stripe listen`:
  - Checkout
  - Webhook sync
  - Plan swap
  - Cancel and resume
  - Billing portal
  - Invoice list
- [ ] Handle failed payments (`past_due`): show a dashboard banner that links to the portal, and
      decide whether limits drop to Free during dunning.
- [ ] Add an ownership transfer flow (owner → existing admin), with policy and tests.
- [ ] Replace the default `Welcome.vue` with a marketing and pricing page that reads from
      `config/billing.php`.
- [ ] Set up CI (GitHub Actions) running `composer run ci:check` (lint, types and tests) on every
      push.

### Medium priority

- [ ] Write a "resend invitation" action, and send an in-app notification when an existing user
      is invited.
- [ ] Let an invitation link carry the invitee through registration: a guest goes to sign-up with
      the email pre-filled, then lands back on the accept page.
- [ ] Add per-seat billing (Cashier quantity) as an alternative to fixed seat limits.
- [ ] Filter the activity log by event type, member and date range, and add CSV export.
- [ ] Add a `TenantAware` job base class or middleware that restores `CurrentOrganization`
      inside queued jobs.
- [ ] Add a super-admin area to impersonate users and view organizations and subscriptions
      across tenants.
- [ ] Add browser tests (Pest browser plugin or Dusk) for the org switcher, invite and accept,
      and the billing UI.

### Nice to have

- [ ] Let an organization upload a logo and avatar, and show them in the switcher.
- [ ] Add API tokens scoped to an organization (Sanctum), with an example tenant-aware API
      endpoint.
- [ ] Support custom subdomains per organization (`acme.app.test`) as an alternative tenant
      resolver.
- [ ] Add usage-based (metered) billing using Cashier meters.
- [ ] Localize UI strings (`lang/` files).
- [ ] Add deployment config (Laravel Cloud / Forge / Docker) with queue worker and scheduler
      setup.
