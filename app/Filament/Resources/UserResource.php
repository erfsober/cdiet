<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\CustomBurnedCaloriesRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\CustomGainedCaloriesRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\NotesRelationManager;
use App\Models\User;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource {
    protected static ?string $model          = User::class;
    protected static ?string $navigationIcon = 'heroicon-s-users';

    public static function form ( Form $form ): Form {
        return $form->schema([//
                             ]);
    }

    public static function table ( Table $table ): Table {
        return $table->columns([
                                   TextColumn::make('id')
                                             ->translateLabel()
                                             ->searchable() ,
                                   TextColumn::make('full_name')
                                             ->translateLabel()
                                             ->searchable() ,
                                   TextColumn::make('email')
                                             ->translateLabel()
                                             ->searchable() ,
                                   TextColumn::make('phone_number')
                                             ->translateLabel()
                                             ->searchable() ,
                                   TextColumn::make('sex')
                                             ->translateLabel() ,
                                   IconColumn::make('is_premium')
                                             ->boolean()
                                             ->translateLabel() ,
                                   TextColumn::make('premium_days_left')
                                             ->numeric()
                                             ->translateLabel() ,

                               ])
                     ->filters([
                                   Filter::make('is_premium')
                                         ->translateLabel()
                                         ->toggle()
                                         ->query(fn ( Builder $query ): Builder => $query->premium()) ,
                                   Filter::make('only_male')
                                         ->translateLabel()
                                         ->toggle()
                                         ->query(fn ( Builder $query ): Builder => $query->male()) ,
                                   Filter::make('only_female')
                                         ->translateLabel()
                                         ->toggle()
                                         ->query(fn ( Builder $query ): Builder => $query->female()) ,
                               ])
                     ->actions([
                                   Tables\Actions\EditAction::make() ,
                               ])
                     ->bulkActions([])
                     ->emptyStateActions([//Tables\Actions\CreateAction::make(),
                                         ])
                     ->defaultSort('id' , 'desc');
    }

    public static function getRelations (): array {
        return [
            NotesRelationManager::class ,
            CommentsRelationManager::class ,
            CustomBurnedCaloriesRelationManager::class ,
            CustomGainedCaloriesRelationManager::class ,
        ];
    }

    public static function getPages (): array {
        return [
            'index' => Pages\ListUsers::route('/') ,
            //'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit') ,
        ];
    }

    public static function getLabel (): ?string {
        return __('User');
    }

    public static function getPluralLabel (): string {
        return __('Users');
    }
}
