<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $ticket_id
 * @property array<float> $embedding_vector
 * @property string $description_hash
 * @property float|null $similarity_score
 * @property string|null $matched_ticket_id
 * @property bool $is_duplicate
 * @property-read Ticket $ticket
 * @property-read Ticket|null $matchedTicket
 */
class TicketEmbedding extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'ticket_id',
        'embedding_vector',
        'description_hash',
        'similarity_score',
        'matched_ticket_id',
        'is_duplicate',
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
            'embedding_vector' => 'array',
            'similarity_score' => 'float',
            'is_duplicate' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function matchedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'matched_ticket_id');
    }
}
