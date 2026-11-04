<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('slug', 160)->nullable()->unique()->after('business_name');
        });

        $businesses = DB::table('businesses')->orderBy('id')->get();
        $usedSlugs = [];

        foreach ($businesses as $business) {
            $baseSlug = Str::slug((string) $business->business_name);
            if ($baseSlug === '') {
                $baseSlug = 'business';
            }

            $slug = $baseSlug;
            $counter = 1;

            while (
                in_array($slug, $usedSlugs, true) ||
                DB::table('businesses')->where('slug', $slug)->where('id', '!=', $business->id)->exists()
            ) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $usedSlugs[] = $slug;

            DB::table('businesses')->where('id', $business->id)->update([
                'slug' => $slug,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('slug');
        });
    }
};
