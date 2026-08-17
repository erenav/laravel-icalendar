<?php

declare(strict_types=1);

use Erenav\LaravelICalendar\Support\TableRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('icalendar.persistence.connection'));
        $calendars = TableRegistry::calendar();
        $events = TableRegistry::event();
        $participants = TableRegistry::participant();
        $alarms = TableRegistry::alarm();

        $schema->create($calendars, function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('timezone')->nullable();
            $table->string('color')->nullable();
            $table->string('source_type', 24)->index();
            $table->boolean('enabled')->default(true)->index();
            $table->string('owner_type')->nullable();
            $table->string('owner_id')->nullable();
            $table->longText('component_ics');
            $table->timestamps();

            $table->index(['owner_type', 'owner_id'], 'ical_cal_owner_idx');
        });

        $schema->create($events, function (Blueprint $table) use ($calendars, $events): void {
            $table->uuid('id');
            $table->primary('id');
            $table->uuid('calendar_id');
            $table->uuid('recurring_master_id')->nullable();
            $table->char('identity_hash', 64);
            $table->char('uid_hash', 64)->index();
            $table->text('uid');
            $table->string('component_type', 32)->index();
            $table->string('recurrence_id_value')->nullable();
            $table->string('recurrence_id_type', 16)->nullable();
            $table->string('recurrence_id_timezone')->nullable();
            $table->string('recurrence_range', 32)->nullable();
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->text('location')->nullable();
            $table->text('url')->nullable();
            $table->string('dtstart_value')->nullable();
            $table->string('dtstart_type', 16)->nullable();
            $table->string('dtstart_timezone')->nullable();
            $table->dateTime('dtstart_utc')->nullable()->index();
            $table->string('dtend_value')->nullable();
            $table->string('dtend_type', 16)->nullable();
            $table->string('dtend_timezone')->nullable();
            $table->dateTime('dtend_utc')->nullable()->index();
            $table->string('duration')->nullable();
            $table->string('source_timezone')->nullable();
            $table->string('status', 32)->nullable()->index();
            $table->string('transparency', 32)->nullable();
            $table->string('classification', 32)->nullable();
            $table->unsignedSmallInteger('priority')->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->string('color')->nullable();
            $table->dateTime('ical_created_at')->nullable();
            $table->dateTime('ical_dtstamp')->nullable();
            $table->dateTime('ical_last_modified_at')->nullable();
            $table->text('rrule')->nullable();
            $table->boolean('is_cancelled')->default(false)->index();
            $table->longText('component_ics');
            $table->timestamps();

            $table->foreign('calendar_id')->references('id')->on($calendars)->cascadeOnDelete();
            $table->foreign('recurring_master_id')->references('id')->on($events)->nullOnDelete();
            $table->unique(['calendar_id', 'identity_hash'], 'ical_event_identity_uq');
            $table->index(['recurring_master_id', 'recurrence_id_value'], 'ical_event_master_rid_idx');
        });

        $schema->create($participants, function (Blueprint $table) use ($events): void {
            $table->uuid('id');
            $table->primary('id');
            $table->uuid('event_id');
            $table->unsignedInteger('position');
            $table->string('type', 16)->index();
            $table->text('calendar_address');
            $table->text('common_name')->nullable();
            $table->string('role', 32)->nullable();
            $table->string('participation_status', 32)->nullable()->index();
            $table->string('user_type', 32)->nullable();
            $table->boolean('rsvp')->nullable();
            $table->json('member')->nullable();
            $table->json('delegated_to')->nullable();
            $table->json('delegated_from')->nullable();
            $table->text('sent_by')->nullable();
            $table->text('directory')->nullable();
            $table->string('language')->nullable();
            $table->json('unknown_parameters')->nullable();
            $table->text('property_ics');
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on($events)->cascadeOnDelete();
            $table->unique(['event_id', 'position'], 'ical_participant_position_uq');
        });

        $schema->create($alarms, function (Blueprint $table) use ($events): void {
            $table->uuid('id');
            $table->primary('id');
            $table->uuid('event_id');
            $table->unsignedInteger('position');
            $table->string('action', 32)->nullable();
            $table->text('trigger_value')->nullable();
            $table->string('trigger_type', 32)->nullable();
            $table->text('description')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedInteger('repeat_count')->nullable();
            $table->string('repeat_duration')->nullable();
            $table->text('component_ics');
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on($events)->cascadeOnDelete();
            $table->unique(['event_id', 'position'], 'ical_alarm_position_uq');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(config('icalendar.persistence.connection'));

        $schema->dropIfExists(TableRegistry::alarm());
        $schema->dropIfExists(TableRegistry::participant());
        $schema->dropIfExists(TableRegistry::event());
        $schema->dropIfExists(TableRegistry::calendar());
    }
};
