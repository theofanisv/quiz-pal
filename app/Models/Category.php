<?php

namespace App\Models;

use App\Enums\QuestionSource;
use App\Models\Concerns\HasLocalizedText;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'source', 'external_id', 'name_en', 'name_el', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasLocalizedText;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => QuestionSource::class,
            'external_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * @return BelongsToMany<Room, $this>
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class);
    }

    /**
     * The categories of one question source, in the order the lobby lists them.
     *
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function forSource(Builder $query, QuestionSource $source): void
    {
        $query->where('source', $source)->orderBy('sort_order')->orderBy('name_en');
    }

    /**
     * The category name in the current locale.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => $this->localizedText('name'));
    }
}
