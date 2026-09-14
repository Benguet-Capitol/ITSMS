<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\ItService;
use App\Models\Profile;
use App\Models\Ticket;
use App\Models\TicketComplexityLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'it_service_id' => ItService::factory(),
            'ticket_number' => $this->faker->unique()->numerify('20260101-####'),
            'concern' => $this->faker->sentence(),
            'query_status' => TicketStatus::Queued,
            'request_status' => TicketStatus::Open,
            'complexity_level_id' => TicketComplexityLevel::factory(),
            'date' => now(),
            'is_other_agency' => false,
        ];
    }
}
