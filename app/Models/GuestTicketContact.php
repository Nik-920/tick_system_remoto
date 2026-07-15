<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contacto/seguimiento de un reporte QR público (invitado sin cuenta).
 *
 * @property string $id
 * @property string $ticket_id
 * @property string|null $contact_email
 * @property string $tracking_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket|null $ticket
 */
class GuestTicketContact extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'ticket_id',
        'contact_email',
        'tracking_code',
    ];

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
