<?php

namespace App\Billing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

final readonly class Plan
{
    /**
     * @param  array{monthly: int, yearly: int}  $prices
     * @param  array{monthly: ?string, yearly: ?string}  $stripePrices
     * @param  list<string>  $features
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public array $prices,
        public array $stripePrices,
        public ?int $maxMembers,
        public ?int $maxProjects,
        public array $features,
        public bool $highlighted = false,
    ) {}

    /**
     * @return Collection<string, self>
     */
    public static function all(): Collection
    {
        return collect(Config::array('billing.plans'))
            ->map(fn (array $plan, string $key) => self::fromConfig($key, $plan));
    }

    public static function find(string $key): ?self
    {
        return self::all()->get($key);
    }

    public static function findOrFail(string $key): self
    {
        return self::find($key) ?? throw new InvalidArgumentException("Unknown plan [{$key}].");
    }

    public static function findByStripePrice(?string $priceId): ?self
    {
        if ($priceId === null) {
            return null;
        }

        return self::all()->first(
            fn (self $plan) => in_array($priceId, $plan->stripePrices, true),
        );
    }

    public static function free(): self
    {
        return self::findOrFail('free');
    }

    public static function trial(): self
    {
        return self::findOrFail(config('billing.trial_plan'));
    }

    public function isFree(): bool
    {
        return $this->prices['monthly'] === 0;
    }

    public function stripePrice(string $interval): ?string
    {
        return $this->stripePrices[$interval] ?? null;
    }

    public function intervalFor(string $priceId): ?string
    {
        $interval = array_search($priceId, $this->stripePrices, true);

        return $interval === false ? null : $interval;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'prices' => $this->prices,
            'limits' => ['members' => $this->maxMembers, 'projects' => $this->maxProjects],
            'features' => $this->features,
            'highlighted' => $this->highlighted,
            'purchasable' => ! $this->isFree() && $this->stripePrices['monthly'] !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private static function fromConfig(string $key, array $plan): self
    {
        return new self(
            key: $key,
            name: $plan['name'],
            description: $plan['description'],
            prices: $plan['price'],
            stripePrices: $plan['stripe_prices'],
            maxMembers: $plan['limits']['members'],
            maxProjects: $plan['limits']['projects'],
            features: $plan['features'],
            highlighted: $plan['highlighted'] ?? false,
        );
    }
}
