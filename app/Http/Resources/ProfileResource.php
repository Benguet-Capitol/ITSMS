<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\CarbonInterface;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
          'id' => $this->id,
          'user_id' => $this->user_id,
          'display_name' => $this->display_name,
          'name' => json_decode($this->name),
          'gender' => $this->gender,
          'designation' => $this->designation,
          'status' => $this->status, // online, offline
          'status_text' => $this->status_text,
          'engagement' => $this->engagement, // ready, busy
          'last_seen_at' => $this->last_seen_at,
          'last_seen_at_humanized' => Carbon::parse($this->last_seen_at)->diffForHumans(["short" => true, 'syntax' => CarbonInterface::DIFF_ABSOLUTE, "options" => Carbon::NO_ZERO_DIFF]),
          'img_path' => $this->img_path ? asset("storage/{$this->img_path}") : null,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
