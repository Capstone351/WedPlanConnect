<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Cebu place suggestions dropped their addresses ("Radisson Blu Hotel Cebu, Cebu City" became
 * "Radisson Blu Hotel Cebu"). Saved places picked from the old list are renamed the same way, so the
 * double-booking check, which compares venues by name, still matches old and new bookings.
 * Places people typed themselves are left as they are.
 */
return new class extends Migration
{
    /** @var array<string, string> old suggestion => new name */
    private const RENAMES = [
        'Radisson Blu Hotel Cebu, Cebu City' => 'Radisson Blu Hotel Cebu',
        'Marco Polo Plaza Cebu, Nivel Hills, Cebu City' => 'Marco Polo Plaza Cebu',
        'Waterfront Cebu City Hotel & Casino, Lahug, Cebu City' => 'Waterfront Cebu City Hotel & Casino',
        'Seda Ayala Center Cebu, Cebu City' => 'Seda Ayala Center Cebu',
        'Quest Hotel & Conference Center, Cebu City' => 'Quest Hotel & Conference Center',
        'Cebu Parklane International Hotel, Cebu City' => 'Cebu Parklane International Hotel',
        'Cebu Grand Hotel, Cebu City' => 'Cebu Grand Hotel',
        'Montebello Villa Hotel, Banilad, Cebu City' => 'Montebello Villa Hotel',
        'Harolds Hotel, Gorordo Ave., Cebu City' => 'Harolds Hotel',
        'NUSTAR Resort and Casino, South Road Properties, Cebu City' => 'NUSTAR Resort and Casino',
        'Bai Hotel Cebu, Mandaue City' => 'Bai Hotel Cebu',
        'Shangri-La Mactan, Lapu-Lapu City' => 'Shangri-La Mactan',
        'Dusit Thani Mactan Cebu Resort, Lapu-Lapu City' => 'Dusit Thani Mactan Cebu Resort',
        'Crimson Resort and Spa Mactan, Lapu-Lapu City' => 'Crimson Resort and Spa Mactan',
        'Plantation Bay Resort and Spa, Lapu-Lapu City' => 'Plantation Bay Resort and Spa',
        'Casa Gorordo Museum, Cebu City' => 'Casa Gorordo Museum',
        'Museo Sugbo, Cebu City' => 'Museo Sugbo',
        'Fort San Pedro, Cebu City' => 'Fort San Pedro',
        'Casino Español de Cebu, Cebu City' => 'Casino Español de Cebu',
        'Cebu Country Club, Banilad, Cebu City' => 'Cebu Country Club',
        'Ayala Center Cebu, Cebu Business Park, Cebu City' => 'Ayala Center Cebu',
        'SM City Cebu, North Reclamation Area, Cebu City' => 'SM City Cebu',
        'SM Seaside City Cebu, South Road Properties, Cebu City' => 'SM Seaside City Cebu',
        'Robinsons Galleria Cebu, Cebu City' => 'Robinsons Galleria Cebu',
        'Ayala Malls Central Bloc, Cebu IT Park, Cebu City' => 'Ayala Malls Central Bloc',
        'Banilad Town Centre, Cebu City' => 'Banilad Town Centre',
        'Gaisano Country Mall, Banilad, Cebu City' => 'Gaisano Country Mall',
        'Cebu IT Park, Lahug, Cebu City' => 'Cebu IT Park',
        'Cebu Business Park, Cebu City' => 'Cebu Business Park',
    ];

    /** @var array<string, string> table => column holding a place */
    private const COLUMNS = [
        'bookings' => 'venue',
        'users' => 'wedding_venue',
        'meetings' => 'location',
        'portfolio_entries' => 'venue',
    ];

    public function up(): void
    {
        $this->rename(self::RENAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAMES));
    }

    /** @param  array<string, string>  $renames */
    private function rename(array $renames): void
    {
        foreach (self::COLUMNS as $table => $column) {
            foreach ($renames as $from => $to) {
                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }
};
