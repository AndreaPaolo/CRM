<?php

namespace App\Filament\Resources\Servizios;

use App\Filament\Resources\Servizios\Pages\CreateServizio;
use App\Filament\Resources\Servizios\Pages\EditServizio;
use App\Filament\Resources\Servizios\Pages\ListServizios;
use App\Filament\Resources\Servizios\Schemas\ServizioForm;
use App\Filament\Resources\Servizios\Tables\ServiziosTable;
use App\Models\Servizio;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServizioResource extends Resource
{
    protected static ?string $model = Servizio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Servizio';

    public static function form(Schema $schema): Schema
    {
        return ServizioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiziosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServizios::route('/'),
            'create' => CreateServizio::route('/create'),
            'edit' => EditServizio::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
