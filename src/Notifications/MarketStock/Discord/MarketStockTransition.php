<?php

namespace Raikia\SeatMarketSeeding\Notifications\MarketStock\Discord;

use Seat\Notifications\Notifications\AbstractDiscordNotification;
use Seat\Notifications\Services\Discord\Messages\DiscordEmbed;
use Seat\Notifications\Services\Discord\Messages\DiscordEmbedField;
use Seat\Notifications\Services\Discord\Messages\DiscordMessage;

class MarketStockTransition extends AbstractDiscordNotification
{
    private array $alert;

    public function __construct(array $alert)
    {
        $this->alert = $alert;
    }

    protected function populateMessage(DiscordMessage $message, $notifiable)
    {
        $empty = $this->alert['current_status'] === 'empty';
        $this->normalizeItems();
        $title = sprintf(
            '%s market item%s %s',
            number_format($this->alert['item_count']),
            $this->alert['item_count'] === 1 ? '' : 's',
            $empty ? 'empty' : 'low'
        );

        $message
            ->content($title)
            ->from('SeAT Market Seeding')
            ->embed(function (DiscordEmbed $embed) use ($title, $empty) {
                $embed->timestamp($this->alert['timestamp']);
                $embed->author('SeAT Market Seeding', asset('web/img/favicon/apple-icon-180x180.png'), $this->alert['dashboard_url']);
                $embed->title($title);
                $embed->color($empty ? DiscordMessage::ERROR : DiscordMessage::WARNING);

                $embed->field(function (DiscordEmbedField $field) {
                    $field->name('Market')
                        ->value(sprintf('%s (%s)', $this->alert['market_name'], $this->alert['location_name']))
                        ->long();
                });

                $embed->field(function (DiscordEmbedField $field) {
                    $field->name($empty ? 'Empty Items' : 'Low Items')
                        ->value($this->formatItems())
                        ->long();
                });
            });

        $empty ? $message->error() : $message->warning();
    }

    private function normalizeItems(): void
    {
        if (isset($this->alert['items'])) {
            return;
        }

        $this->alert['items'] = [[
            'type_name' => $this->alert['type_name'],
            'current_quantity' => $this->alert['current_quantity'],
            'warning_quantity' => $this->alert['warning_quantity'],
            'desired_quantity' => $this->alert['desired_quantity'],
            'previous_status' => $this->alert['previous_status'],
            'current_status' => $this->alert['current_status'],
        ]];
        $this->alert['item_count'] = 1;
    }

    private function formatItems(): string
    {
        $items = collect($this->alert['items']);
        $lines = $items->take(15)->map(function (array $item) {
            return sprintf(
                '%s: %s / %s target (warn %s, was %s)',
                $item['type_name'],
                number_format($item['current_quantity']),
                number_format($item['desired_quantity']),
                number_format($item['warning_quantity']),
                $item['previous_status']
            );
        });

        if ($items->count() > $lines->count()) {
            $lines->push(sprintf('...and %s more', number_format($items->count() - $lines->count())));
        }

        return $lines->implode("\n");
    }
}
