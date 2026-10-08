<?php

namespace App\Models;

use Database\Factories\CustomProviderModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A model an operator chose to keep from a custom provider's catalogue.
 *
 * The rows are the operator's answer to "which models is this endpoint
 * actually for", written down so the picker can offer them without asking the
 * endpoint again. Discovery is cheap to repeat, but a model the user picked
 * should not disappear because the endpoint was briefly unreachable.
 */
#[Fillable(['provider', 'model_id', 'label'])]
class CustomProviderModel extends Model
{
    /** @use HasFactory<CustomProviderModelFactory> */
    use HasFactory;

    /**
     * The models kept for one custom provider.
     *
     * @return Builder<CustomProviderModel>
     */
    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }
}
