<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Verta;

class User extends Authenticatable implements HasMedia {
    use HasApiTokens , HasFactory , Notifiable , InteractsWithMedia;

    protected $casts = [
        'pregnant_status' => 'boolean' ,
        'lactation_status' => 'boolean' ,
        'allow_notification' => 'boolean' ,
    ];
    const GOALS           = [
        'gain-weight' => 'افزایش وزن' ,
        'loose-weight' => 'کاهش وزن' ,
        'maintain-weight' => 'تثبیت وزن' ,
    ];
    const GOAL_RATIOS     = [
        'افزایش وزن' => 1.25 ,
        'کاهش وزن' => 0.75 ,
        'تثبیت وزن' => 1 ,
    ];
    const EXERCISES       = [
        'low' => 'کم' ,
        'medium' => 'متوسط' ,
        'much' => 'زیاد' ,
        'very-much' => 'خیلی زیاد' ,
    ];
    const EXERCISE_RATIOS = [
        'کم' => 1.5 ,
        'متوسط' => 1.65 ,
        'زیاد' => 1.8 ,
        'خیلی زیاد' => 1.9 ,
    ];
    const EXERCISE_BMR    = [
        'کم' => 1.2 ,
        'متوسط' => 1.55 ,
        'زیاد' => 1.725 ,
        'خیلی زیاد' => 1.9 ,
    ];
    const SEXES           = [
        'male' => 'مرد' ,
        'female' => 'زن' ,
    ];

    public function registerMediaCollections (): void {
        $this->addMediaCollection('avatar')
             ->singleFile();
    }

    public function comments (): HasMany {
        return $this->hasMany(Comment::class);
    }

    public function notes (): HasMany {
        return $this->hasMany(Note::class);
    }

    protected function registerCompleted (): Attribute {
        return Attribute::make(get: fn () => (bool)$this->register_completed_at);
    }

    protected function isPremium (): Attribute {
        return Attribute::make(get: fn () => $this->premium_expires_at && $this->premium_expires_at > now());
    }

    protected function premiumDaysLeft (): Attribute {
        $premium_days_left = 0;
        if ( $this->premium_expires_at && $this->premium_expires_at > now() ) {
            $premium_days_left = Carbon::parse($this->premium_expires_at)
                                       ->diffInDays(now()) + 1;
        }

        return Attribute::make(get: fn () => $premium_days_left);
    }

    protected function isMale (): Attribute {
        return Attribute::make(get: fn () => $this->sex === self::SEXES[ 'male' ]);
    }

    protected function isFemale (): Attribute {
        return Attribute::make(get: fn () => $this->sex === self::SEXES[ 'female' ]);
    }

    protected function age (): Attribute {
        $age = $this->birthday ? Verta::now()
                                      ->diffYears(Verta::parse($this->birthday)) : 0;

        return Attribute::make(get: fn () => $age);
    }

    protected function dailyCalorieNeeded (): Attribute {
        return Attribute::make(get: function () {
            $age_ratio = $this->is_male ? 5 : -161;
            if ( $this->goal == null && $this->target_weight && $this->exercise ) {
                $formula = ( $this->weight * 10 ) + ( $this->height * 6.25 ) - ( $this->age * 5 ) + $age_ratio;

                $formula = $formula * self::EXERCISE_BMR[ $this->exercise ];
                if ( $this->weight > $this->target_weight ) {
                    $formula = $formula - 500;
                }
                else {
                    $formula = $formula + 500;
                }

                return $formula;
            }
            if ( !$this->register_completed || !$this->exercise || !$this->goal ) {
                return 0;
            }
            if ( $this->male ) {
                $result = ( $this->weight * 10 ) + ( $this->height * 6.25 ) - ( 5 * ( $this->age + $age_ratio ) );
            }
            else {
                $result = ( $this->weight * 10 ) + ( $this->height * 6.25 ) + ( 5 * ( $this->age + $age_ratio ) );
            }
            $result = self::EXERCISE_RATIOS[ $this->exercise ] * self::GOAL_RATIOS[ $this->goal ] * $result;

            return $result;
        });
    }

    protected function dailyCarbohydrateNeeded (): Attribute {
        if ( !$this->register_completed ) {
            return Attribute::make(get: fn () => 0);
        }
        $result = ( $this->daily_calorie_needed * 0.5 ) / 4;

        return Attribute::make(get: fn () => $result);
    }

    protected function dailyProteinNeeded (): Attribute {
        if ( !$this->register_completed ) {
            return Attribute::make(get: fn () => 0);
        }
        $result = ( $this->daily_calorie_needed * 0.22 ) / 4;

        return Attribute::make(get: fn () => $result);
    }

