<?php

namespace App\Filament\Resources\Cities\Concerns;

use App\Domains\Maps\Services\CityDepartureCoordinateService;

trait EnsuresCityDepartureCoordinates
{
    protected ?string $coordinateCorrectionMessage = null;

    /** @param  array<string, mixed>  $data */
    protected function ensureCityDepartureCoordinates(array $data): array
    {
        $this->coordinateCorrectionMessage = null;

        if (empty($data['rides_enabled'])) {
            return $data;
        }

        $name = trim((string) ($data['name'] ?? ''));
        $state = trim((string) ($data['state'] ?? ''));

        if ($name === '' || $state === '') {
            return $data;
        }

        $result = app(CityDepartureCoordinateService::class)->normalize(
            $data['departure_lat'] ?? null,
            $data['departure_lng'] ?? null,
            $name,
            $state,
            autoCorrect: true,
            strict: true,
        );

        $data['departure_lat'] = $result['lat'];
        $data['departure_lng'] = $result['lng'];

        if (empty($data['departure_address']) && $result['reference_label']) {
            $data['departure_address'] = $result['reference_label'];
        }

        if ($result['corrected'] && $result['message']) {
            $this->coordinateCorrectionMessage = $result['message'];
        }

        return $data;
    }

    protected function notifyCoordinateCorrection(): void
    {
        if ($this->coordinateCorrectionMessage === null) {
            return;
        }

        \Filament\Notifications\Notification::make()
            ->title('Coordenadas ajustadas')
            ->body($this->coordinateCorrectionMessage)
            ->warning()
            ->send();
    }
}
