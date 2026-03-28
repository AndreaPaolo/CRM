<?php

namespace App\Filament\Resources\Abbonamentos;

use App\Filament\Resources\Abbonamentos\Pages\CreateAbbonamento;
use App\Filament\Resources\Abbonamentos\Pages\EditAbbonamento;
use App\Filament\Resources\Abbonamentos\Pages\ListAbbonamentos;
use App\Filament\Resources\Abbonamentos\Schemas\AbbonamentoForm;
use App\Filament\Resources\Abbonamentos\Tables\AbbonamentosTable;
use App\Models\Abbonamento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AbbonamentoResource extends Resource
{
    protected static ?string $model = Abbonamento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Abbonamento';

    public static function form(Schema $schema): Schema
    {
        return AbbonamentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AbbonamentosTable::configure($table);
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
            'index' => ListAbbonamentos::route('/'),
            'create' => CreateAbbonamento::route('/create'),
            'edit' => EditAbbonamento::route('/{record}/edit'),
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
