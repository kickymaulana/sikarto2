<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $dueInstruments = Instrument::with(['type', 'factory', 'department', 'latestTest'])
            ->whereDoesntHave('tests')
            ->orWhereHas('tests', fn ($q) => $q->where('next_test_date', '<=', $today))
            ->orderBy('code')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'code' => $i->code,
                'type' => $i->type?->name,
                'factory' => $i->factory?->name,
                'department' => $i->department?->name,
                'latest_test_date' => $i->latestTest?->test_date?->toDateString(),
                'next_test_date' => $i->latestTest?->next_test_date?->toDateString(),
            ]);

        return Inertia::render('Dashboard', [
            'dueInstruments' => $dueInstruments,
        ]);
    }
}
