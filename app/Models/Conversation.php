<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single chat thread, optionally filed under a project.
 */
class Conversation extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'session_id',
        'title',
        'mode',
        'position',
        'pinned_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The most recent message, used for the sidebar preview line.
     */
    public function latestMessage(): ?ChatMessage
    {
        return $this->messages()->latest('position')->first();
    }
}