    protected function dailyFatNeeded (): Attribute {
        if ( !$this->register_completed ) {
            return Attribute::make(get: fn () => 0);
        }
        $result = ( $this->daily_calorie_needed * 0.28 ) / 9;

        return Attribute::make(get: fn () => $result);
    }

    public function scopePremium ( $query ) {
        return $query->whereNotNull('premium_expires_at')
                     ->where('premium_expires_at' , '>' , now());
    }

    public function scopeMale ( $query ) {
        return $query->where('sex' , self::SEXES[ 'male' ]);
    }

    public function scopeFemale ( $query ) {
        return $query->where('sex' , self::SEXES[ 'female' ]);
    }

    public function allowedCalorie ( $date ) {
        return $this->daily_calorie_needed;
    }

    public function targetWeight ( $date = null ) {
        return ( $this->target_weight && $this->goal == null ) ? $this->target_weight : ( $this->height - 100 );
    }

    public function targetWeightWeek ( $date ) {
        $last_plan = $this->lastPlan();
        if ( $last_plan && $last_plan->days ) {
            $weeks = $last_plan->days / 7 ?? 1;

            return ( $this->targetWeight(null) - $this->weight ) / $weeks;
        }

        return ( $this->targetWeight(null) - $this->weight ) / 4;
    }

    public function burnedCalorie ( $jalali_date ) {
        $total = 0;
        $user_activities_of_type_exercise = UserActivity::query()
                                                        ->where('date' , $jalali_date)
                                                        ->where('user_id' , $this->id)
                                                        ->where('type' , UserActivity::TYPES[ 'exercise' ])
                                                        ->get();
        foreach ( $user_activities_of_type_exercise as $user_activity_of_type_exercise ) {
            $exercise = Exercise::query()
                                ->find($user_activity_of_type_exercise->exercise_id);
            if ( $exercise ) {
                $total += $exercise->calorie;
            }
        }
        $user_activities_of_type_steps = UserActivity::query()
                                                     ->where('date' , $jalali_date)
                                                     ->where('user_id' , $this->id)
                                                     ->where('type' , UserActivity::TYPES[ 'step' ])
                                                     ->get();
        foreach ( $user_activities_of_type_steps as $user_activities_of_type_step ) {
            $steps_count = $user_activities_of_type_step->count;
            $total += round($steps_count / 20);
        }
        $cbcs = CustomBurnedCalorie::query()
                                   ->where('date' , $jalali_date)
                                   ->where('user_id' , $this->id)
                                   ->sum('amount');
        $total += $cbcs;

        return $total;
    }

    public function gainedCalorie ( $started_jalali_date , $ended_jalali_date = null ) {
        $ended_jalali_date = $ended_jalali_date ?? $started_jalali_date;
        $total = 0;
        $user_activities_of_type_food = UserActivity::query()
                                                    ->where('date' , '>=' , $started_jalali_date)
                                                    ->where('date' , '<=' , $ended_jalali_date)
                                                    ->where('user_id' , $this->id)
                                                    ->where('type' , UserActivity::TYPES[ 'food' ])
                                                    ->get();
        foreach ( $user_activities_of_type_food as $user_activity_of_type_food ) {
            $food = Food::query()
                        ->find($user_activity_of_type_food->food_id);
            if ( $food ) {
                $total += $food->calorie;
            }
        }
        $cgcs = CustomGainedCalorie::query()
                                   ->where('date' , '>=' , $started_jalali_date)
                                   ->where('date' , '<=' , $ended_jalali_date)
                                   ->where('user_id' , $this->id)
                                   ->sum('amount');
        $total += $cgcs;

        return $total;
    }

    public function gainedFat ( $jalali_date ) {
        $total = 0;
        $user_activities_of_type_food = UserActivity::query()
                                                    ->where('date' , $jalali_date)
                                                    ->where('user_id' , $this->id)
                                                    ->where('type' , UserActivity::TYPES[ 'food' ])
                                                    ->get();
        foreach ( $user_activities_of_type_food as $user_activity_of_type_food ) {
            $food = Food::query()
                        ->find($user_activity_of_type_food->food_id);
            if ( $food ) {
                $total += $food->fat;
            }
        }
        $cgcs = CustomGainedCalorie::query()
                                   ->where('date' , $jalali_date)
                                   ->where('user_id' , $this->id)
                                   ->sum('fat');
        $total += $cgcs;

        return $total;
    }

    public function gainedProtein ( $jalali_date ) {
        $total = 0;
        $user_activities_of_type_food = UserActivity::query()
                                                    ->where('date' , $jalali_date)
                                                    ->where('user_id' , $this->id)
                                                    ->where('type' , UserActivity::TYPES[ 'food' ])
                                                    ->get();
        foreach ( $user_activities_of_type_food as $user_activity_of_type_food ) {
            $food = Food::query()
                        ->find($user_activity_of_type_food->food_id);
            if ( $food ) {
                $total += $food->protein;
            }
        }
        $cgcs = CustomGainedCalorie::query()
                                   ->where('date' , $jalali_date)
                                   ->where('user_id' , $this->id)
                                   ->sum('protein');
        $total += $cgcs;

        return $total;
    }

