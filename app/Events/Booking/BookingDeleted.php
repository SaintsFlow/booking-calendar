<?php

namespace App\Events\Booking;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BookingDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $bookingData;
    public int $tenantId;

    public function __construct(array $bookingData, int $tenantId)
    {
        $this->bookingData = $bookingData;
        $this->tenantId = $tenantId;

        Log::info('📢 BookingDeleted event created', [
            'booking_id' => $bookingData['id'],
            'tenant_id' => $tenantId,
        ]);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.deleted';
    }

    public function broadcastWith(): array
    {
        $data = [
            'booking_id' => $this->bookingData['id'],
            'message' => 'Бронь удалена',
        ];

        Log::info('📡 Broadcasting BookingDeleted', [
            'booking_id' => $this->bookingData['id'],
            'channel' => 'tenant.' . $this->tenantId,
            'event' => 'booking.deleted',
        ]);

        return $data;
    }
}
