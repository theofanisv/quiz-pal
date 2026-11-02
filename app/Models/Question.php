<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedText;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'text_en', 'text_el', 'answers_en', 'answers_el', 'correct_index', 'difficulty'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    use HasLocalizedText;

    public const int ANSWER_COUNT = 4;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers_en' => 'array',
            'answers_el' => 'array',
            'correct_index' => 'integer',
            'difficulty' => 'integer',
            'reported_at' => 'datetime',
            'verified_at' => 'datetime',
            'times_shown' => 'integer',
            'last_shown_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Round, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    public function isCorrect(int $choice): bool
    {
        return $choice === $this->correct_index;
    }

    /**
     * Questions a player reported after their last review.
     *
     * @param  Builder<Question>  $query
     */
    #[Scope]
    protected function awaitingReview(Builder $query): void
    {
        $query->whereNotNull('reported_at')
            ->where(fn (Builder $query) => $query->whereNull('verified_at')->orWhereColumn('verified_at', '<', 'reported_at'));
    }

    /**
     * Questions new games may use: never reported, or reviewed since the last report.
     *
     * @param  Builder<Question>  $query
     */
    #[Scope]
    protected function inRotation(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('reported_at')->orWhereColumn('verified_at', '>=', 'reported_at'));
    }

    /**
     * Questions never shown first, in random order, then the one shown longest ago, so a question comes back only
     * after every other question of its category was shown since. Ordering by recency rather than by
     * `times_shown` keeps a question added later from repeating game after game until its count catches up.
     *
     * @param  Builder<Question>  $query
     */
    #[Scope]
    protected function leastRecentlyShownFirst(Builder $query): void
    {
        $query->orderBy('last_shown_at')->inRandomOrder();
    }

    /**
     * The question text in the current locale.
     *
     * @return Attribute<string, never>
     */
    protected function text(): Attribute
    {
        return Attribute::get(fn (): string => $this->localizedText('text'));
    }

    /**
     * The four answers in the current locale, indexed by their original position.
     *
     * @return Attribute<array<int, string>, never>
     */
    protected function answers(): Attribute
    {
        return Attribute::get(fn (): array => $this->localizedList('answers'));
    }
}
