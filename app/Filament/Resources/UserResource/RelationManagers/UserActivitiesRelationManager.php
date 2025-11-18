<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\UserActivity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'userActivities';
    protected static ?string $title = 'فعالیت های کاربر';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('نوع فعالیت')
                    ->options([
                        UserActivity::TYPES['exercise'] => 'ورزش',
                        UserActivity::TYPES['food'] => 'غذا',
                        UserActivity::TYPES['drink-water'] => 'نوشیدن آب',
                        UserActivity::TYPES['step'] => 'قدم',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('date')
                    ->label('تاریخ')
                    ->required(),
                Forms\Components\TextInput::make('exercise_id')
                    ->label('شناسه ورزش')
                    ->numeric(),
                Forms\Components\TextInput::make('food_id')
                    ->label('شناسه غذا')
                    ->numeric(),
                Forms\Components\TextInput::make('recommended_meal_id')
                    ->label('شناسه وعده پیشنهادی')
                    ->numeric(),
                Forms\Components\TextInput::make('count')
                    ->label('تعداد')
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('شناسه')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع فعالیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        UserActivity::TYPES['exercise'] => 'success',
                        UserActivity::TYPES['food'] => 'warning',
                        UserActivity::TYPES['drink-water'] => 'info',
                        UserActivity::TYPES['step'] => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        UserActivity::TYPES['exercise'] => 'ورزش',
                        UserActivity::TYPES['food'] => 'غذا',
                        UserActivity::TYPES['drink-water'] => 'نوشیدن آب',
                        UserActivity::TYPES['step'] => 'قدم',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('date')
                    ->label('تاریخ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('food.name')
                    ->label('غذا')
                    ->default('-'),
                Tables\Columns\TextColumn::make('exercise_id')
                    ->label('شناسه ورزش')
                    ->default('-'),
                Tables\Columns\TextColumn::make('recommended_meal_id')
                    ->label('شناسه وعده')
                    ->default('-'),
                Tables\Columns\TextColumn::make('count')
                    ->label('تعداد')
                    ->numeric()
                    ->default('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع فعالیت')
                    ->options([
                        UserActivity::TYPES['exercise'] => 'ورزش',
                        UserActivity::TYPES['food'] => 'غذا',
                        UserActivity::TYPES['drink-water'] => 'نوشیدن آب',
                        UserActivity::TYPES['step'] => 'قدم',
                    ]),
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
