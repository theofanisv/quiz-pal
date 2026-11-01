<?php

namespace App\Livewire;

use App\Actions\Game\CreateRoom;
use App\Models\Room;
use App\Support\RoomCode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::game')]
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
        return view('livewire.home')->title(__('Play'));
    }
}
