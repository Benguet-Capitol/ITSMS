<?php

namespace App\Enums;

/**
 * Lifecycle shared by every request type that uses HasRequestWorkflow
 * (see docs/adr/0002-request-workflow.md). Status only changes through
 * RequestWorkflowController actions, never through the edit form.
 */
enum RequestStatus: string
{
    case Pending = 'pending'; // Nobody on it yet
    case InProgress = 'in_progress'; // Accepted by / assigned to at least one person
    case OnHold = 'on_hold';
    case ForCompletion = 'for_completion'; // Work done, waiting for the client's rating
    case Completed = 'completed'; // Closed by an admin after the rating
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::OnHold => 'On Hold',
            self::ForCompletion => 'For Completion',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** Statuses that keep the people on the request BUSY. */
    public static function active(): array
    {
        return [self::InProgress, self::OnHold];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
