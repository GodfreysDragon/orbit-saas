# Roadmap

What's planned next for Orbit SaaS, and the current limitations to know about. Setup and
architecture are in the [README](../README.md).

## Known limitations

- **Stripe Checkout hasn't been tested against a live Stripe account yet.** Plan resolution,
  trials, limits and webhook logging are covered by tests that use simulated subscription rows.
  The Stripe-hosted flows (Checkout, swap, cancel/resume, the customer portal and invoices) use
  Cashier's standard APIs but haven't been run against a Stripe test account yet.
- **Queued jobs and console commands have no implicit tenant.** They must call
  `CurrentOrganization::runAs()` or filter by `organization_id` themselves.
- **Ownership can't be transferred.** The owner can't leave or be removed, and there is no
  transfer flow yet.
- **Mail goes to the log by default** (`MAIL_MAILER=log`). Invitation emails need a queue worker;
  `composer run dev` starts one.

## Next up

- [ ] Test the full Stripe flow in test mode with `stripe listen`: Checkout, webhook sync,
      plan swap, cancel and resume, the billing portal and invoices.
- [ ] Handle failed payments (`past_due`): show a dashboard banner that links to the portal, and
      decide whether limits drop to Free during dunning.
- [ ] Add an ownership transfer flow (owner → existing admin), with a policy and tests.
- [ ] Add a `TenantAware` job base class or middleware that restores `CurrentOrganization`
      inside queued jobs.
- [ ] Add browser tests (Pest browser plugin) for the org switcher, invite and accept, and the
      billing UI.

## Later

- [ ] Add a "resend invitation" action, and send an in-app notification when an existing user is
      invited.
- [ ] Let an invitation link carry the invitee through registration (sign-up with the email
      pre-filled, then back to the accept page).
- [ ] Add per-seat billing (Cashier quantity) as an alternative to fixed seat limits.
- [ ] Filter the activity log by event type, member and date range, and add CSV export.
- [ ] Add a super-admin area to impersonate users and view organizations and subscriptions
      across tenants.
- [ ] Let an organization upload a logo, and show it in the switcher.
- [ ] Add API tokens scoped to an organization (Sanctum), with an example tenant-aware endpoint.
- [ ] Support a custom subdomain per organization (`acme.app.test`) as an alternative tenant
      resolver.
- [ ] Add usage-based (metered) billing using Cashier meters.
- [ ] Localize UI strings (`lang/` files).
- [ ] Add deployment config (Laravel Cloud / Forge / Docker) with queue worker and scheduler
      setup.
