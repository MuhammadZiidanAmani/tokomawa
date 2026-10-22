<?php

namespace App\Livewire\Dashboard;

use App\Domain\Shared\Support\IndonesiaFormatter;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Overview extends Component
{
    public string $currentDateTime = '';

    public function mount(IndonesiaFormatter $formatter): void
    {
        $this->currentDateTime = $formatter->dateTime(now());
    }

    public function render(): View
    {
        return view('livewire.dashboard.overview');
    }
}
