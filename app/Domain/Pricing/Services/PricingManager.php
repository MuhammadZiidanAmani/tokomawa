<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Catalog\Models\Product;
use App\Domain\Pricing\Models\PriceChangeLog;
use App\Domain\Pricing\Models\PriceList;
use App\Domain\Pricing\Models\ProductPrice;
use App\Domain\Pricing\Models\TaxRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PricingManager
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function savePriceList(?PriceList $priceList, array $data, AuditContext $context): PriceList
    {
        return DB::transaction(function () use ($priceList, $data, $context): PriceList {
            $priceList = $priceList === null
                ? new PriceList
                : PriceList::query()->lockForUpdate()->findOrFail($priceList->getKey());
            $created = ! $priceList->exists;
            $before = $priceList->exists ? $priceList->attributesToArray() : null;
            $priceList->fill(Arr::except($data, ['reason']));
            if (! $priceList->exists) {
                $priceList->created_by = $context->actor?->getKey();
            }
            $priceList->save();

            $this->auditLogger->record(
                $context,
                $created ? 'price_list.created' : 'price_list.updated',
                'price_list',
                $priceList->public_id,
                before: $before,
                after: $priceList->attributesToArray(),
            );

            return $priceList;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createProductPrice(array $data, AuditContext $context): ProductPrice
    {
        return DB::transaction(function () use ($data, $context): ProductPrice {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $unitIsConfigured = $product->base_unit_id === (int) $data['unit_id']
                || $product->units()->where('unit_id', $data['unit_id'])->where('is_active', true)->exists();

            if (! $unitIsConfigured) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Satuan harga harus terdaftar sebagai satuan barang.',
                ]);
            }

            $overlap = ProductPrice::query()
                ->where(Arr::only($data, ['product_id', 'price_list_id', 'unit_id', 'minimum_quantity']))
                ->where('is_active', true)
                ->where('effective_from', '<', $data['effective_to'] ?? '9999-12-31 23:59:59')
                ->where(function (Builder $query) use ($data): void {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>', $data['effective_from']);
                })
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Periode harga bertabrakan dengan harga aktif pada strata, satuan, dan minimum kuantitas yang sama.',
                ]);
            }

            $price = ProductPrice::query()->create([
                ...Arr::except($data, ['reason']),
                'currency' => 'IDR',
                'created_by' => $context->actor?->getKey(),
            ]);
            PriceChangeLog::query()->create([
                'product_price_id' => $price->getKey(),
                'product_id' => $price->product_id,
                'price_list_id' => $price->price_list_id,
                'unit_id' => $price->unit_id,
                'change_type' => 'created',
                'new_amount' => $price->amount,
                'new_effective_from' => $price->effective_from,
                'new_effective_to' => $price->effective_to,
                'reason' => $data['reason'],
                'changed_by' => $context->actor?->getKey(),
                'changed_at' => now(),
            ]);

            $this->auditLogger->record(
                $context,
                'product_price.created',
                'product_price',
                $price->public_id,
                after: $price->attributesToArray(),
            );

            return $price;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function saveTaxRule(?TaxRule $taxRule, array $data, AuditContext $context): TaxRule
    {
        return DB::transaction(function () use ($taxRule, $data, $context): TaxRule {
            if ($taxRule !== null) {
                $taxRule = TaxRule::query()->lockForUpdate()->findOrFail($taxRule->getKey());
            }

            $overlap = TaxRule::query()
                ->where('code', $data['code'])
                ->where('is_active', true)
                ->when($taxRule !== null, fn (Builder $query) => $query->whereKeyNot($taxRule->getKey()))
                ->where('effective_from', '<', $data['effective_to'] ?? '9999-12-31 23:59:59')
                ->where(function (Builder $query) use ($data): void {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>', $data['effective_from']);
                })
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Periode aturan pajak bertabrakan dengan versi aktif berkode sama.',
                ]);
            }

            $created = $taxRule === null;
            $before = $taxRule?->attributesToArray();
            $taxRule ??= new TaxRule;
            $taxRule->fill(Arr::except($data, ['reason']));
            if (! $taxRule->exists) {
                $taxRule->created_by = $context->actor?->getKey();
            }
            $taxRule->save();

            $this->auditLogger->record(
                $context,
                $created ? 'tax_rule.created' : 'tax_rule.updated',
                'tax_rule',
                $taxRule->public_id,
                before: $before,
                after: $taxRule->attributesToArray(),
            );

            return $taxRule;
        }, 3);
    }
}
