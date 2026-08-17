<?php

namespace App\Console\Commands;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nats:listen-orders')]
#[Description('Feliratkozik az order.created NATS eseményre és naplózza')]
class ListenOrderCreated extends Command
{
    public function handle(): int
    {
        $configuration = new Configuration([
            'host' => config('nats.host'),
            'port' => config('nats.port'),
        ]);
        $configuration->setDelay(0.001);

        $client = new Client($configuration);

        // FIGYELEM: a NestJS a pontot alulvonásra cseréli → 'order_created'
        $client->subscribe('order_created', function ($message) {
            $envelope = json_decode($message->body, true);

            // A NestJS egy { pattern, data } borítékba csomagol — a mi adatunk a 'data'-ban van
            $data = $envelope['data'] ?? [];

            $this->info('📦 Új rendelés érkezett!');
            $this->line('  Rendelés ID: ' . ($data['orderId'] ?? 'ismeretlen'));
            $this->line('  Végösszeg: ' . ($data['totalAmount'] ?? '?') . ' Ft');
            $this->line('  Tételek száma: ' . ($data['itemCount'] ?? '?'));

            logger()->info('Új rendelés értesítés', $data);
        });

        $this->info('Figyelem az order_created eseményt... (Ctrl+C a leállításhoz)');

        while (true) {
            $client->process(1);
        }
    }
}