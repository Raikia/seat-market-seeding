<?php

namespace Raikia\SeatMarketSeeding\Notifications\MarketStock\Discord;

use Seat\Notifications\Notifications\AbstractDiscordNotification;
use Seat\Notifications\Services\Discord\Messages\DiscordEmbed;
use Seat\Notifications\Services\Discord\Messages\DiscordEmbedField;
use Seat\Notifications\Services\Discord\Messages\DiscordMessage;

class MarketStockRestocked extends AbstractDiscordNotification
{
    private array $alert;

    public function __construct(array $alert)
    {
        $this->alert = $alert;
    }

    protected function populateMessage(DiscordMessage $message, $notifiable)
    {
        $title = sprintf('%s market item%s restocked', number_format($this->alert['item_count']), $this->alert['item_count'] === 1 ? '' : 's');

        $message
            ->content($title)
            ->from('SeAT Market Seeding')
            ->embed(function (DiscordEmbed $embed) use ($title) {
                $embed->timestamp($this->alert['timestamp']);
                $embed->author('SeAT Market Seeding', asset('web/img/favicon/apple-icon-180x180.png'), $this->alert['dashboard_url']);
                $embed->title($title);
                $embed->color(DiscordMessage::SUCCESS);

                $embed->field(function (DiscordEmbedField $field) {
                    $field->name('Market')
                        ->value(sprintf('%s (%s)', $this->alert['market_name'], $this->alert['location_name']))
                        ->long();
                });

                $embed->field(function (DiscordEmbedField $field) {
                    $field->name('Restocked Items')
                        ->value($this->formatItems())
                        ->long();
                });
            });

        $message->success();
    }

    private function formatItems(): string
    {
        $items = collect($this->alert['items']);
        $lines = $items->take(15)->map(function (array $item) {
            return sprintf(
                '%s: %s / %s (was %s)',
                $item['type_name'],
                number_format($item['current_quantity']),
                number_format($item['desired_quantity']),
                $item['previous_status']
            );
        });

        if ($items->count() > $lines->count()) {
            $lines->push(sprintf('...and %s more', number_format($items->count() - $lines->count())));
        }

        return $lines->implode("\n");
    }
}
