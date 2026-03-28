<?php

namespace App\Filament\Resources\Appuntamentos;

use App\Filament\Resources\Appuntamentos\Pages\CreateAppuntamento;
use App\Filament\Resources\Appuntamentos\Pages\EditAppuntamento;
use App\Filament\Resources\Appuntamentos\Pages\ListAppuntamentos;
use App\Filament\Resources\Appuntamentos\Schemas\AppuntamentoForm;
use App\Filament\Resources\Appuntamentos\Tables\AppuntamentosTable;
use App\Models\Appuntamento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AppuntamentoResource extends Resource
{
    protected static ?string $model = Appuntamento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Appuntamento';

    public static function form(Schema $schema): Schema
    {
        return AppuntamentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppuntamentosTable::configure($table);
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
            'index' => ListAppuntamentos::route('/'),
            'create' => CreateAppuntamento::route('/create'),
            'edit' => EditAppuntamento::route('/{record}/edit'),
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
