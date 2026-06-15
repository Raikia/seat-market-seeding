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
        $title = $empty ? 'Market item is empty' : 'Market item is low';

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
                    $field->name('Item')
                        ->value($this->alert['type_name'])
                        ->long();
                });

                $embed->field(function (DiscordEmbedField $field) {
                    $field->name('Current')
                        ->value(number_format($this->alert['current_quantity']))
                        ->long();
                });

                $embed->field(function (DiscordEmbedField $field) {
                    $field->name('Low Warning')
                        ->value(number_format($this->alert['warning_quantity']))
                        ->long();
                });

                $embed->field(function (DiscordEmbedField $field) {
                    $field->name('Target')
                        ->value(number_format($this->alert['desired_quantity']))
                        ->long();
                });

                $embed->field(function (DiscordEmbedField $field) {
                    $field->name('Transition')
                        ->value($this->alert['previous_status'] . ' -> ' . $this->alert['current_status'])
                        ->long();
                });
            });

        $empty ? $message->error() : $message->warning();
    }
}
