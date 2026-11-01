<?php

namespace App\View\Components;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * An inline SVG QR code for a URL, so players can scan the room link from another device.
 */
class QrCode extends Component
{
    public function __construct(
        public string $url,
        public int $size = 192,
    ) {}

    public function render(): View
    {
        return view('components.qr-code', ['svg' => $this->svg()]);
    }

    private function svg(): string
    {
        $renderer = new ImageRenderer(new RendererStyle($this->size, 0), new SvgImageBackEnd);

        $svg = (new Writer($renderer))->writeString($this->url);

        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
