<?php

namespace App\Models;

use App\Models\Concerns\HasYearlyControlNumber;
use Illuminate\Database\Eloquent\Model;

class DesignRequest extends Model
{
    use HasYearlyControlNumber;

    /** Same lifecycle as the other request modules -- the form has no approval chain. */
    public const STATUSES = ['pending', 'in_progress', 'completed', 'on_hold', 'cancelled'];

    /** Item value => label printed on form ICT-10, in the form's column order. */
    public const ITEMS = [
        'booklet' => 'Booklet',
        'brochure' => 'Brochure',
        'logo' => 'Logo',
        'certificate' => 'Certificate',
        'invitation' => 'Invitation',
        'poster' => 'Poster',
        'program_paper' => 'Program Paper',
        'tarpaulin' => 'Tarpaulin',
        'video_editing' => 'Video Editing',
        'audio_editing' => 'Audio/Music Editing',
        'template_editing' => 'Template Editing',
        'others' => 'Others',
    ];

    /** The form prints "mpf"; MP4 is the evident intent (see docs/requests-design.md). */
    public const FILE_FORMATS = [
        'pdf' => 'PDF',
        'tiff' => 'TIFF',
        'mp4' => 'MP4',
    ];

    public const MEDIA = [
        'jpeg' => 'JPEG',
        'png' => 'PNG',
        'mp3' => 'MP3',
    ];

    protected $fillable = [
        'control_number',
        'status',
        'date_of_request',
        'department_office',
        'date_needed',
        'items',
        'file_formats',
        'file_format_other',
        'media_provided',
        'requester_name',
        'requester_position',
        'requester_email',
        'assigned_personnel',
        'date_received',
        'date_released',
        'received_by',
        'remarks',
        'feedback_rating',
        'feedback_name',
        'feedback_date',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'date_of_request' => 'date:Y-m-d',
        'date_needed' => 'date:Y-m-d',
        'date_received' => 'date:Y-m-d',
        'date_released' => 'date:Y-m-d',
        'feedback_date' => 'date:Y-m-d',
        'items' => 'array',
        'file_formats' => 'array',
        'media_provided' => 'array',
        'feedback_rating' => 'integer',
    ];

    /** @return array<string, array{item: string, dimension: ?string, quantity: ?string, specify: ?string}> requested items keyed by item value */
    public function itemsByType(): array
    {
        return collect($this->items ?? [])->keyBy('item')->all();
    }

    /**
     * One readable line per requested item, e.g. "Tarpaulin (3x6 ft; 2 pcs)"
     * or "Others: Lanyard (1 pc)".
     *
     * @return list<string>
     */
    public function itemSummaries(): array
    {
        return collect($this->items ?? [])
            ->map(function (array $item) {
                $label = self::ITEMS[$item['item']] ?? $item['item'];
                if ($item['item'] === 'others' && ! empty($item['specify'])) {
                    $label .= ": {$item['specify']}";
                }

                $details = array_filter([$item['dimension'] ?? null, $item['quantity'] ?? null]);

                return $details ? $label.' ('.implode('; ', $details).')' : $label;
            })
            ->values()
            ->all();
    }
}
