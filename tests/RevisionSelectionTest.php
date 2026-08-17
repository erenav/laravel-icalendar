<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Tests;

use Erenav\LaravelICalendar\Importing\CalendarImporter;

final class RevisionSelectionTest extends PersistenceTestCase
{
    public function test_last_modified_and_canonical_content_break_revision_ties_deterministically(): void
    {
        $timestamp = $this->calendar(<<<'EVENTS'
BEGIN:VEVENT
UID:timestamp-revision
SEQUENCE:1
DTSTAMP:20260815T120000Z
SUMMARY:Old timestamp
END:VEVENT
BEGIN:VEVENT
UID:timestamp-revision
SEQUENCE:1
DTSTAMP:20260815T130000Z
SUMMARY:New timestamp
END:VEVENT
EVENTS);
        $this->assertSame('New timestamp', app(CalendarImporter::class)->previewIcs($timestamp)->selectedEvents[0]->summary());

        $lastModified = $this->calendar(<<<'EVENTS'
BEGIN:VEVENT
UID:revision
SEQUENCE:1
LAST-MODIFIED:20260815T120000Z
SUMMARY:Old
END:VEVENT
BEGIN:VEVENT
UID:revision
SEQUENCE:1
LAST-MODIFIED:20260815T130000Z
SUMMARY:New
END:VEVENT
EVENTS);
        $preview = app(CalendarImporter::class)->previewIcs($lastModified);
        $this->assertSame('New', $preview->selectedEvents[0]->summary());
        $this->assertSame(1, $preview->discardedRevisions);
        $this->assertSame(["revision\0-"], $preview->discardedRevisionIds);

        $missingMetadataA = $this->calendar(<<<'EVENTS'
BEGIN:VEVENT
UID:no-metadata
SUMMARY:A
END:VEVENT
BEGIN:VEVENT
UID:no-metadata
SUMMARY:B
END:VEVENT
EVENTS);
        $missingMetadataB = $this->calendar(<<<'EVENTS'
BEGIN:VEVENT
UID:no-metadata
SUMMARY:B
END:VEVENT
BEGIN:VEVENT
UID:no-metadata
SUMMARY:A
END:VEVENT
EVENTS);
        $this->assertSame(
            app(CalendarImporter::class)->previewIcs($missingMetadataA)->selectedEvents[0]->summary(),
            app(CalendarImporter::class)->previewIcs($missingMetadataB)->selectedEvents[0]->summary(),
        );
        $this->assertSame('B', app(CalendarImporter::class)->previewIcs($missingMetadataA)->selectedEvents[0]->summary());
    }

    public function test_malformed_duplicate_revision_rejects_the_whole_identity(): void
    {
        $preview = app(CalendarImporter::class)->previewIcs($this->calendar(<<<'EVENTS'
BEGIN:VEVENT
UID:broken
SEQUENCE:1
SUMMARY:First
END:VEVENT
BEGIN:VEVENT
UID:broken
SEQUENCE:not-an-integer
SUMMARY:Malformed
END:VEVENT
BEGIN:VEVENT
UID:broken
SEQUENCE:3
SUMMARY:Must not return
END:VEVENT
EVENTS));

        $this->assertSame([], $preview->selectedEvents);
        $this->assertCount(2, $preview->invalid);
    }

    public function test_malformed_revision_metadata_is_invalid_even_without_a_duplicate(): void
    {
        $preview = app(CalendarImporter::class)->previewIcs($this->calendar(<<<'EVENTS'
BEGIN:VEVENT
UID:broken-single
SEQUENCE:-1
SUMMARY:Invalid revision
END:VEVENT
EVENTS));

        $this->assertSame([], $preview->selectedEvents);
        $this->assertCount(1, $preview->invalid);
        $this->assertStringContainsString('SEQUENCE', $preview->invalid[0]);
    }

    private function calendar(string $events): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Revision Test//EN\r\n".
            str_replace("\n", "\r\n", $events)."\r\nEND:VCALENDAR\r\n";
    }
}
