<?php

use App\Support\HubTimezone;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_events', function (Blueprint $table): void {
            $table->timestamp('start_time')->nullable()->after('event_type');
            $table->timestamp('end_time')->nullable()->after('start_time');
        });

        DB::table('hub_events')
            ->join('hubs', 'hubs.id', '=', 'hub_events.hub_id')
            ->select([
                'hub_events.id',
                'hub_events.date_from',
                'hub_events.date_to',
                'hub_events.time_from',
                'hub_events.time_to',
                'hubs.timezone',
            ])
            ->orderBy('hub_events.id')
            ->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $timezone = HubTimezone::resolve($row->timezone);
                    $startLocal = Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        sprintf('%s %s', $row->date_from, $row->time_from ?? '00:00:00'),
                        $timezone
                    );
                    $endLocal = Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        sprintf('%s %s', $row->date_to, $row->time_to ?? '23:59:59'),
                        $timezone
                    );

                    DB::table('hub_events')
                        ->where('id', $row->id)
                        ->update([
                            'start_time' => $startLocal->utc(),
                            'end_time' => $endLocal->utc(),
                        ]);
                }
            });

        Schema::table('hub_events', function (Blueprint $table): void {
            $table->dropColumn(['date_from', 'date_to', 'time_from', 'time_to']);
        });
    }

    public function down(): void
    {
        Schema::table('hub_events', function (Blueprint $table): void {
            $table->date('date_from')->nullable()->after('event_type');
            $table->date('date_to')->nullable()->after('date_from');
            $table->time('time_from')->nullable()->after('date_to');
            $table->time('time_to')->nullable()->after('time_from');
        });

        DB::table('hub_events')
            ->join('hubs', 'hubs.id', '=', 'hub_events.hub_id')
            ->select([
                'hub_events.id',
                'hub_events.start_time',
                'hub_events.end_time',
                'hubs.timezone',
            ])
            ->orderBy('hub_events.id')
            ->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $timezone = HubTimezone::resolve($row->timezone);
                    $start = Carbon::parse($row->start_time)->setTimezone($timezone);
                    $end = Carbon::parse($row->end_time)->setTimezone($timezone);

                    DB::table('hub_events')
                        ->where('id', $row->id)
                        ->update([
                            'date_from' => $start->toDateString(),
                            'date_to' => $end->toDateString(),
                            'time_from' => $start->format('H:i:s'),
                            'time_to' => $end->format('H:i:s'),
                        ]);
                }
            });

        Schema::table('hub_events', function (Blueprint $table): void {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
