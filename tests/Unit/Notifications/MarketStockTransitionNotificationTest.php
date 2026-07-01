<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Notifications;

use Raikia\SeatMarketSeeding\Notifications\MarketStock\Discord\MarketStockRestocked;
use Raikia\SeatMarketSeeding\Notifications\MarketStock\Discord\MarketStockTransition;
use Raikia\SeatMarketSeeding\Tests\TestCase;
use Seat\Notifications\Services\Discord\Messages\DiscordMessage;

class MarketStockTransitionNotificationTest extends TestCase
{
    public function test_discord_transition_notification_renders_grouped_empty_alert(): void
    {
        $notification = new class($this->alert('empty')) extends MarketStockTransition {
            public function render(DiscordMessage $message): void
            {
                $this->populateMessage($message, null);
            }
        };
        $message = new DiscordMessage();

        $notification->render($message);

        $this->assertSame('error', $message->level);
        $this->assertSame('2 market items empty', $message->content);
        $this->assertSame('Empty Items', $message->embeds[0]->fields[1]->toArray()['name']);
    }

    public function test_discord_transition_notification_renders_grouped_low_alert(): void
    {
        $notification = new class($this->alert('low')) extends MarketStockTransition {
            public function render(DiscordMessage $message): void
            {
                $this->populateMessage($message, null);
            }
        };
        $message = new DiscordMessage();

        $notification->render($message);

        $this->assertSame('warning', $message->level);
        $this->assertSame('2 market items low', $message->content);
        $this->assertSame('Low Items', $message->embeds[0]->fields[1]->toArray()['name']);
    }

    public function test_discord_restocked_notification_renders_grouped_alert(): void
    {
        $notification = new class($this->restockedAlert()) extends MarketStockRestocked {
            public function render(DiscordMessage $message): void
            {
                $this->populateMessage($message, null);
            }
        };
        $message = new DiscordMessage();

        $notification->render($message);

        $this->assertSame('success', $message->level);
        $this->assertSame('2 market items restocked', $message->content);
        $this->assertSame('Restocked Items', $message->embeds[0]->fields[1]->toArray()['name']);
    }

    private function alert(string $status): array
    {
        return [
            'current_status' => $status,
            'item_count' => 2,
            'market_name' => 'Home',
            'location_name' => 'Z-XMUC - Ends of Invention',
            'dashboard_url' => 'https://seat.test/market-seeding',
            'timestamp' => now(),
            'items' => [
                [
                    'type_name' => 'Warp Scrambler II',
                    'current_quantity' => $status === 'empty' ? 0 : 2,
                    'warning_quantity' => 4,
                    'desired_quantity' => 10,
                    'previous_status' => 'stocked',
                    'current_status' => $status,
                ],
                [
                    'type_name' => 'Damage Control II',
                    'current_quantity' => $status === 'empty' ? 0 : 3,
                    'warning_quantity' => 5,
                    'desired_quantity' => 15,
                    'previous_status' => 'low',
                    'current_status' => $status,
                ],
            ],
        ];
    }

    private function restockedAlert(): array
    {
        return [
            'item_count' => 2,
            'market_name' => 'Home',
            'location_name' => 'Z-XMUC - Ends of Invention',
            'dashboard_url' => 'https://seat.test/market-seeding',
            'timestamp' => now(),
            'items' => [
                [
                    'type_name' => 'Warp Scrambler II',
                    'current_quantity' => 10,
                    'warning_quantity' => 4,
                    'desired_quantity' => 10,
                    'previous_status' => 'empty',
                    'current_status' => 'stocked',
                ],
                [
                    'type_name' => 'Damage Control II',
                    'current_quantity' => 15,
                    'warning_quantity' => 5,
                    'desired_quantity' => 15,
                    'previous_status' => 'low',
                    'current_status' => 'stocked',
                ],
            ],
        ];
    }
}