    public function gainedCarbohydrate ( $jalali_date ) {
        $total = 0;
        $user_activities_of_type_food = UserActivity::query()
                                                    ->where('date' , $jalali_date)
                                                    ->where('user_id' , $this->id)
                                                    ->where('type' , UserActivity::TYPES[ 'food' ])
                                                    ->get();
        foreach ( $user_activities_of_type_food as $user_activity_of_type_food ) {
            $food = Food::query()
                        ->find($user_activity_of_type_food->food_id);
            if ( $food ) {
                $total += $food->carbohydrate;
            }
        }
        $cgcs = CustomGainedCalorie::query()
                                   ->where('date' , $jalali_date)
                                   ->where('user_id' , $this->id)
                                   ->sum('carbohydrate');
        $total += $cgcs;

        return $total;
    }

    public function drinkWaterCount ( $jalali_date ) {
        $total = 0;
        $drink_water = UserActivity::query()
                                   ->where('date' , $jalali_date)
                                   ->where('user_id' , $this->id)
                                   ->where('type' , UserActivity::TYPES[ 'drink-water' ])
                                   ->first();
        if ( $drink_water ) {
            $total = $drink_water->count;
        }

        return $total;
    }

    public function stepsCount ( $jalali_date ) {
        $total = 0;
        $step = UserActivity::query()
                            ->where('date' , $jalali_date)
                            ->where('user_id' , $this->id)
                            ->where('type' , UserActivity::TYPES[ 'step' ])
                            ->first();
        if ( $step ) {
            $total = $step->count;
        }

        return $total;
    }

    public function statistic ( $date ) {
        return [
            'allowed_calorie' => round($this->daily_calorie_needed , 2) ,
            'allowed_fat' => round($this->daily_fat_needed , 2) ,
            'allowed_protein' => round($this->daily_protein_needed , 2) ,
            'allowed_carbohydrate' => round($this->daily_carbohydrate_needed , 2) ,
            'recommended_burn_calorie' => 2000 ,
            'burned_calorie' => $this->burnedCalorie($date) ,
            'gained_calorie' => $this->gainedCalorie($date) ,
            'gained_fat' => $this->gainedFat($date) ,
            'gained_protein' => $this->gainedProtein($date) ,
            'gained_carbohydrate' => $this->gainedCarbohydrate($date) ,
            'target_weight' => $this->targetWeight($date) ,
            'target_weight_week' => $this->targetWeightWeek($date) ,
            'drink_water_count' => $this->drinkWaterCount($date) ,
            'steps' => $this->stepsCount($date) ,
        ];
    }

    public function statisticOfCurrentMonth () {
        return [
            'today_drink_water_count' => $this->drinkWaterCount(verta()->format('Y/m/d')) ,
            'today_burned_calorie' => $this->burnedCalorie(verta()->format('Y/m/d')) ,
            'current_month_total_gained_calorie' => $this->gainedCalorie(verta()
                                                                             ->startMonth()
                                                                             ->format('Y/m/d') , verta()
                                                                             ->endMonth()
                                                                             ->format('Y/m/d')) ,
            'current_week_total_gained_calorie' => $this->gainedCalorie(verta()
                                                                            ->startWeek()
                                                                            ->format('Y/m/d') , verta()
                                                                            ->endWeek()
                                                                            ->format('Y/m/d')) ,
        ];
    }

    public function addCredit ( $days ) {
        if ( $days > 0 ) {
            $current_time = $this->premium_expires_at > Carbon::now() ? $this->premium_expires_at : Carbon::now();
            $premium_expire_date = $current_time->addDays($days);
            $this->update([
                              'premium_expires_at' => $premium_expire_date ,
                          ]);
        }
    }

    public function userActivities (): HasMany {
        return $this->hasMany(UserActivity::class);
    }

    public function customBurnedCalories (): HasMany {
        return $this->hasMany(CustomBurnedCalorie::class);
    }

    public function customGainedCalories (): HasMany {
        return $this->hasMany(CustomGainedCalorie::class);
    }

    public function routeNotificationForFcm () {
        return $this->firebase_token;
    }

    public function transactions (): HasMany {
        return $this->hasMany(Transaction::class , 'user_id');
    }

    public function lastTransaction () {
        return $this->transactions()
                    ->whereNotNull('verified_at')
                    ->latest()
                    ->first();
    }

    public function lastPlan () {
        if ( $last_transaction = $this->lastTransaction() ) {
            return $last_transaction->plan;
        }

        return null;
    }
}
