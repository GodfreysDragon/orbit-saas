# Roadmap

What's planned next for Orbit SaaS, and the current limitations to know about. Setup and
architecture are in the [README](../README.md).

## Known limitations

- **Stripe flows are verified manually, not in CI.** Checkout, webhook sync, plan swap,
  cancel and resume, the customer portal and invoices were run end to end against a Stripe
  sandbox. The automated tests cover plan resolution, trials, limits and webhook logging with
  simulated subscription rows.
- **Console commands have no implicit tenant.** Tenant-owned queries return nothing until they
  call `CurrentOrganization::runAs()` or opt in with `acrossOrganizations()`. Queued jobs get their
  tenant automatically.
- **Failed payments are verified with simulated subscriptions.** The past-due banner and audit
  entry are covered by tests, not yet by a real failed renewal against Stripe.
- **Mail goes to the log by default** (`MAIL_MAILER=log`). Invitation emails need a queue worker;
  `composer run dev` starts one.

## Done

- [x] Handle failed payments (`past_due`): the organization keeps its plan while Stripe retries,
      and a banner links billing managers to the portal.
- [x] Transfer ownership to an existing admin, confirmed with the owner's password.
- [x] Restore the tenant inside queued jobs (`TenantAwareJobs`, with a `NotTenantAware` opt-out).
- [x] Browser tests (Pest browser plugin) for the organization switcher, invite and sign-up,
      ownership transfer and the billing UI.
- [x] Deployment config: a Docker image and a Render Blueprint for the public demo.
- [x] Make the organization scope fail closed: without a tenant, tenant-owned queries match
      nothing unless they opt in with `acrossOrganizations()`.
- [x] Store invitation tokens as SHA-256 hashes, so a database leak doesn't expose working links.
- [x] Record each Stripe webhook event once in the audit log, even when Stripe delivers it again.
- [x] Check seat and project limits under a lock on the organization, in the same transaction as
      the insert (SQLite transactions are `IMMEDIATE`, so they serialize too).

## Later

- [ ] Add a "resend invitation" action, and send an in-app notification when an existing user is
      invited.
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
- [ ] Add a separate queue worker to the deployment for production use (the demo uses the
      sync queue).
