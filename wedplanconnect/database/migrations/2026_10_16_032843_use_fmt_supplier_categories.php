<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames supplier and budget categories to FMT's own supplier groupings
 * (Coordination, Hair & Make-up Artists, Photo & Video, Lights & Sounds,
 * Event Styling, Cakes, Entourage Dress(es)).
 */
return new class extends Migration
{
    private const SUPPLIER_CATEGORIES = [
        'Florist' => 'Event Styling',
        'Photographer' => 'Photo & Video',
        'Videographer' => 'Photo & Video',
        'Hair & Makeup' => 'Hair & Make-up Artists',
        'Cake' => 'Cakes',
        'Gowns & Suits' => 'Entourage Dress(es)',
        'Host / Emcee' => 'Coordination',
    ];

    private const BUDGET_CATEGORIES = self::SUPPLIER_CATEGORIES + [
        'Decorations & Styling' => 'Event Styling',
        'Caterer' => 'Catering',
        'Band / Musicians' => 'Other',
    ];

    public function up(): void
    {
        foreach (self::SUPPLIER_CATEGORIES as $old => $new) {
            DB::table('suppliers')->where('category', $old)->update(['category' => $new]);
        }

        // A budget can hold each category once, so merge amounts when two old categories become one.
        foreach (self::BUDGET_CATEGORIES as $old => $new) {
            foreach (DB::table('budget_allocations')->where('category', $old)->get() as $row) {
                $existing = DB::table('budget_allocations')
                    ->where('client_budget_id', $row->client_budget_id)->where('category', $new)->first();

                if ($existing) {
                    DB::table('budget_allocations')->where('id', $existing->id)->update(['amount' => $existing->amount + $row->amount]);
                    DB::table('budget_allocations')->where('id', $row->id)->delete();
                } else {
                    DB::table('budget_allocations')->where('id', $row->id)->update(['category' => $new]);
                }
            }
        }
    }

    public function down(): void
    {
        // Merged categories cannot be split back apart.
    }
};
