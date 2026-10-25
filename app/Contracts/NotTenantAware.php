<?php

namespace App\Contracts;

/**
 * Marks a queued job that should run without the tenant it was dispatched
 * in, e.g. a job that works across every organization.
 */
interface NotTenantAware {}
