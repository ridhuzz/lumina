<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Rupiah;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan di dashboard admin. */
class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $customerAccounts = Account::whereHas('user', fn ($q) => $q->customers());
        $today = Transaction::whereDate('occurred_at', today());

        $inToday = (clone $today)->where('direction', 'in')->sum('amount');
        $outToday = (clone $today)->where('direction', 'out')->sum('amount');

        return [
            Stat::make('Nasabah', User::customers()->count())
                ->description('terdaftar')
                ->icon(Heroicon::OutlinedUsers),
            Stat::make('Total saldo nasabah', Rupiah::format((clone $customerAccounts)->sum('balance')))
                ->description((clone $customerAccounts)->count().' rekening')
                ->icon(Heroicon::OutlinedBuildingLibrary),
            Stat::make('Transaksi hari ini', (clone $today)->count())
                ->description('Masuk '.Rupiah::format($inToday).' · Keluar '.Rupiah::format($outToday))
                ->icon(Heroicon::OutlinedArrowsRightLeft),
        ];
    }
}
