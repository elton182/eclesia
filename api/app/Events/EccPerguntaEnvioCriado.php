<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EccPerguntaEnvio;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EccPerguntaEnvioCriado implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly EccPerguntaEnvio $envio,
        public readonly string $responsavelUlid,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('eclesia.user.'.$this->responsavelUlid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pergunta.envio.criado';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $envio = $this->envio->loadMissing(['pergunta', 'equipe']);

        return [
            'envio_id' => (string) $envio->id,
            'pergunta_id' => (string) $envio->pergunta_id,
            'pergunta_titulo' => (string) ($envio->pergunta?->titulo ?? ''),
            'equipe_id' => (string) $envio->equipe_id,
            'equipe_nome' => (string) ($envio->equipe?->nome ?? ''),
            'enviado_em' => $envio->enviado_em?->toIso8601String(),
        ];
    }
}
