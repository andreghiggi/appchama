<?php

namespace Tests\Unit;

use App\Domains\Maps\Services\GooglePlacesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GooglePlacesServiceTest extends TestCase
{
    public function test_sem_chave_fica_desligado_e_nao_chama_o_google(): void
    {
        config(['services.google.places_key' => null]);
        Http::fake();

        $service = app(GooglePlacesService::class);

        $this->assertFalse($service->isConfigured());
        $this->assertSame([], $service->search('super paludo', -28.84, -51.89, 50));
        Http::assertNothingSent();
    }

    public function test_acha_locais_pelo_nome_e_formata_o_rotulo(): void
    {
        config(['services.google.places_key' => 'chave-de-teste']);
        Cache::flush();
        Http::fake([
            'places.googleapis.com/*' => Http::response(['places' => [
                ['displayName' => ['text' => 'Super Paludo'], 'formattedAddress' => 'Av. Brasil, 100 - Centro, Guaporé - RS, 99200-000, Brasil', 'location' => ['latitude' => -28.841, 'longitude' => -51.891]],
                ['displayName' => ['text' => 'Sem local'], 'formattedAddress' => 'X'],
            ]]),
        ]);

        $out = app(GooglePlacesService::class)->search('super paludo', -28.84, -51.89, 50);

        $this->assertCount(1, $out);
        $this->assertSame('Super Paludo, Av. Brasil, 100 - Centro, Guaporé - RS, 99200-000', $out[0]['label']);
        $this->assertEqualsWithDelta(-28.841, $out[0]['latitude'], 0.0001);
        Http::assertSent(fn ($r) => $r->hasHeader('X-Goog-Api-Key', 'chave-de-teste')
            && $r['textQuery'] === 'super paludo'
            && $r['locationBias']['circle']['radius'] === 50000.0);
    }

    public function test_erro_do_google_nao_derruba_a_busca(): void
    {
        config(['services.google.places_key' => 'chave-de-teste']);
        Cache::flush();
        Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'API not enabled']], 403)]);

        $this->assertSame([], app(GooglePlacesService::class)->search('hospital', -28.84, -51.89, 50));
    }
}
