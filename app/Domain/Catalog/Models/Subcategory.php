<?php

namespace App\Domain\Catalog\Models;

use App\Models\User;
use Database\Factories\SubcategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['category_id', 'code', 'name', 'description', 'is_active', 'created_by'])]
class Subcategory extends Model
{
    /** @use HasFactory<SubcategoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Subcategory $subcategory): void {
            $subcategory->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): SubcategoryFactory
    {
        return SubcategoryFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
