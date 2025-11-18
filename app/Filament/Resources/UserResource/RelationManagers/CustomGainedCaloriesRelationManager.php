<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomGainedCaloriesRelationManager extends RelationManager
{
    protected static string $relationship = 'customGainedCalories';
    protected static ?string $title = 'کالری های دریافتی';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->label('مقدار کالری')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('fat')
                    ->label('چربی')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('protein')
                    ->label('پروتئین')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('carbohydrate')
                    ->label('کربوهیدرات')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('date')
                    ->label('تاریخ')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->label('مقدار کالری')
                    ->numeric(),
                Tables\Columns\TextColumn::make('fat')
                    ->label('چربی')
                    ->numeric(),
                Tables\Columns\TextColumn::make('protein')
                    ->label('پروتئین')
                    ->numeric(),
                Tables\Columns\TextColumn::make('carbohydrate')
                    ->label('کربوهیدرات')
                    ->numeric(),
                Tables\Columns\TextColumn::make('date')
                    ->label('تاریخ'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
