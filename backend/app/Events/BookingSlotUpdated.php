<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingSlotUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $hubId,
        public readonly int $courtId,
        public readonly string $status,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("hub.{$this->hubId}");
    }

    public function broadcastAs(): string
    {
        return 'booking.slot.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'hub_id' => $this->hubId,
            'court_id' => $this->courtId,
            'status' => $this->status,
        ];
    }
}
