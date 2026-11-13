<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bride and groom first/last names are entered separately. The full names (bride_name, groom_name)
     * are kept, built from these, so everything that shows the couple's names keeps working.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bride_first_name', 60)->nullable()->after('groom_name');
            $table->string('bride_last_name', 60)->nullable()->after('bride_first_name');
            $table->string('groom_first_name', 60)->nullable()->after('bride_last_name');
            $table->string('groom_last_name', 60)->nullable()->after('groom_first_name');
        });

        // Existing full names: the last word becomes the last name, the rest the first name.
        DB::table('users')->where(fn ($q) => $q->whereNotNull('bride_name')->orWhereNotNull('groom_name'))
            ->orderBy('id')->each(function (object $user) {
                [$brideFirst, $brideLast] = $this->split($user->bride_name);
                [$groomFirst, $groomLast] = $this->split($user->groom_name);

                DB::table('users')->where('id', $user->id)->update([
                    'bride_first_name' => $brideFirst, 'bride_last_name' => $brideLast,
                    'groom_first_name' => $groomFirst, 'groom_last_name' => $groomLast,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bride_first_name', 'bride_last_name', 'groom_first_name', 'groom_last_name']);
        });
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function split(?string $fullName): array
    {
        $parts = preg_split('/\s+/', trim((string) $fullName), -1, PREG_SPLIT_NO_EMPTY);

        if (! $parts) {
            return [null, null];
        }

        $last = count($parts) > 1 ? array_pop($parts) : null;

        return [mb_substr(implode(' ', $parts), 0, 60), $last ? mb_substr($last, 0, 60) : null];
    }
};
