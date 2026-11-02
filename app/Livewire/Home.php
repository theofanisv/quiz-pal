<?php

namespace App\Livewire;

use App\Actions\Game\CreateRoom;
use App\Enums\Locale;
use App\Models\Room;
use App\Support\RoomCode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Home extends Component
{
    public string $name = '';

    public string $code = '';

    /**
     * Create a room, make the visitor its admin and take them to the lobby.
     */
    public function create(CreateRoom $createRoom): void
    {
        $room = $createRoom->handle($this->name);

        Auth::guard('player')->login($room->admin);

        $this->redirectRoute('rooms.show', $room);
    }

    /**
     * Take the visitor to the room with the typed code.
     */
    public function join(): void
    {
        $code = RoomCode::normalize($this->code);

        $room = RoomCode::isValid($code) ? Room::query()->where('code', $code)->first() : null;

        if ($room === null) {
            $this->addError('code', __('No room was found with that code.'));

            return;
        }

        $this->redirectRoute('rooms.show', $room);
    }

    public function render(): View
    {
        $description = __('Create a room, share the link or QR code, and play together on your phones.');
        $homeUrl = Locale::current()->homeUrl();

        return view('livewire.home')
            ->title(__('Quiz night with friends'))
            ->layout('layouts::game', [
                'description' => $description,
                'canonical' => $homeUrl,
                'alternates' => $this->homeUrls(),
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebApplication',
                    'name' => config('app.name'),
                    'url' => $homeUrl,
                    'description' => $description,
                    'applicationCategory' => 'GameApplication',
                    'operatingSystem' => 'Any',
                    'browserRequirements' => 'Requires JavaScript',
                    'inLanguage' => array_map(fn (Locale $locale): string => $locale->value, Locale::cases()),
                    'isAccessibleForFree' => true,
                    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR'],
                    'image' => asset('og-image.png'),
                ],
            ]);
    }

    /**
     * The home page of every language, keyed by language code, for the hreflang links.
     *
     * @return array<string, string>
     */
    private function homeUrls(): array
    {
        $urls = [];

        foreach (Locale::cases() as $locale) {
            $urls[$locale->value] = $locale->homeUrl();
        }

        return $urls;
    }
}
