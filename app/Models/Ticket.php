<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'title',
        'description',
        'reporter_id',
        'assigned_to',
        'location_id',
        'category_id',
        'state',
        'priority',
        'resolved_at',
    ];

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(TicketEmbedding::class, 'ticket_id');
    }

    public function aiLogs(): HasMany
    {
        return $this->hasMany(TicketAiLog::class, 'ticket_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(TicketMedia::class, 'ticket_id');
    }

    public function stateHistory(): HasMany
    {
        return $this->hasMany(StateHistory::class, 'ticket_id');
    }
}
