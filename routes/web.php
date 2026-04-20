<?php

use App\Http\Controllers\Portfolio\PortfolioController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::inertia('manage-assets', 'manage-assets')->name('manage-assets');

    // Endpoints financeiros explícitos: sem refresh implícito e sem lógica no controller.
    Route::get('portfolio/assets', [PortfolioController::class, 'assets'])->name('portfolio.assets');
    Route::get('portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
    Route::post('portfolio', [PortfolioController::class, 'store'])->name('portfolio.store');
    Route::post('portfolio/refresh', [PortfolioController::class, 'refresh'])->name('portfolio.refresh');
    Route::delete('portfolio/{portfolioPosition}', [PortfolioController::class, 'destroy'])->name('portfolio.destroy');
});

require __DIR__.'/settings.php';
