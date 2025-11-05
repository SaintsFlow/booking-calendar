<?php

namespace App\Events\Booking;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BookingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;

        Log::info('📢 BookingUpdated event created', [
            'booking_id' => $booking->id,
            'tenant_id' => $booking->tenant_id,
        ]);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->booking->tenant_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.updated';
    }

    public function broadcastWith(): array
    {
        $data = [
            'booking' => $this->booking->load(['client', 'employee', 'workplace', 'services', 'status'])->toArray(),
            'message' => 'Бронь обновлена',
        ];

        Log::info('📡 Broadcasting BookingUpdated', [
            'booking_id' => $this->booking->id,
            'channel' => 'tenant.' . $this->booking->tenant_id,
            'event' => 'booking.updated',
        ]);

        return $data;
    }
}
